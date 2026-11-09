<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\AdsCampana;
use App\Models\Cliente;
use App\Models\SeoCampana;
use App\Support\Api\Respuesta;
use App\Support\FinanzasMetrics;

/** Mismos KPIs que Admin\DashboardController::index(), en JSON plano para consumo externo. */
class DashboardApiController extends ControladorApi
{
    public function resumen()
    {
        $now = now();
        $periodo = FinanzasMetrics::periodoActual($now);

        $campanasActivas = AdsCampana::where('estado', 'activa')->count()
            + SeoCampana::where('estado', 'activa')->count();

        return Respuesta::recurso([
            'clientes_activos' => Cliente::where('estado', 'activo')->count(),
            'clientes_total' => Cliente::count(),
            'mrr' => FinanzasMetrics::mrr(),
            'ingresos_por_proyecto' => FinanzasMetrics::ingresosPorProyecto()['total'],
            'cobrado_mes' => $periodo['cobrado'],
            // El contrato sigue siendo del mes; lo arrastrado de meses anteriores va en campos aparte.
            'pendiente_mes' => $periodo['pendiente_mes'],
            'vencido_meses_anteriores' => $periodo['vencido_anterior'],
            'gastos_mes' => $periodo['gastos'],
            'utilidad_mes' => $periodo['utilidad'],
            'facturas_pagadas_mes' => $periodo['facturas_pagadas'],
            'facturas_pendientes_mes' => $periodo['facturas_pendientes_mes'],
            'facturas_vencidas_meses_anteriores' => $periodo['facturas_vencidas_anterior'],
            'campanas_activas' => $campanasActivas,
            'revenue_6m' => FinanzasMetrics::revenueData($now),
        ]);
    }
}
