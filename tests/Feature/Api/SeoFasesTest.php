<?php

namespace Tests\Feature\Api;

use App\Enums\FaseSeo;
use App\Models\Cliente;
use App\Models\SeoCampana;
use App\Models\SeoFaseAuditoria;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Replica FasesCaracterizacionTest (checklist incompleto bloquea aprobar,
 * checklist completo aprueba y avanza) pero contra los endpoints de la API
 * en vez del controlador web, para confirmar que ambos canales usan la misma
 * MaquinaSeo y llegan al mismo resultado.
 */
class SeoFasesTest extends TestCase
{
    use RefreshDatabase;

    private function campana(): SeoCampana
    {
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'seo']);

        $campana = SeoCampana::create([
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña fases API',
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

    public function test_fase_estado_devuelve_la_maquina(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $campana = $this->campana();

        $this->getJson("/api/v1/seo/campanas/{$campana->id}/fase")
            ->assertOk()
            ->assertJsonPath('data.fase_actual', 'auditoria')
            ->assertJsonPath('data.ciclo_actual', 1)
            ->assertJsonPath('data.completo', false);
    }

    public function test_guardar_checklist_parcial_no_marca_completo(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $campana = $this->campana();

        $this->postJson("/api/v1/seo/campanas/{$campana->id}/fase/guardar", [
            'checklist' => ['auditoria_tecnica_completada' => true],
        ])->assertOk()->assertJsonPath('data.completo', false);
    }

    public function test_aprobar_bloqueado_si_checklist_incompleto(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $campana = $this->campana();

        $this->postJson("/api/v1/seo/campanas/{$campana->id}/fase/aprobar")
            ->assertStatus(422)
            ->assertJsonPath('campo', 'checklist');

        $campana->refresh();
        $this->assertEquals('auditoria', $campana->fase_actual->value);
    }

    public function test_aprobar_avanza_fase_con_checklist_completo(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $campana = $this->campana();

        $checklist = array_fill_keys(array_keys(SeoFaseAuditoria::CHECKLIST), true);
        $this->postJson("/api/v1/seo/campanas/{$campana->id}/fase/guardar", ['checklist' => $checklist])->assertOk();

        $this->postJson("/api/v1/seo/campanas/{$campana->id}/fase/aprobar")
            ->assertOk()
            ->assertJsonPath('data.fase_actual', 'estrategia');

        $campana->refresh();
        $this->assertEquals('estrategia', $campana->fase_actual->value);
    }

    public function test_aprobar_requiere_habilidad_de_escritura(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['seo:leer']);
        $campana = $this->campana();

        $this->postJson("/api/v1/seo/campanas/{$campana->id}/fase/aprobar")
            ->assertStatus(403)
            ->assertJsonPath('habilidad_requerida', 'seo:escribir');
    }

    public function test_retroceder_limpia_aprobado_de_la_fase_anterior(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $campana = $this->campana();

        $checklist = array_fill_keys(array_keys(SeoFaseAuditoria::CHECKLIST), true);
        $this->postJson("/api/v1/seo/campanas/{$campana->id}/fase/guardar", ['checklist' => $checklist])->assertOk();
        $this->postJson("/api/v1/seo/campanas/{$campana->id}/fase/aprobar")->assertOk();

        $this->postJson("/api/v1/seo/campanas/{$campana->id}/fase/retroceder")
            ->assertOk()
            ->assertJsonPath('data.fase_actual', 'auditoria');

        $this->assertFalse($campana->faseAuditoria->fresh()->aprobado);
    }
}
