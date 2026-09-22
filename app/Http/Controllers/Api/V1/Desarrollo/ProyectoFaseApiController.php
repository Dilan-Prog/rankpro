<?php

namespace App\Http\Controllers\Api\V1\Desarrollo;

use App\Enums\FaseProyecto;
use App\Http\Controllers\Admin\ProyectoFaseController;
use App\Http\Controllers\Controller;
use App\Models\Proyecto;
use App\Support\Api\Respuesta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fase actual de un proyecto de Desarrollo. `guardar`/`aprobar`/`retroceder`
 * se delegan en Admin\ProyectoFaseController: ese controlador ya responde
 * JSON cuando $request->wantsJson() (aquí siempre true, lo pone el
 * middleware `api.json`), así que no hay que reimplementar la máquina de
 * fases — solo envolver su JsonResponse en {data: ...}.
 */
class ProyectoFaseApiController extends Controller
{
    public function __construct(private ProyectoFaseController $delegado)
    {
    }

    /** No existe en el panel (ahí la fase se ve dentro del show completo): se arma aquí. */
    public function ver(Proyecto $proyecto): JsonResponse
    {
        $fase = $proyecto->fase_actual;
        $registro = $fase === FaseProyecto::Cerrado ? $proyecto->control : $this->registroFase($proyecto, $fase);

        return Respuesta::recurso([
            'fase_actual' => $fase->value,
            'fase_orden' => $fase->orden(),
            'estado' => $proyecto->estado->value,
            'porcentaje_avance' => $proyecto->porcentaje_avance,
            'checklist' => $registro?->checklist,
            'aprobado' => $registro?->aprobado,
            'fecha_aprobacion' => $registro?->fecha_aprobacion?->toIso8601String(),
        ]);
    }

    public function guardar(Request $request, Proyecto $proyecto): JsonResponse
    {
        return $this->reenvasar($this->delegado->guardar($request, $proyecto));
    }

    public function aprobar(Request $request, Proyecto $proyecto): JsonResponse
    {
        return $this->reenvasar($this->delegado->aprobar($request, $proyecto));
    }

    public function retroceder(Request $request, Proyecto $proyecto): JsonResponse
    {
        return $this->reenvasar($this->delegado->retroceder($request, $proyecto));
    }

    private function reenvasar(JsonResponse $respuesta): JsonResponse
    {
        return Respuesta::recurso($respuesta->getData(true), $respuesta->getStatusCode());
    }

    /** Mismo `firstOrCreate` por fase que Admin\ProyectoFaseController::registroFase() (privado ahí). */
    private function registroFase(Proyecto $proyecto, FaseProyecto $fase)
    {
        return match ($fase) {
            FaseProyecto::Planeacion => $proyecto->planeacion()->firstOrCreate([], ['checklist' => []]),
            FaseProyecto::Organizacion => $proyecto->organizacion()->firstOrCreate([], ['checklist' => [], 'equipo' => []]),
            FaseProyecto::Direccion => $proyecto->direccion()->firstOrCreate([], ['checklist' => []]),
            FaseProyecto::Control => $proyecto->control()->firstOrCreate([], ['checklist' => []]),
            FaseProyecto::Cerrado => null,
        };
    }
}
