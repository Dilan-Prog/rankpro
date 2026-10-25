<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Captura\TableroCaptura;
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

        return view('admin.captura.index', [
            'pageTitle' => 'Pendientes de captura',
            'tablero' => $tablero->calcular($mes, $soloPendientes),
            'fuentes' => TableroCaptura::FUENTES,
            'mes' => $mes,
            'soloPendientes' => $soloPendientes,
        ]);
    }
}
