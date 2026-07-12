<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Finanza;
use App\Models\Servicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanzasController extends Controller
{
    public function index(): View
    {
        $finanzas = Finanza::with('cliente')->get();

        $ultimoPeriodo = $finanzas->sortByDesc(fn (Finanza $f) => $f->anio * 100 + $f->mes)->first();
        $mesActual = $ultimoPeriodo?->mes;
        $anioActual = $ultimoPeriodo?->anio;

        $delMes = $finanzas->where('mes', $mesActual)->where('anio', $anioActual);

        $mrr = Servicio::where('estado', 'activo')->sum('precio_mensual');
        $cobrado = $delMes->where('tipo', 'ingreso')->where('estado', 'pagado')->sum('monto');
        $pendiente = $delMes->where('tipo', 'ingreso')->whereIn('estado', ['pendiente', 'vencido'])->sum('monto');
        $gastos = $delMes->where('tipo', 'gasto')->sum('monto');
        $utilidad = $cobrado - $gastos;

        $revenueData = $finanzas
            ->groupBy(fn (Finanza $f) => sprintf('%04d-%02d', $f->anio, $f->mes))
            ->map(function ($items, $periodo) {
                [$anio, $mes] = explode('-', $periodo);
                $meses = ['01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr', '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago', '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic'];

                return [
                    'periodo' => $periodo,
                    'month' => $meses[$mes] ?? $mes,
                    'income' => (float) $items->where('tipo', 'ingreso')->sum('monto'),
                    'expense' => (float) $items->where('tipo', 'gasto')->sum('monto'),
                ];
            })
            ->sortBy('periodo')
            ->values();

        $facturas = $finanzas
            ->sortByDesc(fn (Finanza $f) => $f->fecha_emision)
            ->values()
            ->map(fn (Finanza $f) => [
                'id' => $f->id,
                'cliente' => $f->cliente?->nombre ?? '—',
                'concepto' => $f->concepto,
                'tipo' => $f->tipo,
                'monto' => (float) $f->monto,
                'estado' => $f->estado->value,
                'fecha_vencimiento' => $f->fecha_vencimiento?->format('Y-m-d'),
                'fecha_pago' => $f->fecha_pago?->format('Y-m-d'),
            ]);

        return view('admin.finanzas.index', [
            'pageTitle' => 'Finanzas',
            'mrr' => (float) $mrr,
            'cobrado' => (float) $cobrado,
            'pendiente' => (float) $pendiente,
            'utilidad' => (float) $utilidad,
            'facturasPendientes' => $delMes->where('tipo', 'ingreso')->whereIn('estado', ['pendiente', 'vencido'])->count(),
            'facturasPagadas' => $delMes->where('tipo', 'ingreso')->where('estado', 'pagado')->count(),
            'revenueData' => $revenueData,
            'facturas' => $facturas,
        ]);
    }

    public function create(): View
    {
        return view('admin.finanzas.create', [
            'pageTitle' => 'Nuevo Registro Financiero',
            'clientes' => Cliente::with('servicios')->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Finanza::create($data);

        return redirect()->route('admin.finanzas.index')->with('status', 'Registro financiero creado correctamente.');
    }

    public function edit(Finanza $finanza): View
    {
        return view('admin.finanzas.edit', [
            'pageTitle' => 'Editar Registro Financiero',
            'finanza' => $finanza,
            'clientes' => Cliente::with('servicios')->orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, Finanza $finanza): RedirectResponse
    {
        $data = $this->validated($request);

        $finanza->update($data);

        return redirect()->route('admin.finanzas.index')->with('status', 'Registro financiero actualizado correctamente.');
    }

    public function destroy(Finanza $finanza): RedirectResponse
    {
        $finanza->delete();

        return redirect()->route('admin.finanzas.index')->with('status', 'Registro financiero eliminado.');
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
