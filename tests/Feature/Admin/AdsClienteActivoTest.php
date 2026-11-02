<?php

namespace Tests\Feature\Admin;

use App\Models\AdsCampana;
use App\Models\Cliente;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** El índice de Ads respeta el cliente activo del menú (grupo Marca). */
class AdsClienteActivoTest extends TestCase
{
    use RefreshDatabase;

    private function campana(Cliente $cliente, string $nombre): AdsCampana
    {
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id]);

        return AdsCampana::create([
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => $nombre,
            'plataforma' => 'google_ads',
            'objetivo' => 'ventas',
            'presupuesto_mensual' => 5000,
            'estado' => 'activa',
            'fase_actual' => 'briefing',
            'ciclo_actual' => 1,
        ]);
    }

    public function test_sin_cliente_activo_muestra_campanas_de_todos(): void
    {
        $a = Cliente::factory()->create();
        $b = Cliente::factory()->create();
        $this->campana($a, 'Campana Alfa Uno');
        $this->campana($b, 'Campana Beta Uno');

        $this->actingAs(User::factory()->create())
            ->get(route('admin.ads.index'))
            ->assertOk()
            ->assertSee('Campana Alfa Uno')
            ->assertSee('Campana Beta Uno')
            ->assertSee('id="adsAccountRow"', false)
            ->assertDontSee('Ver todos los clientes');
    }

    public function test_con_cliente_activo_solo_muestra_sus_campanas(): void
    {
        $a = Cliente::factory()->create();
        $b = Cliente::factory()->create();
        $this->campana($a, 'Campana Alfa Uno');
        $this->campana($b, 'Campana Beta Uno');

        $this->actingAs(User::factory()->create())
            ->withSession(['cliente_activo_id' => $a->id])
            ->get(route('admin.ads.index'))
            ->assertOk()
            ->assertSee('Campana Alfa Uno')
            ->assertDontSee('Campana Beta Uno')
            ->assertSee('Ver todos los clientes')
            ->assertSee('data-cliente-activo="'.$a->id.'"', false)
            ->assertDontSee('id="adsAccountRow"', false);
    }

    public function test_con_cliente_activo_sin_campanas_muestra_estado_vacio(): void
    {
        $a = Cliente::factory()->create();
        $b = Cliente::factory()->create();
        $this->campana($b, 'Campana Beta Uno');

        $this->actingAs(User::factory()->create())
            ->withSession(['cliente_activo_id' => $a->id])
            ->get(route('admin.ads.index'))
            ->assertOk()
            ->assertDontSee('Campana Beta Uno')
            ->assertViewHas('campanas', fn ($c) => $c->isEmpty());
    }

    public function test_modal_de_nueva_campana_preselecciona_el_cliente_activo(): void
    {
        $a = Cliente::factory()->create(['nombre' => 'Cliente Preseleccionado']);
        Cliente::factory()->create(['nombre' => 'Cliente Otro']);

        $html = $this->actingAs(User::factory()->create())
            ->withSession(['cliente_activo_id' => $a->id])
            ->get(route('admin.ads.index'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<option value="'.$a->id.'"\s+selected[^>]*>Cliente Preseleccionado/', $html);
        $this->assertDoesNotMatchRegularExpression('/<option value="\d+"\s+selected[^>]*>Cliente Otro/', $html);
    }
}
