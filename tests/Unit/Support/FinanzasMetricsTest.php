<?php

namespace Tests\Unit\Support;

use App\Models\Cliente;
use App\Models\Finanza;
use App\Models\Servicio;
use App\Support\FinanzasMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * FinanzasMetrics is a static class over Eloquent queries (Finanza, Servicio),
 * so these tests hit the real (dedicated) testing database via
 * RefreshDatabase, matching the DB-backed convention the Feature tests in
 * this repo already use — there's no tests/Unit/Support precedent yet and no
 * mocking layer for Eloquent models here.
 */
class FinanzasMetricsTest extends TestCase
{
    use RefreshDatabase;

    private function finanza(array $overrides = []): Finanza
    {
        $cliente = $overrides['cliente_id'] ?? null;
        unset($overrides['cliente_id']);

        return Finanza::create(array_merge([
            'cliente_id' => $cliente ?? Cliente::factory()->create()->id,
            'servicio_id' => null,
            'concepto' => 'Concepto de prueba',
            'tipo' => 'ingreso',
            'monto' => 1000,
            'estado' => 'pagado',
            'fecha_emision' => now()->toDateString(),
            'mes' => now()->month,
            'anio' => now()->year,
        ], $overrides));
    }

    // --- mrr() ---------------------------------------------------------

    public function test_mrr_sums_only_active_servicios(): void
    {
        $cliente = Cliente::factory()->create();

        Servicio::factory()->create(['cliente_id' => $cliente->id, 'estado' => 'activo', 'precio_mensual' => 5000]);
        Servicio::factory()->create(['cliente_id' => $cliente->id, 'estado' => 'activo', 'precio_mensual' => 7500]);
        Servicio::factory()->create(['cliente_id' => $cliente->id, 'estado' => 'pausado', 'precio_mensual' => 20000]);
        Servicio::factory()->create(['cliente_id' => $cliente->id, 'estado' => 'cancelado', 'precio_mensual' => 30000]);

        $this->assertSame(12500.0, FinanzasMetrics::mrr());
    }

    public function test_mrr_is_zero_when_no_active_servicios(): void
    {
        $cliente = Cliente::factory()->create();
        Servicio::factory()->create(['cliente_id' => $cliente->id, 'estado' => 'pausado', 'precio_mensual' => 9000]);

        $this->assertSame(0.0, FinanzasMetrics::mrr());
    }

    // --- periodoActual() -------------------------------------------------

