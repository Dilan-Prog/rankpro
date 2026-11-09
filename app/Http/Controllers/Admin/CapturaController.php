<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Captura\TableroCaptura;
use App\Support\ClienteActivo;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tablero de pendientes de captura del mes: qué clientes no tienen medido qué.
 * Solo lee; la captura sigue ocurriendo en la pantalla de cada módulo.
 */
class CapturaController extends Controller
{
    public function index(Request $request, TableroCaptura $tablero): View
    {
        $mes = TableroCaptura::resolverMes($request->query('mes'));
        $soloPendientes = $request->boolean('pendientes');
        $clienteActivo = ClienteActivo::modelo();

        return view('admin.captura.index', [
            'pageTitle' => 'Pendientes de captura',
            'clienteActivo' => $clienteActivo,
            'tablero' => $tablero->calcular($mes, $soloPendientes, $clienteActivo?->id),
            'fuentes' => TableroCaptura::FUENTES,
            'mes' => $mes,
            'soloPendientes' => $soloPendientes,
            // «Reportes por salir»: envíos programados a ≤ 3 días y lo que les falta (solo avisa).
            'alertasProgramadas' => app(\App\Support\Captura\AlertasProgramadas::class)->calcular(now(), $clienteActivo?->id),
        ]);
    }
}
