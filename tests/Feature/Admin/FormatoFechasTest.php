<?php

namespace Tests\Feature\Admin;

use App\Models\AdsCampana;
use App\Models\AdsClic;
use App\Models\AdsOptimizacion;
use App\Models\Bug;
use App\Models\Cliente;
use App\Models\CorreoEnvio;
use App\Models\DisponibilidadBloqueo;
use App\Models\Finanza;
use App\Models\Proyecto;
use App\Models\ProyectoComunicacion;
use App\Models\Reporte;
use App\Models\Servicio;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las pantallas muestran las fechas como dd/mm/aaaa y no como 2026-12-05. Los
 * `value`/`data-*` de formularios y JS siguen en ISO a propósito, así que el
 * texto visible se separa del HTML (sin tags, scripts ni atributos) antes de buscar.
 */
class FormatoFechasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // El manifest de Vite versionado no conoce los assets nuevos hasta compilar.
        $this->withoutVite();
    }

    private function admin(): User
    {
        return User::factory()->create();
    }

    /** Texto que ve el usuario: sin scripts, estilos, comentarios ni atributos. */
    private function visible(string $html): string
    {
        $html = preg_replace('#<(script|style)\b.*?</\1>#is', ' ', $html);
        $html = preg_replace('/<!--.*?-->/s', ' ', $html);

        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function assertMuestra(string $html, string $mx, string $iso): void
    {
        $this->assertStringContainsString($mx, $this->visible($html), "No se ve $mx");
        $this->assertDoesNotMatchRegularExpression('/\b'.preg_quote($iso, '/').'\b/', $this->visible($html), "Se ve $iso en texto visible");
    }

    public function test_clientes_muestra_la_renovacion_en_dd_mm_aaaa_y_el_data_sigue_iso(): void
    {
        Cliente::factory()->create(['fecha_renovacion_contrato' => '2026-12-05']);

        $res = $this->actingAs($this->admin())->get(route('admin.clientes.index'))->assertOk();

        $this->assertMuestra($res->getContent(), '05/12/2026', '2026-12-05');
        // El JSON que usa el modal y los inputs conserva el ISO.
        $this->assertStringContainsString('2026-12-05', $res->getContent());
    }

    public function test_finanzas_muestra_vencimiento_y_pago(): void
    {
        Finanza::create([
            'cliente_id' => Cliente::factory()->create()->id,
            'servicio_id' => null,
            'concepto' => 'Factura de prueba',
            'tipo' => 'ingreso',
            'monto' => 1000,
            'estado' => 'pagado',
            'fecha_emision' => '2026-11-01',
            'fecha_vencimiento' => '2026-12-05',
            'fecha_pago' => '2026-12-06',
            'mes' => 11,
            'anio' => 2026,
        ]);

        $res = $this->actingAs($this->admin())->get(route('admin.finanzas.index', ['mes' => 11, 'anio' => 2026]))->assertOk();
        $html = $res->getContent();

        $this->assertMuestra($html, '05/12/2026', '2026-12-05');
        $this->assertMuestra($html, '06/12/2026', '2026-12-06');
    }

    public function test_servicios_muestra_el_alta(): void
    {
        Servicio::factory()->create(['fecha_inicio' => '2026-12-05']);

        $res = $this->actingAs($this->admin())->get(route('admin.servicios.index'))->assertOk();

        $this->assertMuestra($res->getContent(), '05/12/2026', '2026-12-05');
    }

    public function test_desarrollo_muestra_entrega_tarea_y_bug_en_dd_mm_aaaa(): void
    {
        $cliente = Cliente::factory()->create();
        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Sitio de fechas',
            'tipo' => 'web_nueva',
            'fase_actual' => 'control', // los bugs solo se ven en Control
            'porcentaje_avance' => 10,
            'presupuesto' => 1000,
            'anticipo' => 0,
            'pagos_recibidos' => 0,
            'estado' => 'activo',
            'fecha_inicio' => '2026-11-02',
            'fecha_entrega_estimada' => '2026-12-05',
        ]);
        foreach (['planeacion', 'organizacion', 'direccion', 'control', 'qa'] as $fase) {
            $proyecto->{$fase}()->create(['checklist' => []]);
        }
        Tarea::create(['proyecto_id' => $proyecto->id, 'titulo' => 'Tarea con fecha', 'prioridad' => 'media', 'estado' => 'pendiente', 'fecha_limite' => '2026-12-07']);
        Bug::create(['proyecto_id' => $proyecto->id, 'titulo' => 'Bug resuelto', 'prioridad' => 'media', 'estado' => 'resuelto', 'fecha_resolucion' => '2026-12-08']);
        ProyectoComunicacion::create(['proyecto_id' => $proyecto->id, 'fecha' => '2026-12-09', 'resumen' => 'Llamada con el cliente']);

        $index = $this->actingAs($this->admin())->get(route('admin.desarrollo.index'))->assertOk()->getContent();
        $this->assertMuestra($index, '05/12/2026', '2026-12-05');

        $bugs = $this->actingAs($this->admin())->get(route('admin.desarrollo.index', ['vista' => 'tabla']))->assertOk()->getContent();
        $this->assertMuestra($bugs, '05/12/2026', '2026-12-05');

        $show = $this->actingAs($this->admin())->get(route('admin.desarrollo.show', $proyecto))->assertOk()->getContent();
        $this->assertMuestra($show, '07/12/2026', '2026-12-07');
        $this->assertMuestra($show, '08/12/2026', '2026-12-08');
        $this->assertMuestra($show, '09/12/2026', '2026-12-09');
        // El kanban deja de usar el año de 2 dígitos (07/12/26).
        $this->assertStringNotContainsString('07/12/26<', $show);
    }

    public function test_ads_show_muestra_optimizacion_y_reporte_aprobado(): void
    {
        $campana = AdsCampana::factory()->create(['fase_actual' => 'reporte']);
        $campana->briefings()->create(['ciclo' => 1, 'checklist' => []]);
        $campana->configuraciones()->create(['ciclo' => 1, 'checklist' => []]);
        $campana->lanzamientos()->create(['ciclo' => 1, 'checklist' => []]);
        AdsOptimizacion::create(['ads_campana_id' => $campana->id, 'fecha' => '2026-12-05', 'tipo' => 'puja', 'descripcion' => 'Ajuste de pujas']);
        $campana->reportes()->create(['ciclo' => 1, 'checklist' => [], 'aprobado' => true, 'fecha_aprobacion' => '2026-12-06']);

        $res = $this->actingAs($this->admin())->get(route('admin.ads.show', $campana))->assertOk();
        $html = $res->getContent();

        $this->assertMuestra($html, '05/12/2026', '2026-12-05');
        $this->assertMuestra($html, '06/12/2026', '2026-12-06');
        // El data-* que alimenta el formulario de edición sigue en ISO.
        $this->assertStringContainsString('data-optimizacion-fecha="2026-12-05"', $html);
    }

    public function test_reportes_show_muestra_el_periodo(): void
    {
        $reporte = Reporte::create([
            'cliente_id' => Cliente::factory()->create()->id,
            'area' => 'seo',
            'titulo' => 'Reporte de fechas',
            'periodo_inicio' => '2026-11-05',
            'periodo_fin' => '2026-12-05',
            'estado' => 'borrador',
        ]);

        $res = $this->actingAs($this->admin())->get(route('admin.reportes.show', $reporte))->assertOk();

        $this->assertMuestra($res->getContent(), '05/11/2026', '2026-11-05');
        $this->assertMuestra($res->getContent(), '05/12/2026', '2026-12-05');
    }

    public function test_seo_show_muestra_la_vigencia_del_contrato(): void
    {
        $cliente = Cliente::factory()->create(['fecha_inicio_contrato' => '2026-01-05', 'fecha_renovacion_contrato' => '2026-12-05']);
        $campana = \App\Models\SeoCampana::create([
            'cliente_id' => $cliente->id,
            'servicio_id' => Servicio::factory()->create(['cliente_id' => $cliente->id])->id,
            'nombre' => 'SEO de fechas',
            'url_sitio' => 'https://ejemplo.test',
            'estado' => 'activa',
            'fase_actual' => 'auditoria',
            'ciclo_actual' => 1,
        ]);
        foreach (['auditorias', 'estrategias', 'ejecuciones', 'reportes'] as $fase) {
            $campana->{$fase}()->create(['ciclo' => 1, 'checklist' => []]);
        }

        $res = $this->actingAs($this->admin())->get(route('admin.seo.show', $campana))->assertOk();

        $this->assertMuestra($res->getContent(), '05/01/2026', '2026-01-05');
        $this->assertMuestra($res->getContent(), '05/12/2026', '2026-12-05');
    }

    public function test_clics_de_integraciones_muestran_fecha_y_hora(): void
    {
        $cliente = Cliente::factory()->create();
        $clic = AdsClic::create(['cliente_id' => $cliente->id, 'visitor_id' => 'v-fechas', 'landing_url' => 'https://ejemplo.test/']);
        $clic->forceFill(['created_at' => '2026-12-05 14:30:00'])->save();

        $res = $this->actingAs($this->admin())->get(route('admin.clientes.clics', $cliente))->assertOk();

        $this->assertMuestra($res->getContent(), '05/12/2026 14:30', '2026-12-05');
    }

    public function test_agenda_muestra_el_bloqueo(): void
    {
        DisponibilidadBloqueo::create(['fecha' => '2026-12-05', 'motivo' => 'Vacaciones']);

        $res = $this->actingAs($this->admin())->get(route('admin.agenda.index'))->assertOk();

        $this->assertStringContainsString('05/12/2026', $this->visible($res->getContent()));
    }

    public function test_correo_envios_muestra_la_fecha_de_programacion(): void
    {
        CorreoEnvio::factory()->create(['estado' => 'programado', 'programado_para' => '2026-12-05 14:30:00']);

        $res = $this->actingAs($this->admin())->get(route('admin.correo.envios.index'))->assertOk();

        $this->assertMuestra($res->getContent(), '05/12/2026', '2026-12-05');
    }
}
