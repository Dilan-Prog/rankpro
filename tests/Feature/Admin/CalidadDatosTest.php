<?php

namespace Tests\Feature\Admin;

use App\Models\AdsCampana;
use App\Models\AdsMetrica;
use App\Models\Cliente;
use App\Models\Keyword;
use App\Models\KeywordMedicion;
use App\Models\MetricaCliente;
use App\Models\MetricaClienteValor;
use App\Models\SeoCampana;
use App\Models\SeoFaseAuditoria;
use App\Models\SeoReporte;
use App\Models\Servicio;
use App\Models\User;
use App\Support\Calidad\DatosDesactualizados;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Calidad de datos (punto 17): cada cifra dice de dónde sale y de cuándo es, y lo viejo se marca.
 * Cubre fecha_auditoria del SEO Score y las pantallas SEO, Ads, Dashboard, Inicio, Keywords y Métricas.
 */
class CalidadDatosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2026-11-10 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function campanaSeo(array $auditoria = [], array $campana = []): SeoCampana
    {
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'seo']);
        $c = SeoCampana::create($campana + [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña SEO',
            'estado' => 'activa',
            'fase_actual' => 'auditoria',
            'ciclo_actual' => 1,
        ]);
        $c->auditorias()->create(['ciclo' => 1, 'checklist' => []] + $auditoria);
        $c->estrategias()->create(['ciclo' => 1, 'checklist' => []]);
        $c->ejecuciones()->create(['ciclo' => 1, 'checklist' => []]);
        $c->reportes()->create(['ciclo' => 1, 'checklist' => []]);

        return $c->fresh();
    }

    // --- fecha_auditoria (modelo y guardado) ----------------------------------

    public function test_seo_fecha_auditoria_se_fija_al_guardar_el_score(): void
    {
        $campana = $this->campanaSeo();

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.seo.fase.guardar', $campana), ['seo_score' => '85'])
            ->assertOk()
            ->assertJsonPath('fecha_auditoria', '2026-11-10');

        $this->assertSame('2026-11-10', $campana->fresh()->faseAuditoria->fecha_auditoria->format('Y-m-d'));
    }

    public function test_seo_fecha_auditoria_no_cambia_al_editar_otros_campos(): void
    {
        $campana = $this->campanaSeo(['seo_score' => 70, 'fecha_auditoria' => '2026-08-01']);
        Carbon::setTestNow('2026-11-20 12:00:00');

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.seo.fase.guardar', $campana), ['seo_score' => '70', 'fecha_auditoria' => '2026-08-01', 'errores_tecnicos' => '4', 'notas' => 'ajuste'])
            ->assertOk();

        $this->assertSame('2026-08-01', $campana->fresh()->faseAuditoria->fecha_auditoria->format('Y-m-d'));
    }

    public function test_seo_un_autoguardado_con_fecha_vacia_no_borra_la_fecha(): void
    {
        $campana = $this->campanaSeo(['seo_score' => 70, 'fecha_auditoria' => '2026-10-01']);

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.seo.fase.guardar', $campana), ['seo_score' => '70', 'fecha_auditoria' => '', 'errores_tecnicos' => '2'])
            ->assertOk();

        $this->assertSame('2026-10-01', $campana->fresh()->faseAuditoria->fecha_auditoria->format('Y-m-d'));
    }

    public function test_seo_no_inventa_fecha_para_registros_viejos(): void
    {
        $campana = $this->campanaSeo();
        // Registro previo a la columna: score sin fecha, escrito sin pasar por los eventos del modelo.
        DB::table('seo_fase_auditoria')->where('seo_campana_id', $campana->id)->update(['seo_score' => 100, 'fecha_auditoria' => null]);

        $aud = $campana->fresh()->faseAuditoria;
        $aud->update(['notas' => 'solo una nota']);

        $this->assertNull($aud->fresh()->fecha_auditoria);
        $this->assertSame(100, $aud->fresh()->seo_score);
    }

    public function test_seo_cambiar_el_score_actualiza_la_fecha_y_vaciarlo_la_borra(): void
    {
        $campana = $this->campanaSeo(['seo_score' => 60, 'fecha_auditoria' => '2026-06-01']);
        $aud = $campana->faseAuditoria;

        $aud->update(['seo_score' => 75]);
        $this->assertSame('2026-11-10', $aud->fresh()->fecha_auditoria->format('Y-m-d'));

        $aud->update(['seo_score' => null]);
        $this->assertNull($aud->fresh()->fecha_auditoria);
    }

    public function test_seo_una_fecha_dada_a_mano_manda_sobre_la_automatica(): void
    {
        $campana = $this->campanaSeo();

        $campana->faseAuditoria->update(['seo_score' => 90, 'fecha_auditoria' => '2026-09-15']);

        $this->assertSame('2026-09-15', $campana->fresh()->faseAuditoria->fecha_auditoria->format('Y-m-d'));
    }

    public function test_seo_rechaza_una_fecha_de_auditoria_futura(): void
    {
        $campana = $this->campanaSeo();

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.seo.fase.guardar', $campana), ['fecha_auditoria' => '2027-01-01'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('fecha_auditoria');
    }

    // --- Módulo SEO -------------------------------------------------------------

    public function test_seo_detalle_muestra_fuente_y_fecha_del_score(): void
    {
        $campana = $this->campanaSeo(['seo_score' => 88, 'fecha_auditoria' => '2026-11-03']);

        $html = $this->actingAs(User::factory()->create())->get(route('admin.seo.show', $campana))->assertOk()->getContent();

        $this->assertStringContainsString('Auditoría SEO · 03/11/2026', $html);
        $this->assertStringNotContainsString('Hace ', $this->soloBloque($html, 'seo-kpis'));
    }

    public function test_seo_detalle_con_auditoria_de_mas_de_90_dias_muestra_la_insignia(): void
    {
        $campana = $this->campanaSeo(['seo_score' => 88, 'fecha_auditoria' => '2026-07-01']);

        $this->actingAs(User::factory()->create())->get(route('admin.seo.show', $campana))
            ->assertOk()
            ->assertSee('Hace 132 días');
    }

    public function test_seo_detalle_con_score_de_registro_viejo_dice_sin_fecha_de_auditoria(): void
    {
        $campana = $this->campanaSeo();
        DB::table('seo_fase_auditoria')->where('seo_campana_id', $campana->id)->update(['seo_score' => 100]);

        $this->actingAs(User::factory()->create())->get(route('admin.seo.show', $campana))
            ->assertOk()
            ->assertSee('Auditoría SEO · Sin fecha de auditoría');
    }

    public function test_seo_detalle_muestra_el_ultimo_mes_de_la_grafica_de_trafico(): void
    {
        $campana = $this->campanaSeo();
        $campana->metricasMensuales()->create(['mes' => 9, 'anio' => 2026, 'trafico_organico' => 1200]);

        $this->actingAs(User::factory()->create())->get(route('admin.seo.show', $campana))
            ->assertOk()
            ->assertSee('Métricas mensuales SEO · último mes capturado 09/2026', false)
            ->assertSee('Hace 41 días');
    }

    public function test_seo_indice_muestra_fuente_y_fecha_de_score_y_trafico(): void
    {
        $campana = $this->campanaSeo(['seo_score' => 80, 'fecha_auditoria' => '2026-11-01']);
        $campana->reportes()->first()->update(['trafico_actual' => 500]);

        $html = $this->actingAs(User::factory()->create())->get(route('admin.seo.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Auditorías SEO (la más antigua) · 01/11/2026', $html);
        $this->assertStringContainsString('Reportes SEO (edición más antigua) · 10/11/2026', $html);
    }

    public function test_seo_indice_cuenta_los_scores_sin_fecha_en_lugar_de_inventarla(): void
    {
        $campana = $this->campanaSeo();
        DB::table('seo_fase_auditoria')->where('seo_campana_id', $campana->id)->update(['seo_score' => 100]);

        $this->actingAs(User::factory()->create())->get(route('admin.seo.index'))
            ->assertOk()
            ->assertSee('1 sin fecha de auditoría');
    }

    public function test_seo_indice_con_base_vacia_no_explota_ni_muestra_fuentes(): void
    {
        $html = $this->actingAs(User::factory()->create())->get(route('admin.seo.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Auditorías SEO (la más antigua)', $html);
        $this->assertStringNotContainsString('dato-fecha__alerta', $html);
    }

    /** La ficha de Ads exige las cuatro filas de fase del ciclo (igual que AdsController::store). */
    private function campanaAdsConFases(): AdsCampana
    {
        $campana = AdsCampana::factory()->create(['fase_actual' => 'briefing']);
        $campana->briefings()->create(['ciclo' => 1, 'checklist' => []]);
        $campana->configuraciones()->create(['ciclo' => 1, 'checklist' => []]);
        $campana->lanzamientos()->create(['ciclo' => 1, 'checklist' => []]);
        $campana->reportes()->create(['ciclo' => 1, 'checklist' => []]);

        return $campana;
    }

    // --- Ads ----------------------------------------------------------------------

    public function test_ads_indice_muestra_fuente_y_ultimo_periodo(): void
    {
        $campana = AdsCampana::factory()->create();
        AdsMetrica::factory()->create(['ads_campana_id' => $campana->id, 'cliente_id' => $campana->cliente_id, 'mes' => 10, 'anio' => 2026]);

        $this->actingAs(User::factory()->create())->get(route('admin.ads.index'))
            ->assertOk()
            ->assertSee('Fuente: métricas mensuales de Ads (acumulado histórico) · último periodo 10/2026', false);
    }

    public function test_ads_indice_marca_antiguo_el_periodo_de_hace_mas_de_30_dias(): void
    {
        $campana = AdsCampana::factory()->create();
        AdsMetrica::factory()->create(['ads_campana_id' => $campana->id, 'cliente_id' => $campana->cliente_id, 'mes' => 8, 'anio' => 2026]);

        $this->actingAs(User::factory()->create())->get(route('admin.ads.index'))
            ->assertOk()
            ->assertSee('Hace 71 días');
    }

    public function test_ads_detalle_muestra_la_fuente_del_roas_y_sin_metricas_dice_sin_fecha(): void
    {
        $con = $this->campanaAdsConFases();
        AdsMetrica::factory()->create(['ads_campana_id' => $con->id, 'cliente_id' => $con->cliente_id, 'mes' => 11, 'anio' => 2026]);
        $sin = $this->campanaAdsConFases();
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get(route('admin.ads.show', $con))
            ->assertOk()
            ->assertSee('Métricas mensuales de Ads · periodo 11/2026', false);

        $this->actingAs($usuario)->get(route('admin.ads.show', $sin))
            ->assertOk()
            ->assertSee('Métricas mensuales de Ads · Sin fecha de captura', false);
    }

    public function test_ads_indice_con_base_vacia_dice_sin_metricas_capturadas(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.ads.index'))
            ->assertOk()
            ->assertSee('Sin métricas mensuales capturadas');
    }

    // --- Dashboard e Inicio ---------------------------------------------------------

    public function test_dashboard_dice_que_sus_cifras_se_calculan_al_momento_con_su_fuente(): void
    {
        $html = $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Servicios (MRR) · Calculado al momento', $html);
        $this->assertStringContainsString('Finanzas (facturas) · Calculado al momento', $html);
        $this->assertStringNotContainsString('dato-fecha__alerta', $html);
    }

    public function test_inicio_muestra_la_fuente_de_los_kpis_y_no_avisa_sin_datos(): void
    {
        $html = $this->actingAs(User::factory()->create())->get(route('admin.inicio', ['tab' => 'resumen']))->assertOk()->getContent();

        $this->assertStringContainsString('Servicios (MRR) · Calculado al momento', $html);
        $this->assertStringNotContainsString('data-datos-antiguos', $html);
    }

    public function test_inicio_avisa_cuantas_cifras_estan_desactualizadas_y_enlaza_a_la_pantalla(): void
    {
        $this->campanaSeo(['seo_score' => 70, 'fecha_auditoria' => '2026-06-01']);
        $campana = AdsCampana::factory()->create();
        AdsMetrica::factory()->create(['ads_campana_id' => $campana->id, 'cliente_id' => $campana->cliente_id, 'mes' => 8, 'anio' => 2026]);

        $html = $this->actingAs(User::factory()->create())->get(route('admin.inicio', ['tab' => 'resumen']))->assertOk()->getContent();

        $this->assertStringContainsString('data-datos-antiguos', $html);
        $this->assertStringContainsString('2 cifras desactualizadas', $html);
        $this->assertStringContainsString('href="'.route('admin.seo.index').'"', $html);
        $this->assertStringContainsString('href="'.route('admin.ads.index').'"', $html);
    }

    public function test_inicio_no_avisa_de_datos_recientes_ni_de_registros_sin_fecha(): void
    {
        $this->campanaSeo(['seo_score' => 70, 'fecha_auditoria' => '2026-10-20']);
        $sinFecha = $this->campanaSeo();
        DB::table('seo_fase_auditoria')->where('seo_campana_id', $sinFecha->id)->update(['seo_score' => 100]);
        AdsCampana::factory()->create(); // sin métricas = sin dato, no antiguo

        $this->assertSame([], DatosDesactualizados::detectar());
    }

    public function test_datos_desactualizados_cuenta_metricas_y_keywords_antiguas(): void
    {
        $cliente = Cliente::factory()->create();
        $metrica = MetricaCliente::create(['cliente_id' => $cliente->id, 'nombre' => 'Llamadas', 'sentido' => 'mayor', 'umbral_amarillo_pct' => 80]);
        MetricaClienteValor::create(['metrica_id' => $metrica->id, 'periodo' => '2026-08-01', 'valor' => 10]);
        $keyword = Keyword::create(['cliente_id' => $cliente->id, 'keyword' => 'hotel', 'tipo' => 'principal', 'estado' => 'en_uso']);
        KeywordMedicion::create(['keyword_id' => $keyword->id, 'fecha' => '2026-09-01', 'posicion' => 5]);

        $modulos = array_column(DatosDesactualizados::detectar(), 'cantidad', 'modulo');

        $this->assertSame(['metricas' => 1, 'keywords' => 1], $modulos);
    }

    // --- Keywords y Métricas ----------------------------------------------------------

    public function test_keywords_cliente_muestra_fuente_y_fecha_de_la_ultima_medicion(): void
    {
        $cliente = Cliente::factory()->create();
        $keyword = Keyword::create(['cliente_id' => $cliente->id, 'keyword' => 'hotel cancún', 'tipo' => 'principal', 'estado' => 'en_uso']);
        KeywordMedicion::create(['keyword_id' => $keyword->id, 'fecha' => '2026-11-02', 'posicion' => 4]);

        $this->actingAs(User::factory()->create())->get(route('admin.keywords.cliente', $cliente))
            ->assertOk()
            ->assertSee('Mediciones de posición · 02/11/2026');
    }

    public function test_keywords_cliente_sin_mediciones_dice_sin_fecha_de_medicion(): void
    {
        $cliente = Cliente::factory()->create();

        $this->actingAs(User::factory()->create())->get(route('admin.keywords.cliente', $cliente))
            ->assertOk()
            ->assertSee('Mediciones de posición · Sin fecha de medición');
    }

    public function test_metricas_muestra_periodo_y_quien_capturo_cada_valor(): void
    {
        $cliente = Cliente::factory()->create();
        $usuario = User::factory()->create(['name' => 'Ana Torres']);
        $metrica = MetricaCliente::create(['cliente_id' => $cliente->id, 'nombre' => 'Llamadas', 'sentido' => 'mayor', 'umbral_amarillo_pct' => 80, 'fuente' => 'manual']);
        MetricaClienteValor::create(['metrica_id' => $metrica->id, 'periodo' => '2026-10-01', 'valor' => 30, 'registrado_por' => $usuario->id]);

        $this->actingAs($usuario)->get(route('admin.metricas.index', ['cliente' => $cliente->id, 'mes' => '2026-10']))
            ->assertOk()
            ->assertSee('capturó Ana Torres · periodo 10/2026', false);
    }

    public function test_metricas_un_mes_sin_valor_no_muestra_fecha_ni_insignia(): void
    {
        $cliente = Cliente::factory()->create();
        MetricaCliente::create(['cliente_id' => $cliente->id, 'nombre' => 'Llamadas', 'sentido' => 'mayor', 'umbral_amarillo_pct' => 80]);

        $html = $this->actingAs(User::factory()->create())->get(route('admin.metricas.index', ['cliente' => $cliente->id, 'mes' => '2026-03']))->assertOk()->getContent();

        $this->assertStringNotContainsString('dato-fecha__alerta', $html);
        $this->assertStringNotContainsString('periodo 03/2026', $html);
    }

    public function test_metricas_sin_cliente_elegido_no_explota(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.metricas.index'))->assertOk();
    }

    /** Recorta el HTML al primer bloque con esa clase para no confundir insignias de otras secciones. */
    private function soloBloque(string $html, string $clase): string
    {
        $inicio = strpos($html, $clase);

        return $inicio === false ? '' : substr($html, $inicio, 2500);
    }
}