    /**
     * The exact behavior the FinanzasMetrics extraction fixed: the old
     * FinanzasController picked "whichever (mes, anio) pair is newest in the
     * table" as the current period, which silently diverges from the real
     * calendar month whenever data entry lags. periodoActual($now) must
     * anchor strictly on $now's month/year and ignore every other period,
     * regardless of insertion order or which period has more/newer rows.
     */
    public function test_periodo_actual_only_aggregates_rows_matching_now_ignoring_other_periods(): void
    {
        $now = Carbon::create(2026, 8, 15);
        $otro = Carbon::create(2026, 5, 1); // an earlier month with MORE rows, inserted AFTER the current-month rows

        $cliente = Cliente::factory()->create();

        // Current month (2026-08): what SHOULD be aggregated.
        $this->finanza(['cliente_id' => $cliente->id, 'tipo' => 'ingreso', 'estado' => 'pagado', 'monto' => 1000, 'mes' => $now->month, 'anio' => $now->year]);
        $this->finanza(['cliente_id' => $cliente->id, 'tipo' => 'ingreso', 'estado' => 'pagado', 'monto' => 2000, 'mes' => $now->month, 'anio' => $now->year]);
        $this->finanza(['cliente_id' => $cliente->id, 'tipo' => 'ingreso', 'estado' => 'pendiente', 'monto' => 500, 'mes' => $now->month, 'anio' => $now->year]);
        $this->finanza(['cliente_id' => $cliente->id, 'tipo' => 'ingreso', 'estado' => 'vencido', 'monto' => 300, 'mes' => $now->month, 'anio' => $now->year]);
        $this->finanza(['cliente_id' => $cliente->id, 'tipo' => 'gasto', 'estado' => 'pagado', 'monto' => 900, 'mes' => $now->month, 'anio' => $now->year]);

        // A different period with more rows and a larger total, inserted last
        // (highest ids) — under the old "newest period in table" logic this
        // period would have won instead of the real current month.
        $this->finanza(['cliente_id' => $cliente->id, 'tipo' => 'ingreso', 'estado' => 'pagado', 'monto' => 999999, 'mes' => $otro->month, 'anio' => $otro->year]);
        $this->finanza(['cliente_id' => $cliente->id, 'tipo' => 'ingreso', 'estado' => 'pendiente', 'monto' => 888888, 'mes' => $otro->month, 'anio' => $otro->year]);
        $this->finanza(['cliente_id' => $cliente->id, 'tipo' => 'gasto', 'estado' => 'pagado', 'monto' => 777777, 'mes' => $otro->month, 'anio' => $otro->year]);

        // Same month number, different year — must also be excluded.
        $this->finanza(['cliente_id' => $cliente->id, 'tipo' => 'ingreso', 'estado' => 'pagado', 'monto' => 654321, 'mes' => $now->month, 'anio' => $now->year - 1]);

        $periodo = FinanzasMetrics::periodoActual($now);

        $this->assertSame(3000.0, $periodo['cobrado']); // 1000 + 2000, pagado ingreso only
        $this->assertSame(800.0, $periodo['pendiente']); // 500 + 300, pendiente + vencido ingreso
        $this->assertSame(900.0, $periodo['gastos']);
        $this->assertSame(3000.0 - 900.0, $periodo['utilidad']);
        $this->assertSame(2, $periodo['facturas_pagadas']);
        $this->assertSame(2, $periodo['facturas_pendientes']);
    }

    public function test_periodo_actual_is_all_zero_when_no_rows_match_the_period(): void
    {
        $now = Carbon::create(2026, 8, 15);
        $cliente = Cliente::factory()->create();

        $this->finanza(['cliente_id' => $cliente->id, 'tipo' => 'ingreso', 'estado' => 'pagado', 'monto' => 500, 'mes' => 1, 'anio' => 2025]);

        $periodo = FinanzasMetrics::periodoActual($now);

        $this->assertSame(0.0, $periodo['cobrado']);
        $this->assertSame(0.0, $periodo['pendiente']);
        $this->assertSame(0.0, $periodo['gastos']);
        $this->assertSame(0.0, $periodo['utilidad']);
        $this->assertSame(0, $periodo['facturas_pagadas']);
        $this->assertSame(0, $periodo['facturas_pendientes']);
    }

    // --- revenueData() -----------------------------------------------------

    public function test_revenue_data_returns_six_months_oldest_first_newest_last(): void
    {
        $now = Carbon::create(2026, 8, 15);

        $data = FinanzasMetrics::revenueData($now);

        $this->assertCount(6, $data);
        $this->assertSame('2026-03', $data[0]['periodo']);
        $this->assertSame('Mar', $data[0]['month']);
        $this->assertSame('2026-08', $data[5]['periodo']);
        $this->assertSame('Ago', $data[5]['month']);

        $expectedOrder = ['2026-03', '2026-04', '2026-05', '2026-06', '2026-07', '2026-08'];
        $this->assertSame($expectedOrder, array_column($data, 'periodo'));
    }

    public function test_revenue_data_zero_fills_months_with_no_finanzas(): void
    {
        $now = Carbon::create(2026, 8, 15);

        $data = FinanzasMetrics::revenueData($now);

        $this->assertCount(6, $data);
        foreach ($data as $mes) {
            $this->assertSame(0.0, $mes['income']);
            $this->assertSame(0.0, $mes['expense']);
            $this->assertSame(0.0, $mes['utilidad']);
        }
    }

