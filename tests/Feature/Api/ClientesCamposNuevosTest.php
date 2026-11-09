<?php

namespace Tests\Feature\Api;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** La API v1 de clientes acepta y devuelve giro, sitio_web y ciudad sin romper a quien no los manda. */
class ClientesCamposNuevosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
    }

    public function test_store_sin_los_campos_nuevos_sigue_funcionando(): void
    {
        $this->postJson('/api/v1/clientes', ['nombre' => 'Sin Nuevos', 'estado' => 'activo'])
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Sin Nuevos')
            ->assertJsonPath('data.giro', null)
            ->assertJsonPath('data.sitio_web', null)
            ->assertJsonPath('data.ciudad', null);
    }

    public function test_store_con_los_campos_nuevos_los_guarda_y_los_devuelve(): void
    {
        $this->postJson('/api/v1/clientes', [
            'nombre' => 'Con Nuevos', 'estado' => 'activo', 'giro' => 'hotel', 'sitio_web' => 'https://hotel.mx', 'ciudad' => 'León',
        ])->assertCreated()
            ->assertJsonPath('data.giro', 'hotel')
            ->assertJsonPath('data.sitio_web', 'https://hotel.mx')
            ->assertJsonPath('data.ciudad', 'León');

        $this->assertDatabaseHas('clientes', ['nombre' => 'Con Nuevos', 'giro' => 'hotel', 'ciudad' => 'León']);
    }

    public function test_un_giro_fuera_del_catalogo_es_422(): void
    {
        $this->postJson('/api/v1/clientes', ['nombre' => 'X', 'estado' => 'activo', 'giro' => 'astronauta'])
            ->assertStatus(422)->assertJsonValidationErrors('giro');
    }

    public function test_update_con_y_sin_los_campos_nuevos(): void
    {
        $cliente = Cliente::factory()->create(['giro' => 'hotel', 'ciudad' => 'Puebla']);

        // Un PUT de un cliente que no conoce los campos nuevos no debe tocarlos ni fallar.
        $this->putJson("/api/v1/clientes/{$cliente->id}", ['nombre' => 'Renombrado', 'estado' => 'activo'])->assertOk();
        $this->assertSame('hotel', $cliente->fresh()->giro);

        $this->putJson("/api/v1/clientes/{$cliente->id}", ['nombre' => 'Renombrado', 'estado' => 'activo', 'giro' => 'ecommerce', 'sitio_web' => 'tienda.mx'])
            ->assertOk()->assertJsonPath('data.giro', 'ecommerce')->assertJsonPath('data.sitio_web', 'tienda.mx');
    }

    public function test_index_y_show_exponen_los_campos_nuevos(): void
    {
        $cliente = Cliente::factory()->create(['giro' => 'b2b_industrial', 'sitio_web' => 'https://planta.mx', 'ciudad' => 'Monterrey']);

        $this->getJson("/api/v1/clientes/{$cliente->id}")->assertOk()
            ->assertJsonPath('data.giro', 'b2b_industrial')->assertJsonPath('data.ciudad', 'Monterrey');
        $this->getJson('/api/v1/clientes')->assertOk()->assertJsonPath('data.0.sitio_web', 'https://planta.mx');
    }

    public function test_el_crm_web_tambien_guarda_los_campos_nuevos_al_editar(): void
    {
        $cliente = Cliente::factory()->create();

        $this->actingAs(User::factory()->create())->putJson(route('admin.clientes.update', $cliente), [
            'nombre' => $cliente->nombre, 'estado' => 'activo', 'giro' => 'hotel', 'sitio_web' => 'h.mx', 'ciudad' => 'Mérida',
        ])->assertOk()->assertJsonPath('giro', 'hotel')->assertJsonPath('perfil_completo', true);

        $this->assertSame('Mérida', $cliente->fresh()->ciudad);
    }
}
