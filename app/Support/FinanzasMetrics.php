<?php

namespace App\Support;

use App\Enums\EstadoClienteServicio;
use App\Enums\TipoCobro;
use App\Models\Finanza;
use App\Models\Servicio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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

    /**
     * FUENTE ÚNICA del MRR: servicios activos de cobro mensual (Servicio::scopeMensual). Dashboard, Inicio,
     * Finanzas, Ads, SEO, CRM y la API lo leen de aquí o de mrrPorCliente()/mrrDeServicios(), nunca con su
     * propia suma. No excluye servicios con fecha_fin vencida: eso lo decide el usuario (se marcan "Vencido").
     */
    public static function mrr(): float
    {
        return (float) Servicio::mensual()->sum('precio_mensual');
    }

    /**
     * MRR por cliente en consulta agrupada (mismo scope que mrr()).
     *
     * @param  list<int>|null  $clienteIds  null = todos los clientes con MRR
     * @return Collection<int, float> cliente_id => MRR
     */
    public static function mrrPorCliente(?array $clienteIds = null): Collection
    {
        return Servicio::mensual()
            ->when($clienteIds !== null, fn ($q) => $q->whereIn('cliente_id', $clienteIds))
            ->groupBy('cliente_id')
            ->selectRaw('cliente_id, SUM(precio_mensual) as mrr')
            ->pluck('mrr', 'cliente_id')
            ->map(fn ($mrr) => (float) $mrr);
    }

    /** MRR de servicios ya cargados en memoria (p. ej. $cliente->servicios); misma regla que el scope. */
    public static function mrrDeServicios(iterable $servicios): float
    {
        return (float) collect($servicios)->filter(fn (Servicio $s) => $s->aportaMrr())->sum('precio_mensual');
    }

    /**
     * Ingresos por proyecto: total contratado de los servicios de pago único que no están cancelados
     * (activos y pausados cuentan; un cancelado ya no se cobrará). Es lo CONTRATADO, no lo cobrado
     * (eso es de Facturación), y nunca entra al MRR. Con `$clienteId` solo cuenta los de ese cliente
     * (misma regla, para que la ficha del cliente no calcule por su cuenta).
     *
     * @return array{total: float, proyectos: int}
     */
    public static function ingresosPorProyecto(?int $clienteId = null): array
    {
        $proyectos = fn () => Servicio::where('tipo_cobro', TipoCobro::Unico->value)
            ->when($clienteId !== null, fn ($q) => $q->where('cliente_id', $clienteId))
            ->where('estado', '!=', EstadoClienteServicio::Cancelado->value);

        return [
            'total' => (float) $proyectos()->sum('precio_mensual'),
            'proyectos' => $proyectos()->count(),
        ];
    }

    /**
     * Uses the query builder (not a fetched Collection filtered in PHP) so
     * the 'estado' string comparisons run at the DB level — Finanza::estado
     * is cast to the EstadoFinanza enum on hydrated models, and PHP's `==`
     * never considers an enum instance equal to a plain string, so filtering
     * an already-fetched Collection by ->where('estado', 'pagado') silently
     * matches nothing.
     *
     * "Por cobrar" (`pendiente`) = pendiente del mes + lo que sigue sin cobrarse de meses ANTERIORES: antes solo
     * miraba el mes calendario y una factura impaga de septiembre desaparecía del KPI en octubre. `cobrado` sigue
     * siendo por mes de la factura (no por fecha de pago): un pago de hoy por una factura vieja no suma aquí.
     *
     * @return array{cobrado: float, pendiente: float, pendiente_mes: float, vencido_anterior: float, facturas_vencidas_anterior: int, gastos: float, utilidad: float, facturas_pagadas: int, facturas_pendientes: int, facturas_pendientes_mes: int}
     */
    public static function periodoActual(Carbon $now): array
    {
        $delMes = fn () => Finanza::where('mes', $now->month)->where('anio', $now->year);
        // Solo meses ya cerrados: un registro de un mes futuro no es deuda todavía.
        $anteriores = fn () => Finanza::where('tipo', 'ingreso')->whereIn('estado', ['pendiente', 'vencido'])
            ->where(fn ($q) => $q->where('anio', '<', $now->year)
                ->orWhere(fn ($q2) => $q2->where('anio', $now->year)->where('mes', '<', $now->month)));

        $cobrado = (float) $delMes()->where('tipo', 'ingreso')->where('estado', 'pagado')->sum('monto');
        $pendienteMes = (float) $delMes()->where('tipo', 'ingreso')->whereIn('estado', ['pendiente', 'vencido'])->sum('monto');
        $vencidoAnterior = (float) $anteriores()->sum('monto');
        $gastos = (float) $delMes()->where('tipo', 'gasto')->sum('monto');
        $facturasPendientesMes = $delMes()->where('tipo', 'ingreso')->whereIn('estado', ['pendiente', 'vencido'])->count();
        $facturasVencidasAnterior = $anteriores()->count();

        return [
            'cobrado' => $cobrado,
            'pendiente' => $pendienteMes + $vencidoAnterior,
            'pendiente_mes' => $pendienteMes,
            'vencido_anterior' => $vencidoAnterior,
            'facturas_vencidas_anterior' => $facturasVencidasAnterior,
            'gastos' => $gastos,
            'utilidad' => $cobrado - $gastos,
            'facturas_pagadas' => $delMes()->where('tipo', 'ingreso')->where('estado', 'pagado')->count(),
            'facturas_pendientes' => $facturasPendientesMes + $facturasVencidasAnterior,
            'facturas_pendientes_mes' => $facturasPendientesMes,
        ];
    }

    /**
     * Variación porcentual contra el periodo anterior, para la flecha "vs mes
     * anterior" de las tarjetas KPI. Devuelve null cuando la base es 0 (o
     * negativa): dividir entre 0 daría "infinito" y un porcentaje sobre una
     * base inexistente sería una cifra inventada; mejor no pintar tendencia.
     */
    public static function variacionPorcentual(float $actual, float $anterior): ?float
    {
        if ($anterior <= 0.0) {
            return null;
        }

        return round((($actual - $anterior) / $anterior) * 100, 1);
    }

    /**
     * Tendencia de "pagos pendientes" contra el mes calendario anterior, con
     * la misma fórmula de periodoActual() para que no diverja de Finanzas. Compara solo el pendiente PROPIO
     * de cada mes (`pendiente_mes`): con el arrastre, el mes actual siempre contendría al anterior y la flecha mentiría.
     * Ojo: el pendiente del mes anterior se lee con el estado de HOY de esas
     * facturas (lo que sigue sin cobrarse de ese mes), no una foto de cómo
     * estaba entonces; no existe historial de cambios de estado.
     */
    public static function tendenciaPendiente(Carbon $now): ?float
    {
        // startOfMonth() antes de restar evita el desbordamiento de fin de mes (ver revenueData()).
        $anterior = self::periodoActual($now->copy()->startOfMonth()->subMonth());

        return self::variacionPorcentual(self::periodoActual($now)['pendiente_mes'], $anterior['pendiente_mes']);
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
