<?php

namespace Tests\Feature\Admin;

use App\Models\AdsCampana;
use App\Models\AutomatizacionProyecto;
use App\Models\Cliente;
use App\Models\SeoCampana;
use App\Models\Servicio;
use App\Models\User;
use App\Services\Fases\MaquinaAds;
use App\Services\Fases\MaquinaAutomatizacion;
use App\Services\Fases\MaquinaFasesCiclica;
use App\Services\Fases\MaquinaSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión QA: retroceder debe invalidar la aprobación de la fase destino Y
 * de las posteriores, y guardar un checklist incompleto revoca la aprobación.
 * Se prueba contra las tres máquinas cíclicas (SEO, Ads, Automatizaciones).
 */
class FasesAprobacionRevocacionTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: \Illuminate\Database\Eloquent\Model, 1: string, 2: MaquinaFasesCiclica} */
    private function crear(string $tipo): array
    {
        $this->actingAs(User::factory()->create());
        $cliente = Cliente::factory()->create();

        if ($tipo === 'seo') {
            $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'seo']);
            $this->postJson('/admin/seo', ['cliente_id' => $cliente->id, 'servicio_id' => $servicio->id, 'nombre' => 'C', 'url_sitio' => 'https://ejemplo.com'])->assertStatus(201);

            return [SeoCampana::latest('id')->first(), '/admin/seo', app(MaquinaSeo::class)];
        }
        if ($tipo === 'ads') {
            $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'google_ads']);
            $this->postJson('/admin/ads', ['cliente_id' => $cliente->id, 'servicio_id' => $servicio->id, 'nombre' => 'C', 'plataforma' => 'google_ads', 'objetivo' => 'leads', 'presupuesto_mensual' => 5000])->assertStatus(201);

            return [AdsCampana::latest('id')->first(), '/admin/ads', app(MaquinaAds::class)];
        }
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'automatizacion']);
        $this->post('/admin/automatizaciones', ['cliente_id' => $cliente->id, 'servicio_id' => $servicio->id, 'nombre' => 'P'])->assertRedirect();

        return [AutomatizacionProyecto::latest('id')->first(), '/admin/automatizaciones', app(MaquinaAutomatizacion::class)];
    }

    private function claves(MaquinaFasesCiclica $maquina, $modelo): array
    {
        $m = new \ReflectionMethod($maquina, 'checklistKeys');
        $m->setAccessible(true);

        return array_fill_keys(array_keys($m->invoke($maquina, $modelo->fase_actual)), true);
    }

    /** Aprueba todas las fases hasta dejar el Reporte aprobado. */
    private function llevarAReporteAprobado($modelo, string $base, MaquinaFasesCiclica $maquina): void
    {
        for ($i = 0; $i < 4; $i++) {
            $modelo->refresh();
            $this->postJson("$base/{$modelo->id}/fase/guardar", ['checklist' => $this->claves($maquina, $modelo)])->assertOk();
            $this->post("$base/{$modelo->id}/fase/aprobar")->assertSessionDoesntHaveErrors();
        }
        $modelo->refresh();
        $this->assertTrue((bool) $maquina->registro($modelo, $modelo->fase_actual)->aprobado, 'el reporte debe quedar aprobado');
    }

    /** @dataProvider tipos */
    public function test_retroceder_invalida_reporte_aprobado_y_bloquea_cerrar(string $tipo): void
    {
        [$modelo, $base, $maquina] = $this->crear($tipo);
        $this->llevarAReporteAprobado($modelo, $base, $maquina);
        $faseReporte = $modelo->fase_actual;

        $this->post("$base/{$modelo->id}/fase/retroceder")->assertSessionDoesntHaveErrors();
        $this->post("$base/{$modelo->id}/fase/retroceder")->assertSessionDoesntHaveErrors();

        $modelo->refresh();
        $this->assertNotEquals($faseReporte, $modelo->fase_actual);
        $this->assertFalse((bool) $maquina->registro($modelo, $faseReporte)->aprobado, 'el reporte aprobado no debe sobrevivir al retroceso');
        $this->assertFalse((bool) $maquina->registro($modelo, $modelo->fase_actual)->aprobado);
    }

    /** @dataProvider tipos */
    public function test_retroceder_una_fase_desde_reporte_no_permite_cerrar_ni_nuevo_ciclo(string $tipo): void
    {
        [$modelo, $base, $maquina] = $this->crear($tipo);
        $this->llevarAReporteAprobado($modelo, $base, $maquina);
        $faseReporte = $modelo->fase_actual;

        $this->post("$base/{$modelo->id}/fase/retroceder");
        // Reaprobar la fase destino devuelve al Reporte, que ya no debe estar aprobado.
        $modelo->refresh();
        $this->postJson("$base/{$modelo->id}/fase/guardar", ['checklist' => $this->claves($maquina, $modelo)])->assertOk();
        $this->post("$base/{$modelo->id}/fase/aprobar");
        $modelo->refresh();
        $this->assertEquals($faseReporte, $modelo->fase_actual);

        $this->post("$base/{$modelo->id}/fase/cerrar")->assertSessionHasErrors('fase');
        $this->post("$base/{$modelo->id}/fase/nuevo-ciclo")->assertSessionHasErrors('fase');
    }

    /** @dataProvider tipos */
    public function test_guardar_checklist_incompleto_revoca_la_aprobacion(string $tipo): void
    {
        [$modelo, $base, $maquina] = $this->crear($tipo);
        $this->llevarAReporteAprobado($modelo, $base, $maquina);

        $checklist = $this->claves($maquina, $modelo);
        $checklist[array_key_first($checklist)] = false;

        $this->postJson("$base/{$modelo->id}/fase/guardar", ['checklist' => $checklist])->assertOk()->assertJsonPath('completo', false);

        $modelo->refresh();
        $this->assertFalse((bool) $maquina->registro($modelo, $modelo->fase_actual)->aprobado);
        $this->post("$base/{$modelo->id}/fase/cerrar")->assertSessionHasErrors('fase');
    }

    public static function tipos(): array
    {
        return ['seo' => ['seo'], 'ads' => ['ads'], 'automatizacion' => ['automatizacion']];
    }
}
