<?php

namespace App\Support;

use App\Models\Finanza;
use App\Models\Servicio;
use Illuminate\Support\Carbon;

/**
 * Shared MRR/pendiente/revenueData calculations, used identically by
 * DashboardController and FinanzasController so their numbers never
 * diverge. Both anchor on the real calendar month via $now — not on
 * whatever (mes, anio) pair happens to be newest in the finanzas table,
 * which is what FinanzasController used before this class existed.
 */
class FinanzasMetrics
{
    private const MESES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    public static function mrr(): float
    {
        return (float) Servicio::where('estado', 'activo')->sum('precio_mensual');
    }

    /**
     * Uses the query builder (not a fetched Collection filtered in PHP) so
     * the 'estado' string comparisons run at the DB level — Finanza::estado
     * is cast to the EstadoFinanza enum on hydrated models, and PHP's `==`
     * never considers an enum instance equal to a plain string, so filtering
     * an already-fetched Collection by ->where('estado', 'pagado') silently
     * matches nothing.
     *
     * @return array{cobrado: float, pendiente: float, gastos: float, utilidad: float, facturas_pagadas: int, facturas_pendientes: int}
     */
    public static function periodoActual(Carbon $now): array
    {
        $delMes = fn () => Finanza::where('mes', $now->month)->where('anio', $now->year);

        $cobrado = (float) $delMes()->where('tipo', 'ingreso')->where('estado', 'pagado')->sum('monto');
        $pendiente = (float) $delMes()->where('tipo', 'ingreso')->whereIn('estado', ['pendiente', 'vencido'])->sum('monto');
        $gastos = (float) $delMes()->where('tipo', 'gasto')->sum('monto');

        return [
            'cobrado' => $cobrado,
            'pendiente' => $pendiente,
            'gastos' => $gastos,
            'utilidad' => $cobrado - $gastos,
            'facturas_pagadas' => $delMes()->where('tipo', 'ingreso')->where('estado', 'pagado')->count(),
            'facturas_pendientes' => $delMes()->where('tipo', 'ingreso')->whereIn('estado', ['pendiente', 'vencido'])->count(),
        ];
    }

    /** Last 6 calendar months of ingreso/gasto/utilidad totals, zero-filled where there's no data. */
    public static function revenueData(Carbon $now): array
    {
        // ->startOfMonth() before subtracting avoids Carbon's end-of-month
        // overflow (e.g. Aug 31 minus 4 months would land on "Apr 31", which
        // doesn't exist and silently rolls over to May 1 — duplicating May
        // and skipping April). Only year/month are read from these, so
        // clamping the day to 1 is safe.
        $periodos = collect(range(5, 0))->map(fn ($i) => $now->copy()->startOfMonth()->subMonths($i));

        $finanzas = Finanza::whereIn('anio', $periodos->pluck('year')->unique())->get()
            ->groupBy(fn (Finanza $f) => $f->anio . '-' . $f->mes);

        return $periodos->map(function (Carbon $periodo) use ($finanzas) {
            $items = $finanzas->get($periodo->year . '-' . $periodo->month, collect());
            $income = (float) $items->where('tipo', 'ingreso')->sum('monto');
            $expense = (float) $items->where('tipo', 'gasto')->sum('monto');

            return [
                'periodo' => sprintf('%04d-%02d', $periodo->year, $periodo->month),
                'month' => self::MESES[$periodo->month - 1],
                'income' => $income,
                'expense' => $expense,
                'utilidad' => $income - $expense,
            ];
        })->values()->all();
    }
}
