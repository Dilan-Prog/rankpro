<?php

namespace App\Http\Controllers\Api\V1\Reportes;

use App\Models\Reporte;
use App\Services\Reportes\PublicadorReporte;
use App\Support\Api\Respuesta;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\URL;

/**
 * Genera el PDF/XLSX de un reporte (App\Services\Reportes\PublicadorReporte,
 * la misma clase que usa Admin\ReporteGeneracionController) y devuelve una
 * URL firmada temporal de descarga en vez del binario: n8n no necesita
 * mandar el Bearer token para bajar el archivo, solo seguir el enlace antes
 * de que expire.
 */
class ReporteGeneracionApiController
{
    public function __construct(private PublicadorReporte $publicador)
    {
    }

    public function pdf(Reporte $reporte): JsonResponse
    {
        return $this->generar($reporte, 'pdf');
    }

    public function xlsx(Reporte $reporte): JsonResponse
    {
        return $this->generar($reporte, 'xlsx');
    }

    private function generar(Reporte $reporte, string $formato): JsonResponse
    {
        $archivo = $this->publicador->publicar($reporte, $formato);

        $url = URL::temporarySignedRoute(
            'api.publico.archivos.descargar',
            now()->addMinutes((int) config('api.url_firmada_minutos', 15)),
            ['archivo' => $archivo->id]
        );

        return Respuesta::recurso(['archivo_id' => $archivo->id, 'url' => $url], 201);
    }
}
