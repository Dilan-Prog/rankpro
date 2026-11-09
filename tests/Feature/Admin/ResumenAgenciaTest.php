<?php

namespace Tests\Feature\Admin;

use App\Models\AdsCampana;
use App\Models\AdsMetrica;
use App\Models\Cliente;
use App\Models\Finanza;
use App\Models\Servicio;
use App\Models\User;
use App\Support\ResumenAgencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Regresiones de las alertas/KPI compartidos por Dashboard e Inicio. */
class ResumenAgenciaTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function mensajes(): array
    {
        return array_column(ResumenAgencia::alertas(now()), 'msg');
    }

    private function vencido(Cliente $cliente, string $tipo = 'ingreso'): void
    {
        Finanza::create([
            'cliente_id' => $cliente->id, 'concepto' => 'Pago', 'tipo' => $tipo, 'monto' => 1000,
            'estado' => 'vencido', 'fecha_vencimiento' => now()->subDays(2), 'mes' => now()->month, 'anio' => now()->year,
        ]);
    }

    public function test_el_contrato_que_vence_hoy_y_manana_aparecen_con_dias_correctos(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 15:30'));
        Cliente::factory()->create(['empresa' => null, 'nombre' => 'Hoy SA', 'estado' => 'activo', 'fecha_renovacion_contrato' => '2026-11-02']);
        Cliente::factory()->create(['empresa' => null, 'nombre' => 'Manana SA', 'estado' => 'activo', 'fecha_renovacion_contrato' => '2026-11-03']);
        Cliente::factory()->create(['empresa' => null, 'nombre' => 'Diez SA', 'estado' => 'activo', 'fecha_renovacion_contrato' => '2026-11-12']);

        $mensajes = $this->mensajes();

        $this->assertContains('Contrato de Hoy SA vence hoy', $mensajes);
        $this->assertContains('Contrato de Manana SA vence mañana', $mensajes);
        $this->assertContains('Contrato de Diez SA vence en 10 días', $mensajes);
    }

    public function test_dashboard_calcula_dias_entre_fechas_no_entre_horas(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 15:30'));
        Cliente::factory()->create(['estado' => 'activo', 'fecha_renovacion_contrato' => '2026-11-03']);

        $dias = $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))
            ->viewData('contractsExpiring')[0]['days'];

        $this->assertSame(1, $dias);
    }

    public function test_un_gasto_vencido_no_sale_como_pago_vencido_de_cliente(): void
    {
        $cliente = Cliente::factory()->create(['empresa' => null, 'nombre' => 'Cliente Gasto']);
        $this->vencido($cliente, 'gasto');

        $this->assertSame([], $this->mensajes());
    }

    public function test_cinco_renovaciones_no_desplazan_los_pagos_vencidos(): void
    {
        foreach (range(1, 5) as $i) {
            Cliente::factory()->create(['empresa' => null, 'nombre' => "Renueva {$i}", 'estado' => 'activo', 'fecha_renovacion_contrato' => now()->addDays($i)]);
        }
        $this->vencido(Cliente::factory()->create(['empresa' => null, 'nombre' => 'Moroso SA']));

        $alertas = $this->mensajes();

        $this->assertCount(5, $alertas);
        $this->assertTrue(collect($alertas)->contains(fn ($m) => str_contains($m, 'Pago vencido: Moroso SA')));
    }

    public function test_el_roas_de_este_mes_usa_las_metricas_del_mes_y_no_el_promedio_historico(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-15'));
        $cliente = Cliente::factory()->create(['empresa' => null, 'nombre' => 'Ads SA']);
        $campana = AdsCampana::factory()->create(['cliente_id' => $cliente->id, 'servicio_id' => Servicio::factory()->create(['cliente_id' => $cliente->id])->id, 'estado' => 'activa', 'nombre' => 'Camp']);
        AdsMetrica::factory()->create(['ads_campana_id' => $campana->id, 'cliente_id' => $cliente->id, 'mes' => 11, 'anio' => 2026, 'roas' => 2.5]);
        AdsMetrica::factory()->create(['ads_campana_id' => $campana->id, 'cliente_id' => $cliente->id, 'mes' => 8, 'anio' => 2026, 'roas' => 9.5]);

        $this->assertContains('Ads SA Camp alcanzó ROAS de 2.5x este mes', $this->mensajes());
    }

    public function test_los_montos_menores_a_mil_no_se_muestran_como_cero_k(): void
    {
        $compacto = new \ReflectionMethod(ResumenAgencia::class, 'compacto');
        $compacto->setAccessible(true);

        $this->assertSame('450', $compacto->invoke(null, 450.0));
        $this->assertSame('500', $compacto->invoke(null, 500.0));
        $this->assertSame('12K', $compacto->invoke(null, 12000.0));
    }

    public function test_el_rango_de_la_grafica_incluye_ambos_anos_al_cruzar(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-02-10'));
        $user = User::factory()->create();

        $this->assertSame('Sep 2026 — Feb 2027', $this->actingAs($user)->get(route('admin.dashboard'))->viewData('chartRange'));

        Carbon::setTestNow(Carbon::parse('2026-11-10'));
        $this->assertSame('Jun — Nov 2026', $this->actingAs($user)->get(route('admin.dashboard'))->viewData('chartRange'));
    }
}
