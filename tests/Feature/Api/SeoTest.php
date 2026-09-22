<?php

namespace Tests\Feature\Api;

use App\Enums\FaseSeo;
use App\Models\Cliente;
use App\Models\SeoCampana;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    private function campana(): SeoCampana
    {
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'seo']);

        $campana = SeoCampana::create([
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña API test',
            'url_sitio' => 'https://ejemplo.com',
            'estado' => 'activa',
            'fase_actual' => FaseSeo::Auditoria->value,
            'ciclo_actual' => 1,
        ]);

        $campana->auditorias()->create(['ciclo' => 1, 'checklist' => []]);
        $campana->estrategias()->create(['ciclo' => 1, 'checklist' => []]);
        $campana->ejecuciones()->create(['ciclo' => 1, 'checklist' => []]);
        $campana->reportes()->create(['ciclo' => 1, 'checklist' => []]);

        return $campana;
    }

    public function test_index_requiere_habilidad_seo(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['keywords:leer']);

        $this->getJson('/api/v1/seo/campanas')->assertStatus(403)
            ->assertJsonPath('habilidad_requerida', 'seo:leer');
    }

    public function test_index_lista_campanas(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $this->campana();

        $this->getJson('/api/v1/seo/campanas')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_show_incluye_relaciones_pedidas(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $campana = $this->campana();
        $campana->posiciones()->create([
            'cliente_id' => $campana->cliente_id,
            'keyword' => 'agencia seo',
            'dispositivo' => 'mobile',
            'fecha_registro' => now()->toDateString(),
        ]);

        $this->getJson("/api/v1/seo/campanas/{$campana->id}?incluir=posiciones")
            ->assertOk()
            ->assertJsonCount(1, 'data.posiciones');
    }

    public function test_store_crea_campana_con_ciclo_1(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'seo']);

        $this->postJson('/api/v1/seo/campanas', [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Nueva campaña',
        ])->assertCreated()->assertJsonPath('data.fase_actual', 'auditoria');

        $campana = SeoCampana::latest('id')->first();
        $this->assertEquals(1, $campana->auditorias()->count());
    }

    public function test_store_requiere_habilidad_de_escritura(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['seo:leer']);

        $this->postJson('/api/v1/seo/campanas', [])->assertStatus(403);
    }

    public function test_update_campana(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $campana = $this->campana();

        $this->putJson("/api/v1/seo/campanas/{$campana->id}", [
            'cliente_id' => $campana->cliente_id,
            'servicio_id' => $campana->servicio_id,
            'nombre' => 'Renombrada',
            'estado' => 'pausada',
        ])->assertOk()->assertJsonPath('data.nombre', 'Renombrada');
    }

    public function test_destroy_campana(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $campana = $this->campana();

        $this->deleteJson("/api/v1/seo/campanas/{$campana->id}")->assertOk()->assertJsonPath('data.deleted', true);
        $this->assertSoftDeleted('seo_campanas', ['id' => $campana->id]);
    }

    public function test_posiciones_crud_anidado(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $campana = $this->campana();

        $respuesta = $this->postJson("/api/v1/seo/campanas/{$campana->id}/posiciones", [
            'keyword' => 'agencia seo cdmx',
            'dispositivo' => 'desktop',
            'posicion_actual' => 5,
        ])->assertCreated();

        $id = $respuesta->json('data.id');
        $this->getJson("/api/v1/seo/campanas/{$campana->id}/posiciones")->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson("/api/v1/seo/posiciones/{$id}")->assertOk();
    }

    public function test_backlinks_crud_anidado(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $campana = $this->campana();

        $respuesta = $this->postJson("/api/v1/seo/campanas/{$campana->id}/backlinks", [
            'url_origen' => 'https://otrositio.com/post',
            'url_destino' => 'https://ejemplo.com',
            'tipo' => 'dofollow',
            'estado' => 'activo',
        ])->assertCreated();

        $id = $respuesta->json('data.id');
        $this->deleteJson("/api/v1/seo/backlinks/{$id}")->assertOk();
    }

    public function test_contenido_crud_anidado(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $campana = $this->campana();

        $respuesta = $this->postJson("/api/v1/seo/campanas/{$campana->id}/contenido", [
            'titulo' => 'Guía de SEO local',
            'estado' => 'borrador',
        ])->assertCreated();

        $id = $respuesta->json('data.id');
        $this->putJson("/api/v1/seo/contenido/{$id}", ['titulo' => 'Guía actualizada', 'estado' => 'publicado'])
            ->assertOk()->assertJsonPath('data.titulo', 'Guía actualizada');
        $this->deleteJson("/api/v1/seo/contenido/{$id}")->assertOk();
    }

    public function test_onpage_crud_anidado(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $campana = $this->campana();

        $respuesta = $this->postJson("/api/v1/seo/campanas/{$campana->id}/onpage", [
            'url_pagina' => 'https://ejemplo.com/servicios',
            'accion' => 'Optimizar meta title',
            'estado' => 'en_progreso',
        ])->assertCreated();

        $id = $respuesta->json('data.id');
        $this->deleteJson("/api/v1/seo/onpage/{$id}")->assertOk();
    }

    public function test_metricas_mensuales_crud_anidado(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $campana = $this->campana();

        $respuesta = $this->postJson("/api/v1/seo/campanas/{$campana->id}/metricas-mensuales", [
            'mes' => 1,
            'anio' => 2026,
            'trafico_organico' => 1000,
        ])->assertCreated();

        $id = $respuesta->json('data.id');
        $this->deleteJson("/api/v1/seo/metricas-mensuales/{$id}")->assertOk();
    }
}
