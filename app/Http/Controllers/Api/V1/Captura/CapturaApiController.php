<?php

namespace App\Http\Controllers\Api\V1\Captura;

use App\Http\Controllers\Api\V1\ControladorApi;
use App\Support\Api\Respuesta;
use App\Support\Captura\TableroCaptura;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/captura: lo que calcula el tablero "Pendientes de captura" del
 * panel, en JSON estable para que n8n avise (WhatsApp/correo) de lo que falta.
 */
class CapturaApiController extends ControladorApi
{
    public function __invoke(Request $request, TableroCaptura $tablero): JsonResponse
    {
        $datos = $request->validate([
            'mes' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'solo_pendientes' => ['nullable', 'boolean'],
        ]);

        $mes = TableroCaptura::resolverMes($datos['mes'] ?? null);
        $r = $tablero->calcular($mes, (bool) ($datos['solo_pendientes'] ?? false));

        $filas = [];
        $pendientes = [];
        foreach ($r['filas'] as $fila) {
            $celdas = [];
            foreach ($fila['celdas'] as $fuente => $celda) {
                $celda = ['fuente' => $fuente, 'etiqueta' => TableroCaptura::FUENTES[$fuente]] + $celda;
                $celdas[] = $celda;

                // Lista plana lista para iterar en n8n (un aviso por elemento).
                if (in_array($celda['estado'], [TableroCaptura::FALTA, TableroCaptura::PARCIAL], true)) {
                    $pendientes[] = ['cliente_id' => $fila['cliente']->id, 'cliente' => $fila['cliente']->nombre] + $celda;
                }
            }
            $filas[] = [
                'cliente_id' => $fila['cliente']->id,
                'cliente' => $fila['cliente']->nombre,
                'pendientes' => $fila['pendientes'],
                'celdas' => $celdas,
            ];
        }

        return Respuesta::recurso([
            'mes' => $mes->format('Y-m'),
            'resumen' => $r['resumen'],
            'filas' => $filas,
            'pendientes' => $pendientes,
        ]);
    }
}
