<?php

namespace Tests\Feature\Admin;

use App\Models\AdsCampana;
use App\Models\AdsMetrica;
use App\Models\Cliente;
use App\Models\Finanza;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect('/login');
    }

    public function test_dashboard_renders_kpi_links(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee(route('admin.finanzas.index'), false);
        $response->assertSee(route('admin.clientes.index'), false);
        $response->assertSee(route('admin.ads.index'), false);
    }

    public function test_top_roas_rows_carry_campaign_data_for_the_modal(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id]);
        $campana = AdsCampana::factory()->create([
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
        ]);
        AdsMetrica::factory()->create([
            'ads_campana_id' => $campana->id,
            'cliente_id' => $cliente->id,
            'roas' => 4.5,
        ]);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertOk();
        // The row's data-campana="..." blob is JSON rendered through Blade's
        // {{ }} escaping, so literal quotes become &quot; in the HTML output.
        $response->assertSee('&quot;id&quot;:' . $campana->id, false);
    }

    public function test_contracts_expiring_rows_link_to_client_show(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create([
            'estado' => 'activo',
            'fecha_renovacion_contrato' => now()->addDays(10),
        ]);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee(route('admin.clientes.show', $cliente), false);
    }

    private function pendienteDe(Cliente $cliente, int $mes, int $anio, float $monto): void
    {
        Finanza::create([
            'cliente_id' => $cliente->id,
            'servicio_id' => null,
            'concepto' => 'Pendiente de prueba',
            'tipo' => 'ingreso',
            'monto' => $monto,
            'estado' => 'pendiente',
            'fecha_emision' => now()->toDateString(),
            'mes' => $mes,
            'anio' => $anio,
        ]);
    }

    public function test_pagos_pendientes_muestra_tendencia_contra_el_mes_anterior(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $anterior = now()->startOfMonth()->subMonth();

        $this->pendienteDe($cliente, $anterior->month, $anterior->year, 1000);
        $this->pendienteDe($cliente, now()->month, now()->year, 1250);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('25% vs mes anterior');
    }

    public function test_sin_pendiente_el_mes_anterior_no_se_pinta_tendencia(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $this->pendienteDe($cliente, now()->month, now()->year, 1250);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('vs mes anterior');
    }

    public function test_exportar_reporte_downloads_pdf(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id]);
        $campana = AdsCampana::factory()->create([
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
        ]);
        AdsMetrica::factory()->create([
            'ads_campana_id' => $campana->id,
            'cliente_id' => $cliente->id,
            'roas' => 3.2,
        ]);

        $response = $this->actingAs($user)->get(route('admin.dashboard.exportar'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
