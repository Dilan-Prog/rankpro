<?php

namespace Tests\Feature\Api;

use App\Models\AdsClic;
use App\Models\AdsConversion;
use App\Models\AdsEmbudoEtapa;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requiere_habilidad_conversiones(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['ads:leer']);

        $this->getJson('/api/v1/conversiones')->assertStatus(403);
    }

    public function test_store_clic_y_conversion_hace_match_por_visitor_id(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['conversiones:escribir']);
        $cliente = Cliente::factory()->create();

        $this->postJson('/api/v1/clics', [
            'cliente_id' => $cliente->id,
            'visitor_id' => 'visitante-1',
            'gclid' => 'gclid-123',
            'landing_url' => 'https://ejemplo.com/landing',
        ])->assertStatus(201);

        $this->assertEquals(1, AdsClic::count());

        $response = $this->postJson('/api/v1/conversiones', [
            'cliente_id' => $cliente->id,
            'visitor_id' => 'visitante-1',
            'tipo' => 'whatsapp',
            'valor' => 500,
        ])->assertStatus(201);

        $conversion = AdsConversion::first();
        $this->assertNotNull($conversion);
        $this->assertEquals('gclid-123', $conversion->gclid);
        $this->assertNotNull($conversion->ads_clic_id);
        $response->assertJsonPath('data.gclid', 'gclid-123');
    }

    public function test_index_filtra_por_cliente(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['conversiones:leer']);
        $clienteA = Cliente::factory()->create();
        $clienteB = Cliente::factory()->create();
        AdsConversion::create(['cliente_id' => $clienteA->id, 'visitor_id' => 'a', 'tipo' => 'whatsapp']);
        AdsConversion::create(['cliente_id' => $clienteB->id, 'visitor_id' => 'b', 'tipo' => 'whatsapp']);

        $this->getJson("/api/v1/conversiones?cliente_id={$clienteA->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_asignar_etapa_valida_que_pertenezca_al_cliente(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['conversiones:escribir']);
        $cliente = Cliente::factory()->create();
        $otroCliente = Cliente::factory()->create();
        $conversion = AdsConversion::create(['cliente_id' => $cliente->id, 'visitor_id' => 'a', 'tipo' => 'whatsapp']);
        $etapaOtroCliente = $otroCliente->embudoEtapas()->create(['nombre' => 'Etapa ajena', 'orden' => 1]);

        $this->postJson("/api/v1/conversiones/{$conversion->id}/etapa", [
            'ads_embudo_etapa_id' => $etapaOtroCliente->id,
        ])->assertStatus(422);

        $etapaPropia = $cliente->embudoEtapas()->create(['nombre' => 'Etapa propia', 'orden' => 1]);

        $this->postJson("/api/v1/conversiones/{$conversion->id}/etapa", [
            'ads_embudo_etapa_id' => $etapaPropia->id,
        ])->assertOk();

        $this->assertEquals($etapaPropia->id, $conversion->fresh()->ads_embudo_etapa_id);
    }

    public function test_update_conversion_solo_toca_valor_y_datos_personalizados(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['conversiones:escribir']);
        $cliente = Cliente::factory()->create();
        $conversion = AdsConversion::create(['cliente_id' => $cliente->id, 'visitor_id' => 'a', 'tipo' => 'whatsapp', 'valor' => 100]);

        $this->putJson("/api/v1/conversiones/{$conversion->id}", ['valor' => 250])
            ->assertOk()
            ->assertJsonPath('data.valor', '250.00');
    }

    public function test_columnas_crud(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['conversiones:escribir']);

        $columna = $this->postJson('/api/v1/conversiones/columnas', ['nombre' => 'Origen'])
            ->assertStatus(201)->json('data');

        $this->putJson("/api/v1/conversiones/columnas/{$columna['id']}", ['nombre' => 'Origen editado'])->assertOk();
        $this->deleteJson("/api/v1/conversiones/columnas/{$columna['id']}")->assertOk();
    }

    public function test_embudo_etapas_crud_por_cliente(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['conversiones:escribir']);
        $cliente = Cliente::factory()->create();

        $etapa = $this->postJson("/api/v1/clientes/{$cliente->id}/embudo/etapas", ['nombre' => 'Contacto'])
            ->assertStatus(201)->json('data');

        $this->getJson("/api/v1/clientes/{$cliente->id}/embudo/etapas")->assertOk()->assertJsonCount(1, 'data');

        $this->putJson("/api/v1/embudo/etapas/{$etapa['id']}", ['nombre' => 'Contacto editado'])->assertOk();
        $this->deleteJson("/api/v1/embudo/etapas/{$etapa['id']}")->assertOk();
    }
}
