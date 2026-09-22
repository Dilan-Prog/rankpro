<?php

namespace Tests\Feature\Api;

use App\Models\Cliente;
use App\Models\Servicio;
use App\Models\ServicioEvento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServiciosTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lista_servicios(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        Servicio::factory()->count(2)->create();

        $this->getJson('/api/v1/servicios')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_store_crea_servicio_y_registra_evento(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $cliente = Cliente::factory()->create();

        $response = $this->postJson('/api/v1/servicios', [
            'cliente_id' => $cliente->id,
            'tipo' => 'seo',
            'nombre' => 'SEO mensual',
            'precio_mensual' => 5000,
            'estado' => 'activo',
        ]);

        $response->assertCreated()->assertJsonPath('data.nombre', 'SEO mensual');
        $servicioId = $response->json('data.id');
        $this->assertDatabaseHas('servicio_eventos', ['servicio_id' => $servicioId]);
    }

    public function test_update_registra_evento_de_cambio_de_estado(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $servicio = Servicio::factory()->create(['estado' => 'activo']);

        $this->putJson("/api/v1/servicios/{$servicio->id}", [
            'cliente_id' => $servicio->cliente_id,
            'tipo' => $servicio->tipo->value,
            'nombre' => $servicio->nombre,
            'precio_mensual' => $servicio->precio_mensual,
            'estado' => 'pausado',
        ])->assertOk()->assertJsonPath('data.estado', 'pausado');

        $this->assertDatabaseHas('servicio_eventos', [
            'servicio_id' => $servicio->id,
            'descripcion' => 'Estado cambiado a pausado.',
        ]);
    }

    public function test_destroy_elimina_servicio(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $servicio = Servicio::factory()->create();

        $this->deleteJson("/api/v1/servicios/{$servicio->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);
    }

    public function test_eventos_anidado_lista_bitacora_del_servicio(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $servicio = Servicio::factory()->create();
        ServicioEvento::create(['servicio_id' => $servicio->id, 'descripcion' => 'Evento de prueba', 'created_at' => now()]);

        $this->getJson("/api/v1/servicios/{$servicio->id}/eventos")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_escritura_prohibida_con_habilidad_de_solo_lectura(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['servicios:leer']);
        $cliente = Cliente::factory()->create();

        $this->postJson('/api/v1/servicios', [
            'cliente_id' => $cliente->id,
            'tipo' => 'seo',
            'nombre' => 'X',
            'precio_mensual' => 100,
            'estado' => 'activo',
        ])->assertForbidden();
    }
}
