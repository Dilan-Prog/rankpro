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
        $binario = base64_decode($base64, true);
        if ($binario === false) {
            throw new ErrorDeDominio('El contenido base64 no es válido.', 'contenido_base64');
        }

        $extension = $nombreOriginal ? pathinfo($nombreOriginal, PATHINFO_EXTENSION) : '';
        $tmpPath = tempnam(sys_get_temp_dir(), 'archivo_api_');
        file_put_contents($tmpPath, $binario);

        if ($extension) {
            $conExtension = $tmpPath.'.'.$extension;
            rename($tmpPath, $conExtension);
            $tmpPath = $conExtension;
        }

        return new UploadedFile($tmpPath, $nombreOriginal ?? basename($tmpPath), null, null, true);
    }

    /** Mismo flujo de guardado que Admin\ArchivosController::store(). */
    private function guardarArchivo(array $data, UploadedFile $file, int $usuarioId): Archivo
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?? ''));
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
