<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\SeoCampana;
use App\Models\SeoFaseAuditoria;
use App\Models\SeoOnPageAccion;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoOnPageAccionTest extends TestCase
{
    use RefreshDatabase;

    private function campana(array $overrides = []): SeoCampana
    {
        return SeoCampana::create(array_merge([
            'cliente_id' => Cliente::factory()->create()->id,
            'servicio_id' => Servicio::factory()->create()->id,
            'nombre' => 'Campaña SEO de Prueba',
            'url_sitio' => 'https://ejemplo.com',
            'estado' => 'activa',
            'fase_actual' => 'auditoria',
            'ciclo_actual' => 1,
            'fecha_inicio' => now()->subMonths(2),
        ], $overrides));
    }

    // --- store ---------------------------------------------------------

    public function test_admin_can_create_onpage_accion(): void
    {
        $user = User::factory()->create();
        $campana = $this->campana();

        $response = $this->actingAs($user)->postJson(route('admin.seo.onpage.store', $campana), [
            'url_pagina' => '/servicios/seo',
            'accion' => 'Optimizar title y meta description',
            'fecha' => '2026-08-15',
            'estado' => 'en_progreso',
        ]);

        $response->assertCreated();
        $response->assertJson([
            'seo_campana_id' => $campana->id,
            'url_pagina' => '/servicios/seo',
            'accion' => 'Optimizar title y meta description',
            'estado' => 'en_progreso',
        ]);

        $this->assertDatabaseHas('seo_onpage_acciones', [
            'seo_campana_id' => $campana->id,
            'url_pagina' => '/servicios/seo',
            'estado' => 'en_progreso',
        ]);
    }

    public function test_store_requires_url_pagina(): void
    {
        $user = User::factory()->create();
        $campana = $this->campana();

        $response = $this->actingAs($user)->postJson(route('admin.seo.onpage.store', $campana), [
            'accion' => 'Acción sin URL',
            'estado' => 'en_progreso',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('url_pagina');
    }

    public function test_store_rejects_invalid_estado(): void
    {
        $user = User::factory()->create();
        $campana = $this->campana();

        $response = $this->actingAs($user)->postJson(route('admin.seo.onpage.store', $campana), [
            'url_pagina' => '/pagina',
            'accion' => 'Acción con estado inválido',
            'estado' => 'invalido',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('estado');
    }

    public function test_responsable_id_accepts_null(): void
    {
        $user = User::factory()->create();
        $campana = $this->campana();

        $response = $this->actingAs($user)->postJson(route('admin.seo.onpage.store', $campana), [
            'url_pagina' => '/pagina',
            'accion' => 'Acción sin responsable',
            'estado' => 'en_progreso',
            'responsable_id' => null,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('seo_onpage_acciones', [
            'seo_campana_id' => $campana->id,
            'responsable_id' => null,
        ]);
    }

    public function test_responsable_id_accepts_valid_user(): void
    {
        $user = User::factory()->create();
        $responsable = User::factory()->create();
        $campana = $this->campana();

        $response = $this->actingAs($user)->postJson(route('admin.seo.onpage.store', $campana), [
            'url_pagina' => '/pagina',
            'accion' => 'Acción con responsable',
            'estado' => 'en_progreso',
            'responsable_id' => $responsable->id,
        ]);

        $response->assertCreated();
        $response->assertJson(['responsable_id' => $responsable->id]);
        $this->assertDatabaseHas('seo_onpage_acciones', [
            'seo_campana_id' => $campana->id,
            'responsable_id' => $responsable->id,
        ]);
    }

    public function test_responsable_id_rejects_nonexistent_user(): void
    {
        $user = User::factory()->create();
        $campana = $this->campana();

        $response = $this->actingAs($user)->postJson(route('admin.seo.onpage.store', $campana), [
            'url_pagina' => '/pagina',
            'accion' => 'Acción con responsable inválido',
            'estado' => 'en_progreso',
            'responsable_id' => 999999,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('responsable_id');
    }

    // --- update ---------------------------------------------------------

    public function test_admin_can_update_onpage_accion(): void
    {
        $user = User::factory()->create();
        $campana = $this->campana();
        $accion = $campana->onPageAcciones()->create([
            'url_pagina' => '/original',
            'accion' => 'Acción original',
            'estado' => 'en_progreso',
        ]);

        $response = $this->actingAs($user)->putJson(route('admin.seo.onpage.update', $accion), [
            'url_pagina' => '/actualizada',
            'accion' => 'Acción actualizada',
            'estado' => 'completada',
        ]);

        $response->assertOk();
        $response->assertJson([
            'url_pagina' => '/actualizada',
            'accion' => 'Acción actualizada',
            'estado' => 'completada',
        ]);

        $this->assertDatabaseHas('seo_onpage_acciones', [
            'id' => $accion->id,
            'url_pagina' => '/actualizada',
            'accion' => 'Acción actualizada',
            'estado' => 'completada',
        ]);
    }

    // --- destroy ---------------------------------------------------------

    public function test_admin_can_delete_onpage_accion(): void
    {
        $user = User::factory()->create();
        $campana = $this->campana();
        $accion = $campana->onPageAcciones()->create([
            'url_pagina' => '/borrar',
            'accion' => 'Acción a borrar',
            'estado' => 'en_progreso',
        ]);

        $response = $this->actingAs($user)->deleteJson(route('admin.seo.onpage.destroy', $accion));

        $response->assertOk();
        $response->assertJson(['deleted' => true]);
        $this->assertDatabaseMissing('seo_onpage_acciones', ['id' => $accion->id]);
    }

    public function test_deleting_campana_cascades_onpage_acciones(): void
    {
        $campana = $this->campana();
        $campana->onPageAcciones()->create([
            'url_pagina' => '/uno',
            'accion' => 'Primera acción',
            'estado' => 'en_progreso',
        ]);
        $campana->onPageAcciones()->create([
            'url_pagina' => '/dos',
            'accion' => 'Segunda acción',
            'estado' => 'pausada',
        ]);

        $this->assertSame(2, SeoOnPageAccion::where('seo_campana_id', $campana->id)->count());

        $campana->delete();

        $this->assertSame(0, SeoOnPageAccion::where('seo_campana_id', $campana->id)->count());
    }

    // --- auth ---------------------------------------------------------

    public function test_store_requires_authentication(): void
    {
        $campana = $this->campana();

        $response = $this->postJson(route('admin.seo.onpage.store', $campana), [
            'url_pagina' => '/pagina',
            'accion' => 'Acción sin autenticar',
            'estado' => 'en_progreso',
        ]);

        $response->assertStatus(401);
    }

    public function test_update_requires_authentication(): void
    {
        $campana = $this->campana();
        $accion = $campana->onPageAcciones()->create([
            'url_pagina' => '/pagina',
            'accion' => 'Acción existente',
            'estado' => 'en_progreso',
        ]);

        $response = $this->putJson(route('admin.seo.onpage.update', $accion), [
            'url_pagina' => '/otra',
            'accion' => 'Intento sin autenticar',
            'estado' => 'completada',
        ]);

        $response->assertStatus(401);
    }

    public function test_destroy_requires_authentication(): void
    {
        $campana = $this->campana();
        $accion = $campana->onPageAcciones()->create([
            'url_pagina' => '/pagina',
            'accion' => 'Acción existente',
            'estado' => 'en_progreso',
        ]);

        $response = $this->deleteJson(route('admin.seo.onpage.destroy', $accion));

        $response->assertStatus(401);
    }

    // --- tecnico_checklist / checklist independence regression ---------

    public function test_tecnico_checklist_completeness_does_not_satisfy_approval_gate(): void
    {
        $user = User::factory()->create();
        $campana = $this->campana(['fase_actual' => 'auditoria']);

        $tecnicoCompleto = collect(SeoFaseAuditoria::TECNICO_CHECKLIST)
            ->collapse()
            ->keys()
            ->mapWithKeys(fn ($key) => [$key => true])
            ->all();

        $guardar = $this->actingAs($user)->postJson(route('admin.seo.fase.guardar', $campana), [
            'tecnico_checklist' => $tecnicoCompleto,
        ]);
        $guardar->assertOk();

        $registro = $campana->fresh()->faseAuditoria;

        // tecnico_checklist persisted...
        foreach ($tecnicoCompleto as $key => $value) {
            $this->assertTrue((bool) $registro->tecnico_checklist[$key]);
        }
        // ...but the real (gating) checklist is still empty/incomplete.
        $this->assertTrue(collect($registro->checklist ?? [])->filter()->isEmpty());

        $aprobar = $this->actingAs($user)->post(route('admin.seo.fase.aprobar', $campana));

        $aprobar->assertSessionHasErrors('checklist');
        $this->assertSame('auditoria', $campana->fresh()->fase_actual->value);
        $this->assertFalse($campana->fresh()->faseAuditoria->aprobado);
    }

    public function test_real_checklist_completeness_alone_satisfies_approval_gate(): void
    {
        $user = User::factory()->create();
        $campana = $this->campana(['fase_actual' => 'auditoria']);

        $checklistCompleto = collect(array_keys(SeoFaseAuditoria::CHECKLIST))
            ->mapWithKeys(fn ($key) => [$key => true])
            ->all();

        $guardar = $this->actingAs($user)->postJson(route('admin.seo.fase.guardar', $campana), [
            'checklist' => $checklistCompleto,
        ]);
        $guardar->assertOk();

        $registro = $campana->fresh()->faseAuditoria;
        $this->assertTrue(collect($registro->checklist)->filter()->count() === count(SeoFaseAuditoria::CHECKLIST));
        // tecnico_checklist was never sent, so it must remain empty/null.
        $this->assertTrue(empty($registro->tecnico_checklist) || collect($registro->tecnico_checklist)->filter()->isEmpty());

        $aprobar = $this->actingAs($user)->post(route('admin.seo.fase.aprobar', $campana));

        $aprobar->assertSessionDoesntHaveErrors();
        $aprobar->assertRedirect(route('admin.seo.show', $campana));
        $this->assertSame('estrategia', $campana->fresh()->fase_actual->value);
        $this->assertTrue($campana->fresh()->faseAuditoria->aprobado);
    }

    public function test_tecnico_checklist_persists_regardless_of_current_phase(): void
    {
        $user = User::factory()->create();
        $campana = $this->campana(['fase_actual' => 'auditoria']);

        $guardar = $this->actingAs($user)->postJson(route('admin.seo.fase.guardar', $campana), [
            'tecnico_checklist' => ['sitemap_enviado_gsc' => true],
        ]);
        $guardar->assertOk();

        $registro = $campana->fresh()->faseAuditoria;
        $this->assertTrue((bool) $registro->tecnico_checklist['sitemap_enviado_gsc']);

        // The Técnico tab has no phase gate, so the checklist must keep saving to the
        // campaign's Auditoria row even once the campaign has advanced past Auditoria —
        // this protects against the checkboxes silently no-oping on mature campaigns.
        $campana->fase_actual = \App\Enums\FaseSeo::Estrategia;
        $campana->save();

        $guardarEstrategia = $this->actingAs($user)->postJson(route('admin.seo.fase.guardar', $campana), [
            'notas' => 'Notas de estrategia',
            'tecnico_checklist' => ['robots_sin_bloqueos' => true],
        ]);
        $guardarEstrategia->assertOk();
        $guardarEstrategia->assertJsonPath('tecnico_checklist.sitemap_enviado_gsc', true);
        $guardarEstrategia->assertJsonPath('tecnico_checklist.robots_sin_bloqueos', true);

        // Both the earlier (Auditoria-phase) and later (Estrategia-phase) checked items
        // persisted together on the same Auditoria row — merged, not overwritten.
        $registroFresh = $campana->fresh()->faseAuditoria;
        $this->assertTrue((bool) $registroFresh->tecnico_checklist['sitemap_enviado_gsc']);
        $this->assertTrue((bool) $registroFresh->tecnico_checklist['robots_sin_bloqueos']);

        // And the Estrategia row itself only received its own field ('notas'), never tecnico_checklist.
        $this->assertEquals('Notas de estrategia', $campana->fresh()->faseEstrategia->notas);
    }
}
