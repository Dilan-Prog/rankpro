<?php

namespace Tests\Feature\Admin;

use App\Models\AdsCampana;
use App\Models\Cliente;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El presupuesto mensual del alta de campañas no se prellena en 0 ni acepta 0/vacío.
 */
class AdsPresupuestoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function alta(array $extra): \Illuminate\Testing\TestResponse
    {
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id]);

        return $this->actingAs(User::factory()->create())->postJson(route('admin.ads.store'), $extra + [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'QA presupuesto',
            'plataforma' => 'google_ads',
            'objetivo' => 'ventas',
        ]);
    }

    public function test_el_modal_no_prellena_el_presupuesto(): void
    {
        $html = $this->actingAs(User::factory()->create())->get(route('admin.ads.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/id="cf_presupuesto"[^>]*placeholder="Ej\. 5000"/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="cf_presupuesto"[^>]*value=/', $html);
    }

    public function test_cero_vacio_y_negativo_se_rechazan_al_crear(): void
    {
        foreach ([0, '0', '', null, -5] as $valor) {
            $this->alta(['presupuesto_mensual' => $valor])->assertStatus(422)->assertJsonValidationErrors('presupuesto_mensual');
        }
        $this->assertSame(0, AdsCampana::count());
    }

    public function test_un_monto_valido_se_guarda_tal_cual(): void
    {
        $this->alta(['presupuesto_mensual' => '1500.50'])->assertSuccessful();

        $this->assertSame('1500.50', AdsCampana::firstOrFail()->presupuesto_mensual);
    }
}
