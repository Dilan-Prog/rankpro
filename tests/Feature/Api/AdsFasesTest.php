<?php

namespace Tests\Feature\Api;

use App\Models\AdsBriefing;
use App\Models\AdsCampana;
use App\Models\Cliente;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Checklist incompleto bloquea aprobar; completo avanza — vía API, estilo FasesCaracterizacionTest. */
class AdsFasesTest extends TestCase
{
    use RefreshDatabase;

    private function campana(): AdsCampana
    {
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'google_ads']);

        $campana = AdsCampana::create([
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña fases API',
            'plataforma' => 'google_ads',
            'objetivo' => 'leads',
            'presupuesto_mensual' => 5000,
            'estado' => 'activa',
            'fase_actual' => 'briefing',
            'ciclo_actual' => 1,
        ]);

        $campana->briefings()->create(['ciclo' => 1, 'checklist' => []]);
        $campana->configuraciones()->create(['ciclo' => 1, 'checklist' => []]);
        $campana->lanzamientos()->create(['ciclo' => 1, 'checklist' => []]);
        $campana->reportes()->create(['ciclo' => 1, 'checklist' => []]);

        return $campana;
    }

    public function test_get_fase_devuelve_estado(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['ads:leer']);
        $campana = $this->campana();

        $this->getJson("/api/v1/ads/campanas/{$campana->id}/fase")
            ->assertOk()
            ->assertJsonPath('data.fase_actual', 'briefing')
            ->assertJsonPath('data.completo', false);
    }

    public function test_aprobar_bloqueado_si_checklist_incompleto(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['ads:escribir']);
        $campana = $this->campana();

        $this->postJson("/api/v1/ads/campanas/{$campana->id}/fase/aprobar")
            ->assertStatus(422)
            ->assertJsonPath('campo', 'checklist');

        $this->assertEquals('briefing', $campana->fresh()->fase_actual->value);
    }

    public function test_guardar_y_aprobar_avanza_de_fase(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['ads:escribir']);
        $campana = $this->campana();

        $checklist = array_fill_keys(array_keys(AdsBriefing::CHECKLIST), true);
        $this->postJson("/api/v1/ads/campanas/{$campana->id}/fase/guardar", ['checklist' => $checklist])
            ->assertOk()
            ->assertJsonPath('data.completo', true);

        $this->postJson("/api/v1/ads/campanas/{$campana->id}/fase/aprobar")->assertOk();

        $this->assertEquals('configuracion', $campana->fresh()->fase_actual->value);
    }

    public function test_fase_requiere_habilidad_ads(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['conversiones:leer']);
        $campana = $this->campana();

        $this->getJson("/api/v1/ads/campanas/{$campana->id}/fase")->assertStatus(403);
    }
}
