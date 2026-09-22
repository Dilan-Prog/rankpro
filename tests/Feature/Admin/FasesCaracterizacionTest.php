<?php

namespace Tests\Feature\Admin;

use App\Models\AdsCampana;
use App\Models\AutomatizacionProyecto;
use App\Models\Cliente;
use App\Models\SeoCampana;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fija el comportamiento actual de las 3 máquinas de fase cíclicas (SEO, Ads,
 * Automatizaciones) ANTES de extraerlas a App\Services\Fases. Si algo aquí se
 * pone en rojo tras el refactor, el refactor cambió comportamiento, no el
 * test. No cubre Desarrollo (ProyectoFaseController es lineal y ya tiene
 * DesarrolloProyectosTest).
 */
class FasesCaracterizacionTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    // ---------------------------------------------------------------- SEO

    private function campanaSeo(): SeoCampana
    {
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'seo']);
        $this->postJson('/admin/seo', [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña SEO test',
            'url_sitio' => 'https://ejemplo.com',
        ])->assertStatus(201);

        return SeoCampana::latest('id')->first();
    }

    public function test_seo_guardar_checklist_parcial_no_marca_completo(): void
    {
        $this->actor();
        $campana = $this->campanaSeo();

        $this->postJson("/admin/seo/{$campana->id}/fase/guardar", [
            'checklist' => ['auditoria_tecnica_completada' => true],
        ])->assertOk()->assertJsonPath('completo', false);
    }

    public function test_seo_aprobar_bloqueado_si_checklist_incompleto(): void
    {
        $this->actor();
        $campana = $this->campanaSeo();

        $this->post("/admin/seo/{$campana->id}/fase/aprobar")
            ->assertSessionHasErrors('checklist');

        $campana->refresh();
        $this->assertEquals('auditoria', $campana->fase_actual->value);
    }

    public function test_seo_aprobar_avanza_fase_con_checklist_completo(): void
    {
        $this->actor();
        $campana = $this->campanaSeo();

        $checklist = array_fill_keys(array_keys(\App\Models\SeoFaseAuditoria::CHECKLIST), true);
        $this->postJson("/admin/seo/{$campana->id}/fase/guardar", ['checklist' => $checklist])->assertOk();

        $this->post("/admin/seo/{$campana->id}/fase/aprobar")->assertSessionDoesntHaveErrors();

        $campana->refresh();
        $this->assertEquals('estrategia', $campana->fase_actual->value);
        $this->assertTrue($campana->faseAuditoria->fresh()->aprobado);
    }

    public function test_seo_aprobar_reporte_no_avanza_de_fase(): void
    {
        $this->actor();
        $campana = $this->campanaSeo();
        $campana->fase_actual = \App\Enums\FaseSeo::Reporte;
        $campana->save();

        $checklist = array_fill_keys(array_keys(\App\Models\SeoReporte::CHECKLIST), true);
        $this->postJson("/admin/seo/{$campana->id}/fase/guardar", ['checklist' => $checklist])->assertOk();
        $this->post("/admin/seo/{$campana->id}/fase/aprobar")->assertSessionDoesntHaveErrors();

        $campana->refresh();
        $this->assertEquals('reporte', $campana->fase_actual->value);
        $this->assertTrue($campana->reporteActual->fresh()->aprobado);
    }

    public function test_seo_retroceder_limpia_aprobado_de_la_fase_anterior(): void
    {
        $this->actor();
        $campana = $this->campanaSeo();
        $checklist = array_fill_keys(array_keys(\App\Models\SeoFaseAuditoria::CHECKLIST), true);
        $this->postJson("/admin/seo/{$campana->id}/fase/guardar", ['checklist' => $checklist])->assertOk();
        $this->post("/admin/seo/{$campana->id}/fase/aprobar");
        $campana->refresh();
        $this->assertEquals('estrategia', $campana->fase_actual->value);

        $this->post("/admin/seo/{$campana->id}/fase/retroceder")->assertSessionDoesntHaveErrors();

        $campana->refresh();
        $this->assertEquals('auditoria', $campana->fase_actual->value);
        $this->assertFalse($campana->faseAuditoria->fresh()->aprobado);
    }

    public function test_seo_nuevo_ciclo_exige_reporte_aprobado(): void
    {
        $this->actor();
        $campana = $this->campanaSeo();
        $campana->fase_actual = \App\Enums\FaseSeo::Reporte;
        $campana->save();

        $this->post("/admin/seo/{$campana->id}/fase/nuevo-ciclo")->assertSessionHasErrors('fase');
    }

    public function test_seo_nuevo_ciclo_crea_las_4_filas_del_ciclo_siguiente(): void
    {
        $this->actor();
        $campana = $this->campanaSeo();
        $campana->fase_actual = \App\Enums\FaseSeo::Reporte;
        $campana->save();
        $campana->reporteActual->update(['aprobado' => true]);

        $this->post("/admin/seo/{$campana->id}/fase/nuevo-ciclo")->assertSessionDoesntHaveErrors();

        $campana->refresh();
        $this->assertEquals(2, $campana->ciclo_actual);
        $this->assertEquals('auditoria', $campana->fase_actual->value);
        $this->assertEquals(1, $campana->auditorias()->where('ciclo', 2)->count());
        $this->assertEquals(1, $campana->estrategias()->where('ciclo', 2)->count());
        $this->assertEquals(1, $campana->ejecuciones()->where('ciclo', 2)->count());
        $this->assertEquals(1, $campana->reportes()->where('ciclo', 2)->count());
    }

    public function test_seo_cerrar_y_pausar_exigen_reporte_aprobado(): void
    {
        $this->actor();
        $campana = $this->campanaSeo();
        $campana->fase_actual = \App\Enums\FaseSeo::Reporte;
        $campana->save();

        $this->post("/admin/seo/{$campana->id}/fase/cerrar")->assertSessionHasErrors('fase');
        $this->post("/admin/seo/{$campana->id}/fase/pausar")->assertSessionHasErrors('fase');

        $campana->reporteActual->update(['aprobado' => true]);

        $this->post("/admin/seo/{$campana->id}/fase/cerrar")->assertSessionDoesntHaveErrors();
        $campana->refresh();
        $this->assertEquals('cerrada', $campana->fase_actual->value);
        $this->assertEquals('finalizada', $campana->estado->value);
    }

    public function test_seo_tecnico_checklist_se_guarda_independiente_del_checklist_de_fase(): void
    {
        $this->actor();
        $campana = $this->campanaSeo();

        $this->postJson("/admin/seo/{$campana->id}/fase/guardar", [
            'tecnico_checklist' => ['sitemap_enviado_gsc' => true],
        ])->assertOk()->assertJsonPath('tecnico_checklist.sitemap_enviado_gsc', true);

        $this->assertTrue($campana->faseAuditoria->fresh()->tecnico_checklist['sitemap_enviado_gsc']);
    }

    // ---------------------------------------------------------------- Ads

    private function campanaAds(): AdsCampana
    {
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'google_ads']);
        $this->postJson('/admin/ads', [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña Ads test',
            'plataforma' => 'google_ads',
            'objetivo' => 'leads',
            'presupuesto_mensual' => 5000,
        ])->assertStatus(201);

        return AdsCampana::latest('id')->first();
    }

    public function test_ads_aprobar_bloqueado_si_checklist_incompleto(): void
    {
        $this->actor();
        $campana = $this->campanaAds();

        $this->post("/admin/ads/{$campana->id}/fase/aprobar")->assertSessionHasErrors();
        $campana->refresh();
        $this->assertEquals('briefing', $campana->fase_actual->value);
    }

    public function test_ads_aprobar_avanza_con_checklist_completo(): void
    {
        $this->actor();
        $campana = $this->campanaAds();
        $checklist = array_fill_keys(array_keys(\App\Models\AdsBriefing::CHECKLIST), true);
        $this->postJson("/admin/ads/{$campana->id}/fase/guardar", ['checklist' => $checklist])->assertOk();

        $this->post("/admin/ads/{$campana->id}/fase/aprobar")->assertSessionDoesntHaveErrors();
        $campana->refresh();
        $this->assertEquals('configuracion', $campana->fase_actual->value);
    }

    // ------------------------------------------------------ Automatización

    private function proyectoAutomatizacion(): AutomatizacionProyecto
    {
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'automatizacion']);
        $this->post('/admin/automatizaciones', [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Proyecto automatización test',
        ])->assertRedirect();

        return AutomatizacionProyecto::latest('id')->first();
    }

    public function test_automatizacion_aprobar_bloqueado_si_checklist_incompleto(): void
    {
        $this->actor();
        $proyecto = $this->proyectoAutomatizacion();

        $this->post("/admin/automatizaciones/{$proyecto->id}/fase/aprobar")->assertSessionHasErrors();
        $proyecto->refresh();
        $this->assertEquals('diagnostico', $proyecto->fase_actual->value);
    }

    public function test_automatizacion_aprobar_avanza_con_checklist_completo(): void
    {
        $this->actor();
        $proyecto = $this->proyectoAutomatizacion();
        $checklist = array_fill_keys(array_keys(\App\Models\AutomatizacionFaseDiagnostico::CHECKLIST), true);
        $this->postJson("/admin/automatizaciones/{$proyecto->id}/fase/guardar", ['checklist' => $checklist])->assertOk();

        $this->post("/admin/automatizaciones/{$proyecto->id}/fase/aprobar")->assertSessionDoesntHaveErrors();
        $proyecto->refresh();
        $this->assertEquals('diseno_flujo', $proyecto->fase_actual->value);
    }
}
