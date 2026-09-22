<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Archivo;
use App\Models\CorreoAdjunto;
use App\Models\CorreoEnvio;
use App\Support\Labels;
use App\Support\Reglas\CorreoAdjuntos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Adjuntos de un envío de correo: subida directa (multipart) o copia desde
 * un Archivo ya existente del módulo Archivos (ArchivosController). No hay
 * Route::scopeBindings() en el grupo de rutas de envíos, así que la
 * pertenencia envio/adjunto se valida a mano en cada acción con {adjunto}.
 */
class CorreoAdjuntosController extends Controller
{
    public function store(Request $request, CorreoEnvio $envio): JsonResponse
    {
        if (! $envio->estado->editable()) {
            return response()->json(['message' => 'Este envío ya no admite cambios.'], 422);
        }

        $data = $request->validate(CorreoAdjuntos::multipart());

        if (($error = $this->validarTopes($envio, (int) $data['archivo']->getSize())) !== null) {
            return $error;
        }

        $file = $request->file('archivo');
        $path = $file->store("correo/envios/{$envio->id}/adjuntos", 'local');

        $adjunto = CorreoAdjunto::create([
            'envio_id' => $envio->id,
            'nombre' => filled($request->input('nombre')) ? $request->input('nombre') : $file->getClientOriginalName(),
            'ruta' => $path,
            'disco' => 'local',
            'mime' => $file->getMimeType(),
            'tamano' => Storage::disk('local')->size($path),
            'subido_por' => $request->user()->id,
        ]);

        return response()->json($this->toAdjuntoRow($adjunto), 201);
    }

    public function desdeArchivo(Request $request, CorreoEnvio $envio): JsonResponse
    {
        if (! $envio->estado->editable()) {
            return response()->json(['message' => 'Este envío ya no admite cambios.'], 422);
        }

        if (($error = $this->validarTopeConteo($envio)) !== null) {
            return $error;
        }

        $data = $request->validate([
            'archivo_id' => ['required', 'integer', 'exists:archivos,id'],
        ]);

        $archivo = Archivo::findOrFail($data['archivo_id']);

        if (! Storage::disk('local')->exists($archivo->ruta_archivo)) {
            return response()->json(['message' => 'Ese archivo no tiene un documento real disponible.'], 422);
        }

        if (($error = $this->validarTopeTamano($envio, (int) $archivo->tamano)) !== null) {
            return $error;
        }

        // Copia física, no referencia in-place: si el archivo original del
        // módulo Archivos se borra después, este adjunto no debe romperse.
        $nuevaRuta = "correo/envios/{$envio->id}/adjuntos/".Str::uuid().'.'.$archivo->extension;
        Storage::disk('local')->copy($archivo->ruta_archivo, $nuevaRuta);

        $adjunto = CorreoAdjunto::create([
            'envio_id' => $envio->id,
            'nombre' => $archivo->nombre,
            'ruta' => $nuevaRuta,
            'disco' => 'local',
            'mime' => Storage::disk('local')->mimeType($nuevaRuta),
            'tamano' => $archivo->tamano,
            'subido_por' => $request->user()->id,
        ]);

        return response()->json($this->toAdjuntoRow($adjunto), 201);
    }

    public function disponibles(Request $request, CorreoEnvio $envio): JsonResponse
    {
        $buscar = trim((string) $request->string('buscar'));

        $query = Archivo::with('cliente');

        if ($buscar !== '') {
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                    ->orWhereHas('cliente', function ($c) use ($buscar) {
                        $c->where('empresa', 'like', "%{$buscar}%")->orWhere('nombre', 'like', "%{$buscar}%");
                    });
            });
        }

        $archivos = $query->orderBy('created_at', 'desc')->limit(20)->get();

        return response()->json([
            'archivos' => $archivos->map(fn (Archivo $a) => [
                'id' => $a->id,
                'nombre' => $a->nombre,
                'cliente' => $a->cliente?->empresa ?: $a->cliente?->nombre,
                'tipo_label' => Labels::tipoArchivo($a->tipo->value),
                'tamano_legible' => $this->tamanoLegible((int) $a->tamano),
                'extension' => $a->extension,
            ])->values(),
        ]);
    }

    public function destroy(CorreoEnvio $envio, CorreoAdjunto $adjunto): JsonResponse
    {
        if ($adjunto->envio_id !== $envio->id) {
            abort(404);
        }

        if (! $envio->estado->editable()) {
            return response()->json(['message' => 'Este envío ya no admite cambios.'], 422);
        }

        if (Storage::disk($adjunto->disco)->exists($adjunto->ruta)) {
            Storage::disk($adjunto->disco)->delete($adjunto->ruta);
        }

        $adjunto->delete();

        return response()->json(['ok' => true, 'deleted' => true]);
    }

    public function descargar(CorreoEnvio $envio, CorreoAdjunto $adjunto): StreamedResponse
    {
        if ($adjunto->envio_id !== $envio->id) {
            abort(404);
        }

        return Storage::disk($adjunto->disco)->download($adjunto->ruta, $adjunto->nombre);
    }

    // -------------------------------------------------------------------------

    /** Chequeo combinado de tope de conteo y de tamaño, para store(). */
    private function validarTopes(CorreoEnvio $envio, int $tamanoNuevo): ?JsonResponse
    {
        return $this->validarTopeConteo($envio) ?? $this->validarTopeTamano($envio, $tamanoNuevo);
    }

    private function validarTopeConteo(CorreoEnvio $envio): ?JsonResponse
    {
        if ($envio->adjuntos()->count() >= CorreoAdjuntos::MAX_ADJUNTOS) {
            return response()->json(['message' => 'Un envío admite como máximo 5 adjuntos.'], 422);
        }

        return null;
    }

    private function validarTopeTamano(CorreoEnvio $envio, int $tamanoNuevo): ?JsonResponse
    {
        if ((int) $envio->adjuntos()->sum('tamano') + $tamanoNuevo > CorreoAdjuntos::MAX_TOTAL_BYTES) {
            return response()->json(['message' => 'El conjunto de adjuntos no puede superar 20 MB (límite típico de los proveedores de correo).'], 422);
        }

        return null;
    }

    private function toAdjuntoRow(CorreoAdjunto $a): array
    {
        return [
            'id' => $a->id,
            'nombre' => $a->nombre,
            'extension' => strtolower(pathinfo($a->nombre, PATHINFO_EXTENSION)),
            'mime' => $a->mime,
            'tamano' => $a->tamano,
            'tamano_legible' => $this->tamanoLegible($a->tamano),
            'creado_en' => $a->created_at?->format('Y-m-d H:i'),
            'url_descarga' => route('admin.correo.envios.adjuntos.descargar', [$a->envio_id, $a]),
            'url_eliminar' => route('admin.correo.envios.adjuntos.destroy', [$a->envio_id, $a]),
        ];
    }

    /** Formato tipo "240 KB" / "2.4 MB" (1 decimal a partir de 1 MB). */
    private function tamanoLegible(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        return round($bytes / 1024).' KB';
    }
}
