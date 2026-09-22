<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Api\Consulta;
use App\Support\Api\ConsultaOpciones;
use App\Support\Api\Respuesta;
use App\Support\Api\Serializador;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Base de los controladores de /api/v1/*: helpers de paginación y
 * serialización compartidos, para que cada controlador de módulo repita lo
 * mínimo (ver App\Support\Api\{Consulta,Serializador,Respuesta}).
 */
abstract class ControladorApi extends Controller
{
    /**
     * $query acepta un Builder (Modelo::query()/where()) o una relación
     * (p. ej. $campana->posiciones()): una relación no es instancia de
     * Builder pero delega en uno vía __call, así que Consulta::aplicar()
     * la acepta igual.
     */
    protected function listar(Builder|Relation $query, Request $request, ConsultaOpciones $opciones): JsonResponse
    {
        $paginador = Consulta::aplicar($query, $request, $opciones);
        $incluir = array_values(array_intersect(
            array_filter(explode(',', (string) $request->string('incluir'))),
            $opciones->incluibles
        ));

        return Respuesta::paginada($paginador, fn ($modelo) => Serializador::modelo($modelo, $incluir));
    }
}