    public function test_revenue_data_sums_income_expense_and_utilidad_for_a_populated_month(): void
    {
        $now = Carbon::create(2026, 8, 15);
        $cliente = Cliente::factory()->create();

        // June 2026 (within the 6-month window) gets data; other months stay empty.
        $this->finanza(['cliente_id' => $cliente->id, 'tipo' => 'ingreso', 'estado' => 'pagado', 'monto' => 4000, 'mes' => 6, 'anio' => 2026]);
        $this->finanza(['cliente_id' => $cliente->id, 'tipo' => 'ingreso', 'estado' => 'pendiente', 'monto' => 1000, 'mes' => 6, 'anio' => 2026]);
        $this->finanza(['cliente_id' => $cliente->id, 'tipo' => 'gasto', 'estado' => 'pagado', 'monto' => 2000, 'mes' => 6, 'anio' => 2026]);

        $data = FinanzasMetrics::revenueData($now);
        $junio = collect($data)->firstWhere('periodo', '2026-06');

        $this->assertNotNull($junio);
        $this->assertSame(5000.0, $junio['income']); // both ingreso rows regardless of estado
        $this->assertSame(2000.0, $junio['expense']);
        $this->assertSame(3000.0, $junio['utilidad']);

        // Every other month in the window stays zero-filled.
        foreach ($data as $mes) {
            if ($mes['periodo'] === '2026-06') {
                continue;
            }
            $this->assertSame(0.0, $mes['income']);
            $this->assertSame(0.0, $mes['expense']);
            $this->assertSame(0.0, $mes['utilidad']);
        }
    }

    // --- variacionPorcentual() / tendenciaPendiente() ------------------------

    public function test_variacion_porcentual_calcula_contra_la_base_anterior(): void
    {
        $this->assertSame(50.0, FinanzasMetrics::variacionPorcentual(150, 100));
        $this->assertSame(-25.0, FinanzasMetrics::variacionPorcentual(75, 100));
    }

    public function test_variacion_porcentual_es_null_si_la_base_es_cero(): void
    {
        $this->assertNull(FinanzasMetrics::variacionPorcentual(500, 0));
        $this->assertNull(FinanzasMetrics::variacionPorcentual(0, 0));
    }

    public function test_tendencia_pendiente_compara_contra_el_mes_calendario_anterior(): void
    {
        $now = Carbon::create(2026, 8, 31); // fin de mes: no debe desbordar al restar un mes
        $cliente = Cliente::factory()->create();

        $this->finanza(['cliente_id' => $cliente->id, 'estado' => 'pendiente', 'monto' => 1000, 'mes' => 7, 'anio' => 2026]);
        $this->finanza(['cliente_id' => $cliente->id, 'estado' => 'vencido', 'monto' => 500, 'mes' => 8, 'anio' => 2026]);
        $this->finanza(['cliente_id' => $cliente->id, 'estado' => 'pendiente', 'monto' => 1000, 'mes' => 8, 'anio' => 2026]);

        $this->assertSame(50.0, FinanzasMetrics::tendenciaPendiente($now)); // 1500 vs 1000
    }

    public function test_tendencia_pendiente_es_null_si_el_mes_anterior_no_tenia_pendiente(): void
    {
        $now = Carbon::create(2026, 8, 15);
        $cliente = Cliente::factory()->create();

        $this->finanza(['cliente_id' => $cliente->id, 'estado' => 'pendiente', 'monto' => 1000, 'mes' => 8, 'anio' => 2026]);
        $this->finanza(['cliente_id' => $cliente->id, 'estado' => 'pagado', 'monto' => 700, 'mes' => 7, 'anio' => 2026]); // pagado: no cuenta como pendiente

        $this->assertNull(FinanzasMetrics::tendenciaPendiente($now));
    }

    public function test_revenue_data_excludes_finanzas_outside_the_six_month_window(): void
    {
        $now = Carbon::create(2026, 8, 15);
        $cliente = Cliente::factory()->create();

        // February 2026 is one month before the window (window starts March 2026).
        $this->finanza(['cliente_id' => $cliente->id, 'tipo' => 'ingreso', 'estado' => 'pagado', 'monto' => 10000, 'mes' => 2, 'anio' => 2026]);

        $data = FinanzasMetrics::revenueData($now);

        foreach ($data as $mes) {
            $this->assertSame(0.0, $mes['income']);
        }
    }
}
