<?php

namespace App\Http\Controllers\Api\V1\Propuestas;

use App\Http\Controllers\Admin\PropuestaController;
use App\Models\Propuesta;
use App\Support\Api\Respuesta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PATCH de una sección (pestaña) de la propuesta y sugerencia de consultas
 * desde el banco de keywords. `actualizarSeccion` es un match con reglas
 * de validación distintas por sección (resumen/situacion/contexto/plan/
 * condiciones/visibilidad) que ya vive completo en Admin\PropuestaController
 * — se delega ahí en vez de duplicar ~150 líneas de reglas.
 */
class PropuestaSeccionesApiController
{
    public function __construct(private PropuestaController $delegado)
    {
    }

    public function actualizar(Request $request, Propuesta $propuesta, string $seccion): JsonResponse
    {
        $respuesta = $this->delegado->actualizarSeccion($request, $propuesta, $seccion);

        return Respuesta::recurso($respuesta->getData(true), $respuesta->getStatusCode());
    }

    public function sugerirConsultas(Propuesta $propuesta): JsonResponse
    {
        $respuesta = $this->delegado->sugerirConsultas($propuesta);

        return Respuesta::recurso($respuesta->getData(true), $respuesta->getStatusCode());
    }
}
