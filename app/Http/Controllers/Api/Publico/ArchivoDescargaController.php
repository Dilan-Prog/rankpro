<?php

namespace App\Http\Controllers\Api\Publico;

use App\Http\Controllers\Controller;
use App\Models\Archivo;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga pública (fuera de auth:sanctum) de un Archivo ya generado por la
 * API v1 (reportes/propuestas en PDF o XLSX). La ruta va protegida con el
 * middleware `signed` de Laravel: sin una URL firmada con
 * `URL::temporarySignedRoute()` responde 403 automáticamente.
 *
 * Genérica a propósito (no "de reportes"): cualquier endpoint de la API que
 * genere un Archivo puede reusar esta misma ruta para entregar su descarga
 * temporal sin tener que declarar la suya.
 */
class ArchivoDescargaController extends Controller
{
    public function __invoke(Archivo $archivo): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($archivo->ruta_archivo), 404);

        return Storage::disk('local')->download($archivo->ruta_archivo, basename($archivo->ruta_archivo));
    }
}
