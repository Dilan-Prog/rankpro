<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Finanza;
use App\Models\Servicio;
use App\Support\FinanzasMetrics;
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

        $finanzasIngreso = Finanza::where('tipo', 'ingreso')->get();
        $carteraBuckets = $this->carteraBuckets($finanzasIngreso);
        $mrrPorCliente = $this->mrrPorCliente();

        $facturas = Finanza::with('cliente')
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Finanza $f) => $this->toRow($f));

        $ingresos6m = (float) array_sum(array_column($revenueData, 'income'));

        return view('admin.finanzas.index', [
            'pageTitle' => 'Finanzas',
            'mrr' => FinanzasMetrics::mrr(),
            'cobrado' => $periodo['cobrado'],
            'pendiente' => $periodo['pendiente'],
            'gastos' => $periodo['gastos'],
            'utilidad' => $periodo['utilidad'],
            'facturasPendientes' => $periodo['facturas_pendientes'],
            'facturasPagadas' => $periodo['facturas_pagadas'],
            'ingresos6m' => $ingresos6m,
            'ticketPromedio' => $mrrPorCliente->count() ? $mrrPorCliente->avg('mrr') : 0.0,
            'revenueData' => $revenueData,
            'carteraBuckets' => $carteraBuckets,
            'mrrPorCliente' => $mrrPorCliente,
            'facturas' => $facturas,
            'clientes' => Cliente::orderBy('nombre')->get(['id', 'nombre']),
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

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->integer('cliente_id'));
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->string('estado'));
        }
        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('concepto', 'like', "%{$search}%")
                    ->orWhereHas('cliente', fn ($c) => $c->where('nombre', 'like', "%{$search}%"));
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
                    $f->cliente?->nombre ?? '—',
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

    private function mrrPorCliente()
    {
        return Servicio::where('estado', 'activo')
            ->with('cliente:id,nombre')
            ->get()
            ->groupBy('cliente_id')
            ->map(fn ($servicios) => [
                'cliente' => $servicios->first()->cliente?->nombre ?? '—',
                'mrr' => (float) $servicios->sum('precio_mensual'),
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
            'cliente' => $f->cliente?->nombre ?? '—',
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
        return $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'servicio_id' => ['nullable', 'integer', 'exists:servicios,id'],
            'concepto' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'in:ingreso,gasto'],
            'monto' => ['required', 'numeric', 'min:0'],
            'estado' => ['required', 'in:pagado,pendiente,vencido'],
            'fecha_emision' => ['nullable', 'date'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'fecha_pago' => ['nullable', 'date'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
