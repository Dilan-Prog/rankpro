<?php

namespace Tests\Feature\Admin;

use App\Models\AdsCampana;
use App\Models\AdsMetrica;
use App\Models\Cliente;
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
