<?php

namespace Tests\Feature\Api;

use App\Models\AdsCampana;
use App\Models\AdsGrupo;
use App\Models\Cliente;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdsTest extends TestCase
{
    use RefreshDatabase;

    private function campana(): AdsCampana
    {
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'google_ads']);

        return AdsCampana::create([
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña API test',
            'plataforma' => 'google_ads',
            'objetivo' => 'leads',
            'presupuesto_mensual' => 5000,
            'estado' => 'activa',
            'fase_actual' => 'briefing',
            'ciclo_actual' => 1,
        ]);
    }

    public function test_index_requiere_habilidad_ads(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['clientes:leer']);

        $this->getJson('/api/v1/ads/campanas')->assertStatus(403);
    }

    public function test_index_lista_campanas(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['ads:leer']);
        $this->campana();

        $this->getJson('/api/v1/ads/campanas')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_store_crea_campana_con_filas_de_fase(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['ads:escribir']);
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'google_ads']);

        $response = $this->postJson('/api/v1/ads/campanas', [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña nueva',
            'plataforma' => 'google_ads',
            'objetivo' => 'leads',
            'presupuesto_mensual' => 3000,
        ])->assertStatus(201);

        $campana = AdsCampana::latest('id')->first();
        $this->assertEquals('Campaña nueva', $campana->nombre);
        $this->assertEquals(1, $campana->briefings()->count());
        $response->assertJsonPath('data.fase_actual', 'briefing');
    }

    public function test_store_requiere_habilidad_escribir(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['ads:leer']);
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'google_ads']);

        $this->postJson('/api/v1/ads/campanas', [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña nueva',
            'plataforma' => 'google_ads',
            'objetivo' => 'leads',
            'presupuesto_mensual' => 3000,
        ])->assertStatus(403);
    }

    public function test_show_incluye_relaciones_pedidas(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['ads:leer']);
        $campana = $this->campana();
        AdsGrupo::create(['ads_campana_id' => $campana->id, 'nombre' => 'Grupo 1', 'estado' => 'activo', 'presupuesto' => 0]);

        $this->getJson("/api/v1/ads/campanas/{$campana->id}?incluir=grupos")
            ->assertOk()
            ->assertJsonCount(1, 'data.grupos');
    }

    public function test_update_y_destroy(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['ads:escribir']);
        $campana = $this->campana();

        $this->putJson("/api/v1/ads/campanas/{$campana->id}", [
            'cliente_id' => $campana->cliente_id,
            'servicio_id' => $campana->servicio_id,
            'nombre' => 'Renombrada',
            'plataforma' => 'google_ads',
            'objetivo' => 'ventas',
            'presupuesto_mensual' => 7000,
            'estado' => 'pausada',
        ])->assertOk()->assertJsonPath('data.nombre', 'Renombrada');

        $this->deleteJson("/api/v1/ads/campanas/{$campana->id}")->assertOk()->assertJsonPath('data.deleted', true);
        $this->assertSoftDeleted('ads_campanas', ['id' => $campana->id]);
    }

    public function test_grupos_keywords_creativos_metricas_optimizaciones_crud(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['ads:escribir']);
        $campana = $this->campana();

        $grupo = $this->postJson("/api/v1/ads/campanas/{$campana->id}/grupos", [
            'nombre' => 'Grupo A',
            'estado' => 'activo',
        ])->assertStatus(201)->json('data');

        $this->putJson("/api/v1/ads/grupos/{$grupo['id']}", ['nombre' => 'Grupo A editado', 'estado' => 'activo'])->assertOk();

        $keyword = $this->postJson("/api/v1/ads/grupos/{$grupo['id']}/keywords", ['keyword' => 'zapatos rojos'])->assertStatus(201)->json('data');
        $this->putJson("/api/v1/ads/grupos/keywords/{$keyword['id']}", ['keyword' => 'zapatos azules'])->assertOk();
        $this->deleteJson("/api/v1/ads/grupos/keywords/{$keyword['id']}")->assertOk();

        $columna = $this->postJson("/api/v1/ads/grupos/{$grupo['id']}/columnas", ['nombre' => 'Notas'])->assertStatus(201)->json('data');
        $this->deleteJson("/api/v1/ads/grupos/columnas/{$columna['id']}")->assertOk();

        $this->deleteJson("/api/v1/ads/grupos/{$grupo['id']}")->assertOk();

        $creativo = $this->postJson("/api/v1/ads/campanas/{$campana->id}/creativos", [
            'titulo' => 'Anuncio 1', 'tipo' => 'imagen', 'estado' => 'activo',
        ])->assertStatus(201)->json('data');
        $this->deleteJson("/api/v1/ads/creativos/{$creativo['id']}")->assertOk();

        $metrica = $this->postJson("/api/v1/ads/campanas/{$campana->id}/metricas", [
            'mes' => 1, 'anio' => 2026,
        ])->assertStatus(201)->json('data');
        $this->putJson("/api/v1/ads/metricas/{$metrica['id']}", ['mes' => 2, 'anio' => 2026])->assertOk();
        $this->deleteJson("/api/v1/ads/metricas/{$metrica['id']}")->assertOk();

        $optimizacion = $this->postJson("/api/v1/ads/campanas/{$campana->id}/optimizaciones", [
            'fecha' => '2026-01-01', 'tipo' => 'puja', 'descripcion' => 'Ajuste de puja',
        ])->assertStatus(201)->json('data');
        $this->deleteJson("/api/v1/ads/optimizaciones/{$optimizacion['id']}")->assertOk();
    }

    public function test_metricas_lote_crea_varias_filas(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['ads:escribir']);
        $campana = $this->campana();

        $this->postJson('/api/v1/ads/metricas/lote', [
            'metricas' => [
                ['ads_campana_id' => $campana->id, 'mes' => 1, 'anio' => 2026, 'impresiones' => 100],
                ['ads_campana_id' => $campana->id, 'mes' => 2, 'anio' => 2026, 'impresiones' => 200],
            ],
        ])->assertStatus(201)->assertJsonCount(2, 'data');

        $this->assertEquals(2, $campana->metricas()->count());
    }

    public function test_store_guarda_y_devuelve_ids_de_plataforma_y_moneda(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['ads:escribir']);
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'google_ads']);
        $base = [
            'cliente_id' => $cliente->id, 'servicio_id' => $servicio->id, 'nombre' => 'Con plataforma',
            'plataforma' => 'google_ads', 'objetivo' => 'leads', 'presupuesto_mensual' => 3000,
        ];

        $this->postJson('/api/v1/ads/campanas', $base + [
            'plataforma_cuenta_id' => '123-456-7890', 'plataforma_campana_id' => '987654321', 'moneda' => 'USD',
        ])->assertStatus(201)
            ->assertJsonPath('data.plataforma_cuenta_id', '123-456-7890')
            ->assertJsonPath('data.plataforma_campana_id', '987654321')
            ->assertJsonPath('data.moneda', 'USD');

        // Sin los campos nuevos el contrato anterior sigue valiendo: moneda MXN e IDs null.
        $this->postJson('/api/v1/ads/campanas', ['nombre' => 'Sin plataforma'] + $base)
            ->assertStatus(201)->assertJsonPath('data.moneda', 'MXN')->assertJsonPath('data.plataforma_cuenta_id', null);
    }

    public function test_moneda_invalida_es_422(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['ads:escribir']);
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'google_ads']);

        $this->postJson('/api/v1/ads/campanas', [
            'cliente_id' => $cliente->id, 'servicio_id' => $servicio->id, 'nombre' => 'X', 'plataforma' => 'google_ads',
            'objetivo' => 'leads', 'presupuesto_mensual' => 100, 'moneda' => 'EUR',
        ])->assertStatus(422)->assertJsonValidationErrors('moneda');
        $this->assertSame(0, AdsCampana::count());
    }

    public function test_update_y_show_devuelven_los_campos_de_plataforma(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['ads:leer', 'ads:escribir']);
        $campana = $this->campana();

        $this->putJson("/api/v1/ads/campanas/{$campana->id}", [
            'cliente_id' => $campana->cliente_id, 'servicio_id' => $campana->servicio_id, 'nombre' => $campana->nombre,
            'plataforma' => 'google_ads', 'objetivo' => 'leads', 'presupuesto_mensual' => 5000, 'estado' => 'activa',
            'plataforma_cuenta_id' => '555-111-2222', 'plataforma_campana_id' => 'C-42', 'moneda' => 'USD',
        ])->assertOk()->assertJsonPath('data.moneda', 'USD');

        $this->getJson("/api/v1/ads/campanas/{$campana->id}")->assertOk()
            ->assertJsonPath('data.plataforma_cuenta_id', '555-111-2222')
            ->assertJsonPath('data.plataforma_campana_id', 'C-42')
            ->assertJsonPath('data.moneda', 'USD');
    }
}
