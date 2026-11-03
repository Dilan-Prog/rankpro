<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Exceptions\ErrorDeDominio;
use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\Archivo;
use App\Support\Api\ConsultaOpciones;
use App\Support\Api\Respuesta;
use App\Support\Api\Serializador;
use App\Support\Reglas\Archivos as ReglasArchivos;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;

/**
 * Archivos de clientes vía API: además de multipart, acepta JSON con
 * contenido_base64 (n8n no siempre arma un multipart/form-data). La
 * descarga real reusa la ruta pública genérica que ya existe para esto
 * (Api\Publico\ArchivoDescargaController, `api.publico.archivos.descargar`,
 * ver routes/api_publico_archivos.php) en vez de duplicarla: esa ruta ya
 * vive fuera de auth:sanctum tras el middleware `signed`, protegida
 * únicamente por la firma temporal que genera url() aquí abajo.
 */
class ArchivosApiController extends ControladorApi
{
    public function index(Request $request)
    {
        return $this->listar(Archivo::query(), $request, new ConsultaOpciones(
            buscarEn: ['nombre'],
            filtrosExactos: ['cliente_id', 'tipo'],
            ordenables: ['id', 'nombre', 'created_at', 'updated_at'],
            incluibles: ['cliente', 'usuario'],
        ));
    }

    public function show(Archivo $archivo)
    {
        return Respuesta::recurso(Serializador::modelo($archivo));
    }

    public function store(Request $request)
    {
        if ($request->hasFile('archivo')) {
            $data = $request->validate(ReglasArchivos::multipart());
            $file = $request->file('archivo');
        } else {
            $data = $request->validate(ReglasArchivos::base64());
            $file = $this->archivoDesdeBase64($data['contenido_base64'], $data['nombre'] ?? null);

            // El base64 se saltaba el tope y la lista de tipos del multipart: se aplican las mismas reglas
            // al archivo ya decodificado (el MIME se detecta por contenido, no por lo que diga el nombre).
            $validador = Validator::make(['archivo' => $file], ['archivo' => ReglasArchivos::multipart()['archivo']], [
                'archivo.max' => 'El archivo no puede pesar más de 100 MB.',
                'archivo.mimes' => 'El tipo de archivo no está permitido.',
            ]);
            if ($validador->fails()) {
                @unlink($file->getPathname());

                throw new ErrorDeDominio($validador->errors()->first('archivo'), 'contenido_base64');
            }
        }

        $archivo = $this->guardarArchivo($data, $file, $request->user()->id);

        return Respuesta::recurso(Serializador::modelo($archivo->fresh()), 201);
    }

    public function destroy(Archivo $archivo)
    {
        if (Storage::disk('local')->exists($archivo->ruta_archivo)) {
            Storage::disk('local')->delete($archivo->ruta_archivo);
        }

        $archivo->delete();

        return Respuesta::eliminado();
    }

    /** URL firmada temporal (ver config('api.url_firmada_minutos')) hacia la descarga pública ya existente. */
    public function url(Archivo $archivo)
    {
        $minutos = (int) config('api.url_firmada_minutos', 15);

        $url = URL::temporarySignedRoute(
            'api.publico.archivos.descargar',
            now()->addMinutes($minutos),
            ['archivo' => $archivo->id]
        );

        return Respuesta::recurso(['url' => $url, 'expira_en_minutos' => $minutos]);
    }

    private function archivoDesdeBase64(string $base64, ?string $nombreOriginal): UploadedFile
    {
        // Tope duro antes de decodificar: 100 MB reales ocupan ~137 MB de base64; no hace falta cargar más en memoria.
        if (strlen($base64) > 140 * 1024 * 1024) {
            throw new ErrorDeDominio('El archivo no puede pesar más de 100 MB.', 'contenido_base64');
        }

        $binario = base64_decode($base64, true);
        if ($binario === false) {
            throw new ErrorDeDominio('El contenido base64 no es válido.', 'contenido_base64');
        }

        // El temporal va SIN extensión: la que se guarda sale del MIME detectado, no del nombre que mande el cliente.
        $tmpPath = tempnam(sys_get_temp_dir(), 'archivo_api_');
        file_put_contents($tmpPath, $binario);

        return new UploadedFile($tmpPath, $nombreOriginal ? basename($nombreOriginal) : basename($tmpPath), null, null, true);
    }

    /** Mismo flujo de guardado que Admin\ArchivosController::store(). */
    private function guardarArchivo(array $data, UploadedFile $file, int $usuarioId): Archivo
    {
        // Extensión del MIME validado (guessExtension), nunca del nombre del cliente.
        $extension = strtolower($file->extension() ?? '');
        $nombre = filled($data['nombre'] ?? null) ? $data['nombre'] : $file->getClientOriginalName();

        $path = $file->store("clientes/{$data['cliente_id']}/archivos", 'local');

        return Archivo::create([
            'cliente_id' => $data['cliente_id'],
            'nombre' => $nombre,
            'tipo' => $data['tipo'],
            'ruta_archivo' => $path,
            'tamano' => Storage::disk('local')->size($path),
            'extension' => $extension,
            'subido_por' => $usuarioId,
        ]);
    }
}
