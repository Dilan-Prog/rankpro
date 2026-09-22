<?php

namespace App\Http\Controllers\Api\V1\Reportes;

use App\Http\Controllers\Admin\ReporteSeccionController;
use App\Models\Reporte;
use App\Models\ReporteSeccion;
use App\Support\Api\Respuesta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Secciones de un reporte. La validación/normalización del `contenido` por
 * tipo de sección (EsquemaSeccion) es bastante grande y ya vive completa en
 * Admin\ReporteSeccionController — en vez de duplicarla, se delega en esa
 * clase (misma validación, mismas reglas) y aquí solo se traduce la
 * respuesta a la forma {data: ...} de la API.
 */
class ReporteSeccionesApiController
{
    public function __construct(private ReporteSeccionController $delegado)
    {
    }

    public function index(Reporte $reporte): JsonResponse
    {
        return Respuesta::coleccion($reporte->secciones()->get()->map(fn (ReporteSeccion $s) => $s->toRow())->values()->all());
    }

    public function store(Request $request, Reporte $reporte): JsonResponse
    {
        return $this->reenvasar($this->delegado->store($request, $reporte));
    }

    public function update(Request $request, Reporte $reporte, ReporteSeccion $seccion): JsonResponse
    {
        return $this->reenvasar($this->delegado->update($request, $reporte, $seccion));
    }

    public function destroy(Reporte $reporte, ReporteSeccion $seccion): JsonResponse
    {
        $this->delegado->destroy($reporte, $seccion);

        return Respuesta::eliminado();
    }

    public function reordenar(Request $request, Reporte $reporte): JsonResponse
    {
        return $this->reenvasar($this->delegado->reordenar($request, $reporte));
    }

    /** Envuelve el JsonResponse del controlador web en {data: ...}, conservando su status. */
    private function reenvasar(JsonResponse $respuesta): JsonResponse
    {
        return Respuesta::recurso($respuesta->getData(true), $respuesta->getStatusCode());
    }
}
