<?php

namespace Tests\Feature\Admin;

use App\Enums\AreaReporte;
use App\Enums\EstadoReporte;
use App\Models\AdsCampana;
use App\Models\AdsMetrica;
use App\Models\Cliente;
use App\Models\GscMetricaMensual;
use App\Models\GscPropiedad;
use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Models\KeywordMedicion;
use App\Models\MetricaCliente;
use App\Models\MetricaClienteValor;
use App\Models\Reporte;
use App\Models\SeoCampana;
use App\Models\SeoMetricaMensual;
use App\Models\Servicio;
use App\Models\User;
use App\Services\Reportes\CreadorReporte;
use App\Services\Reportes\PublicadorReporte;
use App\Services\Reportes\RenderizadorPdf;
use App\Support\Reportes\Precarga\Ads;
use App\Support\Reportes\Precarga\AplicadorPrecarga;
use App\Support\Reportes\Precarga\Seo;
use App\Support\Reportes\Precarga\SugerenciasResumen;
use App\Support\Reportes\Precarga\Web;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * «Generar reporte del mes»: las tres precargas (con datos y SIN datos), el endpoint de
 * dos pasos, «Actualizar datos» sin pisar lo editado, el folio desde el borrador y que un
 * render fallido no deje el reporte en «listo».
 *
 * Hoy casi todas las fuentes están vacías en la base real, así que el caso "sin dato" pesa
 * tanto como el caso con datos: un cero inventado en un reporte al cliente es un error.
 */
class ReportePrecargaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
        // Se reporta agosto estando en septiembre: el mes recién cerrado.
        Carbon::setTestNow(Carbon::create(2026, 9, 15, 12, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // --- helpers ---------------------------------------------------------

    private function agosto(): Carbon
    {
        return Carbon::create(2026, 8, 1);
    }

    private function cliente(array $extra = []): Cliente
    {
        return Cliente::factory()->create($extra + ['empresa' => 'Hotel Fratelli']);
    }

    private function seoCampana(Cliente $cliente): SeoCampana
    {
        return SeoCampana::create([
            'cliente_id' => $cliente->id,
            'servicio_id' => Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'seo'])->id,
            'nombre' => 'SEO '.uniqid(), 'url_sitio' => 'https://ejemplo.com', 'estado' => 'activa',
            'fase_actual' => 'ejecucion', 'ciclo_actual' => 1, 'fecha_inicio' => '2026-01-01',
        ]);
    }

    private function seoMes(SeoCampana $campana, int $mes, array $extra = []): SeoMetricaMensual
    {
        return SeoMetricaMensual::create($extra + [
            'seo_campana_id' => $campana->id, 'ciclo' => 1, 'mes' => $mes, 'anio' => 2026,
            'trafico_organico' => 1000, 'keywords_top3' => 2, 'keywords_top10' => 5, 'keywords_top100' => 30,
            'backlinks_total' => 50, 'errores_resueltos' => 4, 'errores_pendientes' => 6,
        ]);
    }

    private function adsCampana(Cliente $cliente, array $extra = []): AdsCampana
    {
        return AdsCampana::factory()->create($extra + [
            'cliente_id' => $cliente->id,
            'servicio_id' => Servicio::factory()->create(['cliente_id' => $cliente->id])->id,
        ]);
    }

    private function adsMes(AdsCampana $campana, int $mes, array $extra = []): AdsMetrica
    {
        return AdsMetrica::create($extra + [
            'ads_campana_id' => $campana->id, 'cliente_id' => $campana->cliente_id, 'mes' => $mes, 'anio' => 2026,
            'inversion_real' => 1000, 'impresiones' => 20000, 'clics' => 400, 'conversiones' => 10, 'valor_conversion' => 5000,
        ]);
    }

    /** @return array{0: Keyword, 1: KeywordLista} */
    private function keyword(Cliente $cliente, string $nombre): array
    {
        $lista = KeywordLista::create(['cliente_id' => $cliente->id, 'nombre' => 'Lista '.uniqid(), 'canal' => 'seo', 'estado' => 'en_uso']);
        $kw = Keyword::create(['cliente_id' => $cliente->id, 'lista_id' => $lista->id, 'keyword' => $nombre, 'tipo' => 'principal', 'estado' => 'en_uso']);

        return [$kw, $lista];
    }

    private function medir(Keyword $kw, string $fecha, int $posicion): void
    {
        KeywordMedicion::create(['keyword_id' => $kw->id, 'lista_id' => $kw->lista_id, 'fecha' => $fecha, 'posicion' => $posicion]);
    }

    /** @param  array<string, mixed>  $contenido */
    private function kpi(array $contenido, string $clave): array
    {
        foreach ($contenido['items'] as $item) {
            if ($item['clave'] === $clave) {
                return $item;
            }
        }
        $this->fail("No hay KPI «{$clave}»");
    }

    // --- Precarga SEO ----------------------------------------------------

    public function test_precarga_seo_con_datos_trae_cada_cifra_con_su_fuente_y_su_comparativo(): void
    {
        $cliente = $this->cliente();
        $campana = $this->seoCampana($cliente);
        $this->seoMes($campana, 7, ['trafico_organico' => 1000, 'backlinks_total' => 40]);
        $this->seoMes($campana, 8, ['trafico_organico' => 1200, 'keywords_top3' => 5, 'backlinks_total' => 80]);

        $gsc = GscPropiedad::create(['cliente_id' => $cliente->id, 'site_url' => 'https://ejemplo.com/', 'activa' => true]);
        GscMetricaMensual::create(['propiedad_id' => $gsc->id, 'periodo' => '2026-07-01', 'clics' => 200, 'impresiones' => 9000, 'ctr' => 0.02, 'posicion_media' => 14.0, 'parcial' => false]);
        GscMetricaMensual::create(['propiedad_id' => $gsc->id, 'periodo' => '2026-08-01', 'clics' => 300, 'impresiones' => 10000, 'ctr' => 0.03, 'posicion_media' => 12.5, 'parcial' => false]);

        [$kw1] = $this->keyword($cliente, 'hotel centro');
        [$kw2] = $this->keyword($cliente, 'hotel boutique');
        $this->medir($kw1, '2026-07-30', 10);
        $this->medir($kw1, '2026-08-30', 3);
        $this->medir($kw2, '2026-07-30', 20);
        $this->medir($kw2, '2026-08-30', 15);

        $secciones = (new Seo($cliente, $this->agosto()))->secciones();
        $kpis = $secciones['kpis'];

        $trafico = $this->kpi($kpis, 'trafico_organico');
        $this->assertSame('1200', $trafico['valor']);
        $this->assertSame('+20,0%', $trafico['comparativo']);
        $this->assertSame('positiva', $trafico['direccion']);
        $this->assertSame('vs. julio 2026', $trafico['detalle']);
        $this->assertSame('', $trafico['desactualizado']);
        $this->assertTrue($trafico['precargada']);
        $this->assertNotSame('', $trafico['fuente']);
        $this->assertSame('2026-09-15', $trafico['fecha_dato']);

        $this->assertSame('300', $this->kpi($kpis, 'gsc_clics')['valor']);
        $this->assertSame('+50,0%', $this->kpi($kpis, 'gsc_clics')['comparativo']);
        $this->assertSame('Search Console', $this->kpi($kpis, 'gsc_clics')['fuente']);
        $this->assertSame('0.03', $this->kpi($kpis, 'gsc_ctr')['valor']);
        $this->assertSame('12.5', $this->kpi($kpis, 'gsc_posicion')['valor']);

        // Keywords: top 3 = solo «hotel centro» (3); posición media = (3 + 15) / 2.
        $this->assertSame('1', $this->kpi($kpis, 'kw_top3')['valor']);
        $this->assertSame('9', $this->kpi($kpis, 'kw_posicion_media')['valor']);
        $this->assertSame('+6,0 lugares', $this->kpi($kpis, 'kw_posicion_media')['comparativo']);

        // Serie mensual: solo julio y agosto (los meses sin dato no se dibujan como cero).
        $filas = $secciones['serie']['filas'];
        $this->assertSame(['jul 2026', 'ago 2026'], array_column($filas, 'x'));
        $this->assertSame(1200.0, $filas[1]['valores']['trafico']);
        $this->assertSame(300.0, $filas[1]['valores']['clics']);

        // Movimientos: las dos keywords mejoraron.
        $this->assertSame(['hotel centro', 'hotel boutique'], array_map(fn ($f) => $f['valores']['keyword'], $secciones['movimientos']['filas']));

        // Logros sugeridos con cifras reales, marcados como precargados.
        $logros = array_column($secciones['resumen']['logros'], 'texto');
        $this->assertCount(3, $logros);
        $this->assertStringContainsString('El tráfico orgánico subió 20,0% frente a julio 2026 (de 1,000 a 1,200)', $logros[0]);
        $this->assertTrue($secciones['resumen']['logros'][0]['precargada']);
        $this->assertSame([], $secciones['resumen']['retos']);
        $this->assertCount(2, $secciones['resumen']['grafica']['filas']);
    }

    public function test_precarga_seo_sin_datos_nunca_inventa_ceros(): void
    {
        $secciones = (new Seo($this->cliente(), $this->agosto()))->secciones();

        foreach ($secciones['kpis']['items'] as $item) {
            $this->assertSame('', $item['valor'], "«{$item['clave']}» no debe traer valor sin dato");
            $this->assertSame('Sin dato de agosto 2026', $item['desactualizado'], $item['clave']);
            $this->assertNull($item['fecha_dato']);
            $this->assertNotSame('', $item['fuente']);
            $this->assertSame('', $item['comparativo']);
        }

        $this->assertSame([], $secciones['serie']['filas']);
        $this->assertSame([], $secciones['movimientos']['filas']);
        $this->assertSame([], $secciones['resumen']['logros']);
        $this->assertSame([], $secciones['resumen']['retos']);
        $this->assertSame([], $secciones['resumen']['grafica']['filas']);
    }

    public function test_un_mes_parcial_de_search_console_y_una_medicion_vieja_se_marcan_como_desactualizados(): void
    {
        $cliente = $this->cliente();
        $gsc = GscPropiedad::create(['cliente_id' => $cliente->id, 'site_url' => 'https://ejemplo.com/', 'activa' => true]);
        GscMetricaMensual::create(['propiedad_id' => $gsc->id, 'periodo' => '2026-08-01', 'clics' => 10, 'impresiones' => 100, 'ctr' => 0.1, 'posicion_media' => 8, 'parcial' => true]);
        [$kw] = $this->keyword($cliente, 'hotel');
        $this->medir($kw, '2026-06-20', 7); // ninguna medición de agosto

        $kpis = (new Seo($cliente, $this->agosto()))->secciones()['kpis'];

        $this->assertSame('Mes parcial en Search Console', $this->kpi($kpis, 'gsc_clics')['desactualizado']);
        $this->assertSame('10', $this->kpi($kpis, 'gsc_clics')['valor']);
        $this->assertSame('Medido el 20/06/2026', $this->kpi($kpis, 'kw_top10')['desactualizado']);
        $this->assertSame('2026-06-20', $this->kpi($kpis, 'kw_top10')['fecha_dato']);
    }

    public function test_una_campana_de_otro_cliente_no_entra_en_la_precarga(): void
    {
        $cliente = $this->cliente();
        $otro = Cliente::factory()->create();
        $this->seoMes($this->seoCampana($otro), 8, ['trafico_organico' => 9999]);
        $this->adsMes($this->adsCampana($otro), 8);

        $this->assertSame('', $this->kpi((new Seo($cliente, $this->agosto()))->secciones()['kpis'], 'trafico_organico')['valor']);
        $this->assertSame('', $this->kpi((new Ads($cliente, $this->agosto()))->secciones()['kpis'], 'inversion')['valor']);
    }

    // --- Precarga Ads ----------------------------------------------------

    public function test_precarga_ads_con_datos_recalcula_cpa_roas_y_ctr_desde_las_sumas(): void
    {
        $cliente = $this->cliente();
        $a = $this->adsCampana($cliente, ['nombre' => 'Marca']);
        $b = $this->adsCampana($cliente, ['nombre' => 'Genérica']);
        $this->adsMes($a, 7, ['inversion_real' => 800, 'conversiones' => 5, 'clics' => 300]);
        $this->adsMes($a, 8, ['inversion_real' => 600, 'impresiones' => 10000, 'clics' => 200, 'conversiones' => 4, 'valor_conversion' => 3000]);
        $this->adsMes($b, 8, ['inversion_real' => 400, 'impresiones' => 10000, 'clics' => 200, 'conversiones' => 6, 'valor_conversion' => 2000]);

        $secciones = (new Ads($cliente, $this->agosto()))->secciones();
        $k = $secciones['kpis'];

        $this->assertSame('1000', $this->kpi($k, 'inversion')['valor']);
        $this->assertSame('+25,0%', $this->kpi($k, 'inversion')['comparativo']);
        $this->assertSame('10', $this->kpi($k, 'conversiones')['valor']);
        $this->assertSame('+100,0%', $this->kpi($k, 'conversiones')['comparativo']);
        $this->assertSame('100', $this->kpi($k, 'cpa')['valor']);
        // El CPA bajó (de 160 a 100): para el CPA bajar es bueno.
        $this->assertSame('positiva', $this->kpi($k, 'cpa')['direccion']);
        $this->assertSame('5', $this->kpi($k, 'roas')['valor']);
        $this->assertSame('0.02', $this->kpi($k, 'ctr')['valor']);
        $this->assertSame('5000', $this->kpi($k, 'valor_conversion')['valor']);

        $this->assertCount(2, $secciones['campanas']['filas']);
        $this->assertEqualsCanonicalizing(['Marca', 'Genérica'], array_map(fn ($f) => $f['valores']['campana'], $secciones['campanas']['filas']));
        $this->assertSame(['jul 2026', 'ago 2026'], array_column($secciones['serie']['filas'], 'x'));
        $this->assertSame(1000.0, $secciones['serie']['filas'][1]['valores']['inversion']);

        $this->assertSame([], array_values(array_filter(array_column($secciones['resumen']['retos'], 'texto'), fn ($t) => str_contains($t, 'conversiones'))));
        $this->assertStringContainsString('Las conversiones subió 100,0% frente a julio 2026 (de 5 a 10)', implode(' ', array_column($secciones['resumen']['logros'], 'texto')));
    }

    public function test_precarga_ads_sin_datos_no_inventa_ceros_y_cero_conversiones_no_da_cpa(): void
    {
        $cliente = $this->cliente();

        foreach ((new Ads($cliente, $this->agosto()))->secciones()['kpis']['items'] as $item) {
            $this->assertSame('', $item['valor'], $item['clave']);
            $this->assertSame('Sin dato de agosto 2026', $item['desactualizado'], $item['clave']);
        }

        // Con filas del mes pero 0 conversiones y sin valor: hay dato (no "sin dato"), pero CPA y ROAS no se calculan.
        $this->adsMes($this->adsCampana($cliente), 8, ['conversiones' => 0, 'valor_conversion' => null]);
        $k = (new Ads($cliente, $this->agosto()))->secciones()['kpis'];

        $this->assertSame('', $this->kpi($k, 'cpa')['valor']);
        $this->assertSame('', $this->kpi($k, 'cpa')['desactualizado']);
        $this->assertSame('No calculable: 0 conversiones', $this->kpi($k, 'cpa')['detalle']);
        $this->assertSame('', $this->kpi($k, 'roas')['valor']);
        $this->assertSame('Sin valor de conversión capturado', $this->kpi($k, 'roas')['detalle']);
        $this->assertSame('0', $this->kpi($k, 'conversiones')['valor']); // este SÍ es un cero real: la fila existe
    }

    // --- Precarga Web ----------------------------------------------------

    public function test_precarga_web_evalua_las_metricas_contra_su_meta_y_marca_las_que_no_tienen_valor(): void
    {
        $cliente = $this->cliente();
        $leads = MetricaCliente::create(['cliente_id' => $cliente->id, 'nombre' => 'Leads', 'unidad' => 'leads', 'meta' => 50, 'sentido' => 'mayor']);
        $sin = MetricaCliente::create(['cliente_id' => $cliente->id, 'nombre' => 'Reservas', 'unidad' => '', 'meta' => 10, 'sentido' => 'mayor']);
        MetricaClienteValor::create(['metrica_id' => $leads->id, 'periodo' => '2026-07-01', 'valor' => 40]);
        MetricaClienteValor::create(['metrica_id' => $leads->id, 'periodo' => '2026-08-01', 'valor' => 60]);

        $secciones = (new Web($cliente, $this->agosto()))->secciones();

        $k = $this->kpi($secciones['kpis'], 'metrica_'.$leads->id);
        $this->assertSame('60 leads', $k['valor']);
        $this->assertSame('positiva', $k['direccion']);
        $this->assertSame('Meta: 50 leads', $k['detalle']);
        $this->assertSame('+50,0%', $k['comparativo']);

        $vacio = $this->kpi($secciones['kpis'], 'metrica_'.$sin->id);
        $this->assertSame('', $vacio['valor']);
        $this->assertSame('Sin dato de agosto 2026', $vacio['desactualizado']);

        $estados = array_column(array_column($secciones['metricas']['filas'], 'valores'), 'estado', 'metrica');
        $this->assertSame('En meta', $estados['Leads']);
        $this->assertSame('Sin dato del mes', $estados['Reservas']);
    }

    public function test_precarga_web_sin_metricas_no_inventa_nada(): void
    {
        $s = (new Web($this->cliente(), $this->agosto()))->secciones();

        $this->assertSame([], $s['kpis']['items']);
        $this->assertSame([], $s['metricas']['filas']);
        $this->assertSame([], $s['serie']['filas']);
    }

    // --- Sugerencias del resumen ----------------------------------------

    public function test_las_sugerencias_solo_usan_cifras_reales_y_no_redactan_sin_mes_previo(): void
    {
        $this->assertSame(['logros' => [], 'retos' => []], SugerenciasResumen::generar([]));

        $sinPrevio = SugerenciasResumen::generar(['metricas' => [
            ['label' => 'Los clics', 'actual' => 100, 'previo' => null, 'sube_es_bueno' => true],
            ['label' => 'Las visitas', 'actual' => 100, 'previo' => 0, 'sube_es_bueno' => true],
        ]]);
        $this->assertSame([], $sinPrevio['logros'] + $sinPrevio['retos']);

        $r = SugerenciasResumen::generar([
            'mes_previo_label' => 'julio 2026',
            'metricas' => [
                ['label' => 'El costo por conversión', 'actual' => 80.0, 'previo' => 100.0, 'sube_es_bueno' => false, 'formato' => 'moneda'],
                ['label' => 'Los clics', 'actual' => 90, 'previo' => 100, 'sube_es_bueno' => true],
            ],
        ]);
        $this->assertStringContainsString('bajó 20,0% frente a julio 2026 (de $100.00 a $80.00)', $r['logros'][0]['texto']);
        $this->assertStringContainsString('Los clics bajó 10,0%', $r['retos'][0]['texto']);
    }

    // --- generar-mes -----------------------------------------------------

    private function payload(array $extra = []): array
    {
        return $extra + ['cliente_id' => $this->cliente()->id, 'area' => 'seo', 'mes' => '2026-08'];
    }

    public function test_generar_mes_crea_el_borrador_con_titulo_folio_periodo_y_secciones_precargadas(): void
    {
        $cliente = $this->cliente();
        $this->seoMes($this->seoCampana($cliente), 8, ['trafico_organico' => 1200]);

        $r = $this->actingAs(User::factory()->create())
            ->postJson(route('admin.reportes.generar-mes'), ['cliente_id' => $cliente->id, 'area' => 'seo', 'mes' => '2026-08'])
            ->assertCreated()
            ->assertJsonStructure(['show_url', 'reporte' => ['id', 'titulo', 'numero']]);

        $reporte = Reporte::findOrFail($r->json('reporte.id'));
        $this->assertSame('Reporte SEO — Hotel Fratelli — Agosto 2026', $reporte->titulo);
        $this->assertSame('mes', $reporte->origen);
        $this->assertSame(EstadoReporte::Borrador, $reporte->estado);
        $this->assertSame('REP-SEO-2026-0001', $reporte->numero);
        $this->assertSame('2026-08-01', $reporte->periodo_inicio->toDateString());
        $this->assertSame('2026-08-31', $reporte->periodo_fin->toDateString());
        $this->assertSame('2026-07-01', $reporte->comparativa_inicio->toDateString());
        $this->assertSame('2026-07-31', $reporte->comparativa_fin->toDateString());

        $secciones = $reporte->secciones;
        $this->assertSame('resumen_cliente', $secciones->first()->tipo->value);
        $this->assertSame(
            ['resumen', 'alcance', 'kpis', 'serie_mensual', 'movimientos', 'hallazgos', 'analisis', 'plan', 'metodologia'],
            array_column(\App\Support\Reportes\Plantillas::paraMes(AreaReporte::Seo), 'clave')
        );
        $this->assertCount(9, $secciones);

        $kpis = $secciones->firstWhere('titulo', 'Indicadores del mes');
        $this->assertSame('kpis', $kpis->precarga['clave']);
        $this->assertSame('1200', $this->kpi($kpis->contenido, 'trafico_organico')['valor']);
        // Sin las avanzadas: no hay consultas, países ni auditoría on-page.
        $this->assertNull($secciones->firstWhere('titulo', 'Consultas'));
    }

    public function test_generar_mes_ofrece_las_secciones_avanzadas_como_opcionales(): void
    {
        $user = User::factory()->create();

        $this->assertSame(['tendencia_diaria', 'consultas', 'paginas', 'paises', 'dispositivos', 'indexacion', 'onpage'], array_keys(\App\Support\Reportes\Plantillas::avanzadas(AreaReporte::Seo)));

        $id = $this->actingAs($user)->postJson(route('admin.reportes.generar-mes'), $this->payload(['avanzadas' => ['consultas', 'onpage']]))
            ->assertCreated()->json('reporte.id');

        $titulos = Reporte::findOrFail($id)->secciones->pluck('titulo')->all();
        $this->assertContains('Consultas', $titulos);
        $this->assertContains('Auditoría on-page', $titulos);
        $this->assertNotContains('Países', $titulos);
    }

    public function test_generar_mes_valida_el_periodo_el_cliente_el_area_y_las_avanzadas(): void
    {
        $user = User::factory()->create();
        $url = route('admin.reportes.generar-mes');

        foreach (['2026-13', '2026-8', 'agosto', '2026-08-01', ''] as $malo) {
            $this->actingAs($user)->postJson($url, $this->payload(['mes' => $malo]))->assertUnprocessable()->assertJsonValidationErrors('mes');
        }
        // Un mes que no ha empezado solo tendría huecos.
        $this->actingAs($user)->postJson($url, $this->payload(['mes' => '2026-10']))->assertUnprocessable()->assertJsonValidationErrors('mes');
        // El mes en curso sí se permite.
        $this->actingAs($user)->postJson($url, $this->payload(['mes' => '2026-09']))->assertCreated();

        $this->actingAs($user)->postJson($url, $this->payload(['cliente_id' => 999999]))->assertUnprocessable()->assertJsonValidationErrors('cliente_id');
        $this->actingAs($user)->postJson($url, ['area' => 'seo', 'mes' => '2026-08'])->assertUnprocessable()->assertJsonValidationErrors('cliente_id');
        $this->actingAs($user)->postJson($url, $this->payload(['area' => 'tiktok']))->assertUnprocessable()->assertJsonValidationErrors('area');
        $this->actingAs($user)->postJson($url, $this->payload(['avanzadas' => ['no_existe']]))->assertUnprocessable()->assertJsonValidationErrors('avanzadas');

        $eliminado = $this->cliente();
        $eliminado->delete();
        $this->actingAs($user)->postJson($url, $this->payload(['cliente_id' => $eliminado->id]))->assertUnprocessable()->assertJsonValidationErrors('cliente_id');
    }

    public function test_generar_mes_no_acepta_una_campana_de_otro_cliente(): void
    {
        $ajena = $this->seoCampana(Cliente::factory()->create());

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.reportes.generar-mes'), $this->payload(['seo_campana_id' => $ajena->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('seo_campana_id');
    }

    public function test_generar_mes_dos_veces_el_mismo_mes_no_duplica_ni_gasta_otro_folio(): void
    {
        $user = User::factory()->create();
        $payload = $this->payload();

        $primero = $this->actingAs($user)->postJson(route('admin.reportes.generar-mes'), $payload)->assertCreated();
        $segundo = $this->actingAs($user)->postJson(route('admin.reportes.generar-mes'), $payload)->assertUnprocessable();

        $segundo->assertJsonPath('show_url', $primero->json('show_url'));
        $this->assertSame(1, Reporte::count());
        // Otra área del mismo mes sí es otro reporte.
        $this->actingAs($user)->postJson(route('admin.reportes.generar-mes'), ['area' => 'ads'] + $payload)->assertCreated();
        $this->assertSame(2, Reporte::count());
    }

    public function test_generar_mes_solo_precarga_los_datos_del_cliente_pedido(): void
    {
        $este = $this->cliente(['empresa' => 'Este']);
        $otro = Cliente::factory()->create(['empresa' => 'Otro']);
        $this->seoMes($this->seoCampana($otro), 8, ['trafico_organico' => 7777]);

        $id = $this->actingAs(User::factory()->create())
            ->postJson(route('admin.reportes.generar-mes'), ['cliente_id' => $este->id, 'area' => 'seo', 'mes' => '2026-08'])
            ->json('reporte.id');

        $kpis = Reporte::findOrFail($id)->secciones->firstWhere('titulo', 'Indicadores del mes')->contenido;
        $this->assertSame('', $this->kpi($kpis, 'trafico_organico')['valor']);
        $this->assertStringNotContainsString('7777', json_encode(Reporte::findOrFail($id)->secciones->pluck('contenido')));
    }

    public function test_un_invitado_no_puede_generar_ni_actualizar(): void
    {
        $this->postJson(route('admin.reportes.generar-mes'), $this->payload())->assertUnauthorized();
    }

    // --- actualizar datos ------------------------------------------------

    private function generar(Cliente $cliente, string $area = 'seo'): Reporte
    {
        $id = $this->actingAs(User::factory()->create())
            ->postJson(route('admin.reportes.generar-mes'), ['cliente_id' => $cliente->id, 'area' => $area, 'mes' => '2026-08'])
            ->assertCreated()->json('reporte.id');

        return Reporte::findOrFail($id);
    }

    public function test_actualizar_datos_trae_lo_nuevo_pero_respeta_lo_editado_a_mano(): void
    {
        $cliente = $this->cliente();
        $reporte = $this->generar($cliente);
        $user = User::factory()->create();

        // El equipo corrige a mano un KPI, DESPUÉS de generar, por el mismo autosave del editor.
        $seccionKpis = $reporte->secciones->firstWhere('titulo', 'Indicadores del mes');
        $contenido = $seccionKpis->contenido;
        foreach ($contenido['items'] as &$item) {
            if ($item['clave'] === 'backlinks_total') {
                $item['valor'] = '77';
                $item['desactualizado'] = '';
            }
        }
        unset($item);
        $this->actingAs($user)->putJson(route('admin.reportes.secciones.update', ['reporte' => $reporte, 'seccion' => $seccionKpis]), [
            'titulo' => $seccionKpis->titulo, 'contenido' => $contenido,
        ])->assertOk();

        // Aparecen datos nuevos para el mes (backlinks incluido).
        $this->seoMes($this->seoCampana($cliente), 8, ['trafico_organico' => 1500, 'backlinks_total' => 99]);

        $this->actingAs($user)->postJson(route('admin.reportes.actualizar-datos', $reporte))
            ->assertOk()
            ->assertJsonPath('conservados', 1)
            ->assertJsonStructure(['actualizados', 'conservados', 'sin_dato', 'message']);

        $kpis = $seccionKpis->fresh()->contenido;
        $this->assertSame('1500', $this->kpi($kpis, 'trafico_organico')['valor']);
        $this->assertSame('', $this->kpi($kpis, 'trafico_organico')['desactualizado'] ?? '');
        $this->assertSame('77', $this->kpi($kpis, 'backlinks_total')['valor'], 'lo editado a mano no se pisa');
    }

    public function test_actualizar_datos_no_pisa_logros_ni_series_modificados_y_conserva_destacado(): void
    {
        $cliente = $this->cliente();
        $campana = $this->seoCampana($cliente);
        $this->seoMes($campana, 7, ['trafico_organico' => 1000]);
        $this->seoMes($campana, 8, ['trafico_organico' => 1200]);
        $reporte = $this->generar($cliente);
        $user = User::factory()->create();

        $resumen = $reporte->secciones->firstWhere('titulo', 'Resumen para el cliente');
        $this->assertNotEmpty($resumen->contenido['logros']);
        $contenido = $resumen->contenido;
        $contenido['logros'][0]['texto'] = 'Reescrito por el equipo';
        $contenido['logros'][0]['precargada'] = false;
        $this->actingAs($user)->putJson(route('admin.reportes.secciones.update', ['reporte' => $reporte, 'seccion' => $resumen]), [
            'titulo' => $resumen->titulo, 'contenido' => $contenido,
        ])->assertOk();

        $serie = $reporte->secciones->firstWhere('titulo', 'Evolución mensual');
        $contenidoSerie = $serie->contenido;
        $contenidoSerie['filas'][] = ['x' => 'dato extra', 'valores' => ['trafico' => 5]];
        $this->actingAs($user)->putJson(route('admin.reportes.secciones.update', ['reporte' => $reporte, 'seccion' => $serie]), [
            'titulo' => $serie->titulo, 'contenido' => $contenidoSerie,
        ])->assertOk();

        SeoMetricaMensual::where('seo_campana_id', $campana->id)->where('mes', 8)->update(['trafico_organico' => 2000]);

        $this->actingAs($user)->postJson(route('admin.reportes.actualizar-datos', $reporte))->assertOk();

        $this->assertSame('Reescrito por el equipo', $resumen->fresh()->contenido['logros'][0]['texto']);
        $this->assertContains('dato extra', array_column($serie->fresh()->contenido['filas'], 'x'));
    }

    public function test_actualizar_datos_sin_cambios_no_toca_nada_y_sigue_siendo_idempotente(): void
    {
        $cliente = $this->cliente();
        $this->seoMes($this->seoCampana($cliente), 8);
        $reporte = $this->generar($cliente);
        $antes = $reporte->secciones()->get()->map(fn ($s) => [$s->id, $s->contenido])->all();

        $this->actingAs(User::factory()->create())->postJson(route('admin.reportes.actualizar-datos', $reporte))
            ->assertOk()->assertJsonPath('actualizados', 0)->assertJsonPath('conservados', 0);

        $this->assertEquals($antes, $reporte->secciones()->get()->map(fn ($s) => [$s->id, $s->contenido])->all());
    }

    public function test_actualizar_datos_no_vuelve_a_poner_un_kpi_que_el_equipo_borro(): void
    {
        $cliente = $this->cliente();
        $reporte = $this->generar($cliente);
        $user = User::factory()->create();

        $seccion = $reporte->secciones->firstWhere('titulo', 'Indicadores del mes');
        $contenido = $seccion->contenido;
        $contenido['items'] = array_values(array_filter($contenido['items'], fn ($i) => $i['clave'] !== 'backlinks_total'));
        $this->actingAs($user)->putJson(route('admin.reportes.secciones.update', ['reporte' => $reporte, 'seccion' => $seccion]), [
            'titulo' => $seccion->titulo, 'contenido' => $contenido,
        ])->assertOk();

        $this->actingAs($user)->postJson(route('admin.reportes.actualizar-datos', $reporte))->assertOk();

        $claves = array_column($seccion->fresh()->contenido['items'], 'clave');
        $this->assertNotContains('backlinks_total', $claves);
    }

    public function test_actualizar_datos_solo_funciona_en_borradores_del_mes(): void
    {
        $user = User::factory()->create();
        $reporte = $this->generar($this->cliente());

        $reporte->update(['estado' => EstadoReporte::Listo->value]);
        $this->actingAs($user)->postJson(route('admin.reportes.actualizar-datos', $reporte))->assertUnprocessable();

        $personalizado = $this->actingAs($user)->postJson(route('admin.reportes.store'), [
            'cliente_id' => $this->cliente()->id, 'area' => 'seo', 'titulo' => 'Libre', 'periodo_inicio' => '2026-08-03', 'periodo_fin' => '2026-08-20',
        ])->assertCreated()->json('reporte.id');
        $this->actingAs($user)->postJson(route('admin.reportes.actualizar-datos', $personalizado))->assertUnprocessable();
    }

    public function test_el_reporte_personalizado_conserva_el_modal_de_siempre_sin_precarga_ni_resumen(): void
    {
        $user = User::factory()->create();
        $id = $this->actingAs($user)->postJson(route('admin.reportes.store'), [
            'cliente_id' => $this->cliente()->id, 'area' => 'seo', 'titulo' => 'Libre', 'periodo_inicio' => '2026-08-03', 'periodo_fin' => '2026-08-20',
        ])->assertCreated()->json('reporte.id');

        $reporte = Reporte::findOrFail($id);
        $this->assertSame('personalizado', $reporte->origen);
        $this->assertCount(count(\App\Support\Reportes\Plantillas::para(AreaReporte::Seo)), $reporte->secciones);
        $this->assertNull($reporte->secciones->firstWhere('tipo.value', 'resumen_cliente'));
        $this->assertNull($reporte->secciones->first()->precarga);

        $this->actingAs($user)->get(route('admin.reportes.index'))->assertOk()
            ->assertSee('Generar reporte del mes')->assertSee('Reporte personalizado')->assertSee('data-mes-area="seo"', false);
    }

    // --- folio desde el borrador ----------------------------------------

    public function test_el_folio_se_asigna_al_crear_el_borrador_y_no_se_reutiliza(): void
    {
        $user = User::factory()->create();
        $nuevo = fn () => $this->actingAs($user)->postJson(route('admin.reportes.store'), [
            'cliente_id' => Cliente::factory()->create()->id, 'area' => 'seo', 'titulo' => 'R', 'periodo_inicio' => '2026-08-01', 'periodo_fin' => '2026-08-31',
        ])->assertCreated()->json('reporte.id');

        $a = Reporte::findOrFail($nuevo());
        $b = Reporte::findOrFail($nuevo());
        $this->assertSame('REP-SEO-2026-0001', $a->numero);
        $this->assertSame('REP-SEO-2026-0002', $b->numero);

        // Eliminar el último borrador deja un hueco: el siguiente NO reutiliza su número.
        $b->delete();
        $c = Reporte::findOrFail($nuevo());
        $this->assertSame('REP-SEO-2026-0003', $c->numero);
        // Cada área lleva su serie.
        $ads = Reporte::findOrFail($this->actingAs($user)->postJson(route('admin.reportes.store'), [
            'cliente_id' => Cliente::factory()->create()->id, 'area' => 'ads', 'titulo' => 'R', 'periodo_inicio' => '2026-08-01', 'periodo_fin' => '2026-08-31',
        ])->json('reporte.id'));
        $this->assertSame('REP-ADS-2026-0001', $ads->numero);
    }

    public function test_el_folio_se_calcula_bajo_bloqueo(): void
    {
        $consultas = [];
        DB::listen(function ($q) use (&$consultas) {
            $consultas[] = strtolower($q->sql);
        });

        $this->generar($this->cliente());

        $this->assertNotEmpty(array_filter($consultas, fn ($sql) => str_contains($sql, 'from `reportes`') && str_contains($sql, 'for update')));
    }

    public function test_una_carrera_por_el_mismo_folio_se_reintenta_con_el_siguiente(): void
    {
        $publicador = new class extends PublicadorReporte {
            public int $intentos = 0;

            public function asignarFolio(Reporte $reporte): void
            {
                // La primera vez simula perder la carrera contra el índice único de `numero`.
                if (++$this->intentos === 1) {
                    throw new QueryException('mysql', 'insert', [], new class('Duplicate entry') extends \PDOException {
                        protected $code = '23000';
                    });
                }
                parent::asignarFolio($reporte);
            }
        };

        $reporte = (new CreadorReporte($publicador))->crearDelMes($this->cliente(), AreaReporte::Seo, $this->agosto(), [], null);

        $this->assertSame(2, $publicador->intentos);
        $this->assertSame('REP-SEO-2026-0001', $reporte->numero);
        // El intento perdido hizo rollback completo: ni reporte ni secciones huérfanas.
        $this->assertSame(1, Reporte::count());
    }

    public function test_generar_el_pdf_conserva_el_folio_del_borrador(): void
    {
        $reporte = $this->generar($this->cliente());
        $folio = $reporte->numero;

        $this->actingAs(User::factory()->create())->post(route('admin.reportes.pdf', $reporte))->assertOk();

        $this->assertSame($folio, $reporte->fresh()->numero);
        $this->assertSame(EstadoReporte::Listo, $reporte->fresh()->estado);
    }

    public function test_un_reporte_anterior_al_cambio_sin_folio_lo_recibe_al_generar(): void
    {
        $reporte = Reporte::create([
            'cliente_id' => $this->cliente()->id, 'area' => 'seo', 'titulo' => 'Viejo',
            'periodo_inicio' => '2026-08-01', 'periodo_fin' => '2026-08-31', 'estado' => 'borrador',
        ]);
        $this->assertNull($reporte->numero);

        $this->actingAs(User::factory()->create())->post(route('admin.reportes.pdf', $reporte))->assertOk();

        $this->assertSame('REP-SEO-2026-0001', $reporte->fresh()->numero);
    }

    // --- render fallido --------------------------------------------------

    private function renderizadorQueFalla(): void
    {
        $this->app->bind(RenderizadorPdf::class, fn () => new class extends RenderizadorPdf {
            public function generar(Reporte $reporte): string
            {
                throw new \RuntimeException('el render reventó');
            }
        });
    }

    public function test_si_el_render_falla_el_reporte_no_queda_listo_y_conserva_su_folio(): void
    {
        $reporte = $this->generar($this->cliente());
        $folio = $reporte->numero;
        $this->renderizadorQueFalla();
        $this->withoutExceptionHandling();

        try {
            $this->actingAs(User::factory()->create())->post(route('admin.reportes.pdf', $reporte));
            $this->fail('El render debía lanzar.');
        } catch (\RuntimeException $e) {
            $this->assertSame('el render reventó', $e->getMessage());
        }

        $fresco = $reporte->fresh();
        $this->assertSame(EstadoReporte::Borrador, $fresco->estado);
        $this->assertNull($fresco->fecha_emision);
        $this->assertSame($folio, $fresco->numero);
        $this->assertSame(0, $fresco->entregas()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_un_render_fallido_no_degrada_un_reporte_que_ya_tenia_entregas(): void
    {
        $reporte = $this->generar($this->cliente());
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('admin.reportes.xlsx', $reporte))->assertOk();
        $this->assertSame(EstadoReporte::Listo, $reporte->fresh()->estado);

        $this->renderizadorQueFalla();
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($user)->post(route('admin.reportes.pdf', $reporte));
        } catch (\RuntimeException) {
        }

        $this->assertSame(EstadoReporte::Listo, $reporte->fresh()->estado);
    }

    public function test_tras_un_render_fallido_el_reintento_funciona_y_pasa_a_listo(): void
    {
        $reporte = $this->generar($this->cliente());
        $user = User::factory()->create();
        $publicador = app(PublicadorReporte::class);

        $this->renderizadorQueFalla();
        try {
            $publicador->publicar($reporte, 'pdf');
        } catch (\RuntimeException) {
        }
        $this->assertSame(EstadoReporte::Borrador, $reporte->fresh()->estado);

        $this->app->forgetInstance(RenderizadorPdf::class);
        $this->app->bind(RenderizadorPdf::class, fn () => new RenderizadorPdf);
        $this->actingAs($user)->post(route('admin.reportes.pdf', $reporte))->assertOk();

        $this->assertSame(EstadoReporte::Listo, $reporte->fresh()->estado);
        $this->assertSame(1, $reporte->fresh()->entregas()->count());
    }
}
