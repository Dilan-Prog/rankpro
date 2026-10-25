<?php

namespace App\Http\Controllers\Api\V1\SearchConsole;

use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\GscPropiedad;
use App\Services\SearchConsole\Sincronizador;
use App\Services\SearchConsole\SincronizacionEnCurso;
use App\Support\Api\Respuesta;
use Illuminate\Http\JsonResponse;

/** Propiedades de Search Console para n8n; nunca expone la llave de la cuenta de servicio. */
class SearchConsoleApiController extends ControladorApi
{
    public function index(): JsonResponse
    {
        return Respuesta::coleccion(
            GscPropiedad::query()->with('cliente:id,nombre')->orderBy('id')->get()->map(fn (GscPropiedad $p) => $p->toRow())->all()
        );
    }

    public function sincronizar(GscPropiedad $propiedad, Sincronizador $sincronizador): JsonResponse
    {
        if (! $propiedad->activa) {
            return Respuesta::mensaje('La propiedad está pausada.', 422);
        }

        @set_time_limit(300);

        try {
            $r = $sincronizador->sincronizar($propiedad);
        } catch (SincronizacionEnCurso $e) {
            return Respuesta::mensaje($e->getMessage(), 409);
        }

        return $r['ok']
            ? Respuesta::recurso($propiedad->fresh('cliente')->toRow() + ['resultado' => $r['mensaje']])
            : Respuesta::mensaje($r['mensaje'], 502, ['data' => $propiedad->fresh('cliente')->toRow()]);
    }
}
