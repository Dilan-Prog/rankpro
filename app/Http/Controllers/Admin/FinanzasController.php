<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Finanza;
use App\Models\Servicio;
use App\Support\ClienteActivo;
use App\Support\Finanzas\Conciliacion;
use App\Support\FinanzasMetrics;
use App\Support\Reglas\Finanzas as ReglasFinanzas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanzasController extends Controller
{
    public function index(): View
    {
        $now = now();
        $periodo = FinanzasMetrics::periodoActual($now);
        $revenueData = FinanzasMetrics::revenueData($now);

        // Cliente activo del menú: acota facturas, cartera y MRR por cliente; los KPI de arriba salen de
        // FinanzasMetrics (fuente única de la agencia) y no se tocan: el aviso lo aclara.
        $clienteActivo = ClienteActivo::modelo();

        $finanzasIngreso = Finanza::where('tipo', 'ingreso')
            ->when($clienteActivo, fn ($q) => $q->where('cliente_id', $clienteActivo->id))
            ->get();
        $carteraBuckets = $this->carteraBuckets($finanzasIngreso);
        $mrrPorCliente = $this->mrrPorCliente($clienteActivo?->id);

        $facturas = Finanza::with('cliente')
            ->when($clienteActivo, fn ($q) => $q->where('cliente_id', $clienteActivo->id))
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Finanza $f) => $this->toRow($f));

        $ingresos6m = (float) array_sum(array_column($revenueData, 'income'));

        // Conciliación solo lectura; con cliente activo se acota igual que la cartera.
        $conciliacion = new Conciliacion($now, $clienteActivo?->id);

        return view('admin.finanzas.index', [
            'pageTitle' => 'Finanzas',
            'clienteActivo' => $clienteActivo,
            'mrr' => FinanzasMetrics::mrr(),
            'ingresosProyecto' => FinanzasMetrics::ingresosPorProyecto(),
            'cobrado' => $periodo['cobrado'],
            'pendiente' => $periodo['pendiente'],
            'gastos' => $periodo['gastos'],
            'utilidad' => $periodo['utilidad'],
            'facturasPendientes' => $periodo['facturas_pendientes'],
            'facturasPagadas' => $periodo['facturas_pagadas'],
            'pendienteMes' => $periodo['pendiente_mes'],
            'vencidoAnterior' => $periodo['vencido_anterior'],
            'facturasVencidasAnterior' => $periodo['facturas_vencidas_anterior'],
            'conciliacionMeses' => $conciliacion->meses(),
            'revision' => $conciliacion->revision(),
            'ingresos6m' => $ingresos6m,
            'ticketPromedio' => $mrrPorCliente->count() ? $mrrPorCliente->avg('mrr') : 0.0,
            'revenueData' => $revenueData,
            'carteraBuckets' => $carteraBuckets,
            'mrrPorCliente' => $mrrPorCliente,
            'facturas' => $facturas,
            'clientes' => Cliente::ordenadoPorNombreComercial()->get(['id', 'nombre', 'empresa']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $finanza = Finanza::create($this->validated($request));

        return response()->json($this->toRow($finanza->fresh('cliente')), 201);
    }

    public function update(Request $request, Finanza $finanza): JsonResponse
    {
        $finanza->update($this->validated($request));

        return response()->json($this->toRow($finanza->fresh('cliente')));
    }

    public function destroy(Finanza $finanza): JsonResponse
    {
        $finanza->delete();

        return response()->json(['deleted' => true]);
    }

    /** CSV export of the Facturación table honoring the same filters active client-side (cliente_id/estado/search). */
    public function exportar(Request $request)
    {
        $query = Finanza::with('cliente')->orderByDesc('fecha_emision')->orderByDesc('id');

        // Lo que se descarga es lo que se ve: sin cliente_id explícito rige el cliente activo.
        ClienteActivo::aplicarA($request);

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->integer('cliente_id'));
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->string('estado'));
        }
        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                // Mismo criterio que el filtro de la tabla en finanzas.js: cliente, concepto y folio.
                $q->where('concepto', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT('F-', LPAD(id, 5, '0')) like ?", ["%{$search}%"])
                    ->orWhereHas('cliente', fn ($c) => $c->where('nombre', 'like', "%{$search}%")->orWhere('empresa', 'like', "%{$search}%"));
            });
        }

        $facturas = $query->get();
        $filename = 'finanzas-' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($facturas) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8
            fputcsv($out, ['Folio', 'Cliente', 'Concepto', 'Tipo', 'Monto', 'Estado', 'Vencimiento', 'Fecha de Pago']);

            foreach ($facturas as $f) {
                fputcsv($out, [
                    $this->folio($f),
                    $f->cliente?->nombre_comercial ?? '—',
                    $f->concepto,
                    $f->tipo,
                    $f->monto,
                    $f->estado->value,
                    $f->fecha_vencimiento?->format('Y-m-d'),
                    $f->fecha_pago?->format('Y-m-d'),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function carteraBuckets($finanzasIngreso): array
    {
        $total = (float) $finanzasIngreso->sum('monto');

        return $finanzasIngreso
            ->groupBy(fn (Finanza $f) => $f->estado->value)
            ->map(fn ($items, $estado) => [
                'estado' => $estado,
                'count' => $items->count(),
                'monto' => (float) $items->sum('monto'),
                'porcentaje' => $total > 0 ? round(((float) $items->sum('monto')) / $total * 100, 1) : 0.0,
            ])
            ->values()
            ->all();
    }

    private function mrrPorCliente(?int $clienteId = null)
    {
        return Servicio::mensual()
            ->when($clienteId, fn ($q) => $q->where('cliente_id', $clienteId))
            ->with('cliente:id,nombre,empresa')
            ->get()
            ->groupBy('cliente_id')
            ->map(fn ($servicios) => [
                'cliente' => $servicios->first()->cliente?->nombre_comercial ?? '—',
                'mrr' => FinanzasMetrics::mrrDeServicios($servicios),
            ])
            ->filter(fn ($r) => $r['mrr'] > 0)
            ->sortByDesc('mrr')
            ->values();
    }

    private function folio(Finanza $f): string
    {
        return 'F-' . str_pad((string) $f->id, 5, '0', STR_PAD_LEFT);
    }

    /** Shared shape for index()'s server-rendered rows and store()/update()'s AJAX responses. */
    private function toRow(Finanza $f): array
    {
        return [
            'id' => $f->id,
            'folio' => $this->folio($f),
            'cliente_id' => $f->cliente_id,
            'cliente' => $f->cliente?->nombre_comercial ?? '—',
            'servicio_id' => $f->servicio_id,
            'concepto' => $f->concepto,
            'tipo' => $f->tipo,
            'monto' => (float) $f->monto,
            'estado' => $f->estado->value,
            'fecha_emision' => $f->fecha_emision?->format('Y-m-d'),
            'fecha_vencimiento' => $f->fecha_vencimiento?->format('Y-m-d'),
            'fecha_pago' => $f->fecha_pago?->format('Y-m-d'),
            'mes' => $f->mes,
            'anio' => $f->anio,
            'notas' => $f->notas,
        ];
    }

    private function validated(Request $request): array
    {
        return ReglasFinanzas::validar($request);
    }
}
