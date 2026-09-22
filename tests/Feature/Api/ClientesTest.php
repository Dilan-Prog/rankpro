<?php

namespace Tests\Feature\Api;

use App\Models\Cliente;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientesTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requiere_token(): void
    {
        $this->getJson('/api/v1/clientes')->assertUnauthorized();
    }

    public function test_index_lista_clientes(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        Cliente::factory()->count(3)->create();

        $this->getJson('/api/v1/clientes')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_show_devuelve_el_cliente(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $cliente = Cliente::factory()->create();

        $this->getJson("/api/v1/clientes/{$cliente->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $cliente->id);
    }

    public function test_store_crea_cliente(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);

        $response = $this->postJson('/api/v1/clientes', [
            'nombre' => 'Cliente API',
            'estado' => 'activo',
        ]);

        $response->assertCreated()->assertJsonPath('data.nombre', 'Cliente API');
        $this->assertDatabaseHas('clientes', ['nombre' => 'Cliente API']);
    }

    public function test_update_actualiza_cliente(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $cliente = Cliente::factory()->create(['nombre' => 'Viejo']);

        $this->putJson("/api/v1/clientes/{$cliente->id}", [
            'nombre' => 'Nuevo',
            'estado' => 'activo',
        ])->assertOk()->assertJsonPath('data.nombre', 'Nuevo');
    }

    public function test_destroy_elimina_cliente(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $cliente = Cliente::factory()->create();

        $this->deleteJson("/api/v1/clientes/{$cliente->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);
        $this->assertSoftDeleted('clientes', ['id' => $cliente->id]);
    }

    public function test_servicios_anidado_filtra_por_cliente(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $cliente = Cliente::factory()->create();
        $otro = Cliente::factory()->create();
        Servicio::factory()->create(['cliente_id' => $cliente->id]);
        Servicio::factory()->create(['cliente_id' => $otro->id]);

        $this->getJson("/api/v1/clientes/{$cliente->id}/servicios")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_token_regenera_y_devuelve_api_token_una_vez(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $cliente = Cliente::factory()->create();

        $response = $this->postJson("/api/v1/clientes/{$cliente->id}/token");

        $response->assertOk();
        $this->assertNotEmpty($response->json('data.api_token'));
        $this->assertStringStartsWith('rp_live_', $response->json('data.api_token'));

        // El token no se filtra en ninguna otra respuesta (show, index, etc).
        $this->getJson("/api/v1/clientes/{$cliente->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.api_token');
    }

    public function test_escritura_prohibida_con_habilidad_de_solo_lectura(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['clientes:leer']);

        $this->postJson('/api/v1/clientes', ['nombre' => 'X', 'estado' => 'activo'])
            ->assertForbidden()
            ->assertJsonPath('habilidad_requerida', 'clientes:escribir');
    }
}
