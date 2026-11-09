<?php

namespace Tests\Unit\Support\Tablero;

use App\Models\AdsCampana;
use App\Models\AdsClic;
use App\Models\AdsConversion;
use App\Models\AdsMetrica;
use App\Models\BuyerPersona;
use App\Models\Cliente;
use App\Models\ElementoContenido;
use App\Models\Finanza;
use App\Models\GscMetricaMensual;
use App\Models\GscPropiedad;
use App\Models\IdentidadMarca;
use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Models\KeywordMedicion;
use App\Models\MetricaCliente;
use App\Models\MetricaClienteValor;
use App\Models\Proyecto;
use App\Models\SeoCampana;
use App\Models\SeoMetricaMensual;
use App\Models\Servicio;
use App\Models\Tarea;
use App\Models\User;
use App\Support\Tablero\Bloques\Ads;
use App\Support\Tablero\Bloques\Bloque;
use App\Support\Tablero\Bloques\Metricas;
use App\Support\Tablero\ResumenCliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * El cálculo del Resumen de cliente sin HTTP: cada bloque con datos y sin datos, las reglas deterministas del
 * semáforo (contrato vencido, pagos vencidos, captura incompleta, dato viejo) y que «sin dato» es gris, nunca verde.
 */
class ResumenClienteTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $hoy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hoy = Carbon::parse('2026-11-08 12:00:00');
        Carbon::setTestNow($this->hoy);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function resumen(Cliente $cliente, ?User $usuario = null): array
    {
        return (new ResumenCliente($this->hoy))->calcular($cliente, $usuario);
    }

    private function bloque(Cliente $cliente, string $clave): array
    {
        return $this->resumen($cliente)['bloques'][$clave];
    }

    private function cifra(array $bloque, string $etiqueta): array
    {
        $c = collect($bloque['cifras'])->firstWhere('etiqueta', $etiqueta);
        $this->assertNotNull($c, "No hay cifra «{$etiqueta}» en el bloque {$bloque['clave']}");

        return $c;
    }

    /** Fija updated_at sin que Eloquent lo pise. */
    private function envejecer(string $tabla, int $id, string $fecha): void
    {
        DB::table($tabla)->where('id', $id)->update(['updated_at' => $fecha]);
    }

    private function servicio(Cliente $cliente, array $extra = []): Servicio
    {
        return Servicio::factory()->create($extra + ['cliente_id' => $cliente->id]);
    }

    private function campanaSeo(Cliente $cliente): SeoCampana
    {
        return SeoCampana::create([
            'cliente_id' => $cliente->id,
            'servicio_id' => $this->servicio($cliente, ['tipo' => 'seo'])->id,
            'nombre' => 'SEO principal', 'url_sitio' => 'https://ejemplo.com', 'estado' => 'activa',
            'fase_actual' => 'ejecucion', 'ciclo_actual' => 1, 'fecha_inicio' => '2026-01-01',
        ]);
    }

    private function campanaAds(Cliente $cliente, string $plataforma = 'google_ads'): AdsCampana
    {
        return AdsCampana::factory()->create([
            'cliente_id' => $cliente->id,
            'servicio_id' => $this->servicio($cliente)->id,
            'plataforma' => $plataforma,
            'fecha_inicio' => '2026-01-01',
        ]);
    }

    private function listaConKeyword(Cliente $cliente, string $nombre = 'kw'): array
    {
        $lista = KeywordLista::create(['cliente_id' => $cliente->id, 'nombre' => 'Lista', 'canal' => 'seo', 'estado' => 'en_uso']);
        $kw = Keyword::create(['cliente_id' => $cliente->id, 'lista_id' => $lista->id, 'keyword' => $nombre, 'tipo' => 'principal', 'estado' => 'en_uso']);

        return [$lista, $kw];
    }

    // --- forma común ---------------------------------------------------------

    public function test_cada_bloque_cumple_el_contrato_comun(): void
    {
        $r = $this->resumen(Cliente::factory()->create());

        $this->assertSame(['salud', 'alertas', 'seo', 'keywords', 'google_ads', 'meta_ads', 'conversiones', 'metricas', 'audiencias', 'contenido'], array_keys($r['bloques']));
        foreach ($r['bloques'] as $clave => $b) {
            $this->assertSame($clave, $b['clave']);
            $this->assertContains($b['estado_semaforo'], ['verde', 'amarillo', 'rojo', 'neutro']);
            $this->assertIsArray($b['cifras']);
            $this->assertArrayHasKey('enlace', $b);
            $this->assertNotSame('', $b['motivo']);
            foreach ($b['cifras'] as $c) {
                foreach (['etiqueta', 'valor', 'fecha', 'fuente'] as $campo) {
                    $this->assertArrayHasKey($campo, $c);
                }
                $this->assertNotSame('', $c['fuente'], "Cifra sin fuente en {$clave}");
            }
        }
    }

    // --- sin dato: gris, nunca verde ------------------------------------------

    public function test_cliente_sin_datos_deja_todo_en_neutro_y_nada_en_verde_por_no_tener_datos(): void
    {
        $r = $this->resumen(Cliente::factory()->create());

        foreach (['seo', 'keywords', 'google_ads', 'meta_ads', 'conversiones', 'metricas', 'audiencias', 'contenido'] as $clave) {
            $this->assertSame('neutro', $r['bloques'][$clave]['estado_semaforo'], "{$clave} debería ser neutro sin datos");
            $this->assertTrue($r['bloques'][$clave]['sin_dato']);
            // Audiencia y Contenido muestran «Ninguna» como cifra explícita; los demás no tienen nada que mostrar.
            if (! in_array($clave, ['audiencias', 'contenido'], true)) {
                $this->assertSame([], $r['bloques'][$clave]['cifras']);
            }
        }
        $this->assertSame('Sin integración', $r['bloques']['meta_ads']['leyenda']);
        $this->assertSame('Sin dato', $r['bloques']['google_ads']['leyenda']);
        $this->assertSame(8, $r['conteo']['neutro']);
    }

    // --- Salud ---------------------------------------------------------------

    public function test_salud_verde_con_contrato_vigente_sin_pagos_vencidos(): void
    {
        $cliente = Cliente::factory()->create(['fecha_renovacion_contrato' => '2027-01-15']);
        $this->servicio($cliente, ['precio_mensual' => 5000]);
        $this->servicio($cliente, ['precio_mensual' => 3000, 'tipo_cobro' => 'unico']);

        $b = $this->bloque($cliente, 'salud');

        $this->assertSame('verde', $b['estado_semaforo']);
        // El MRR sale de FinanzasMetrics: el servicio de pago único no entra.
        $this->assertSame('$5,000.00 / mes', $this->cifra($b, 'MRR')['valor']);
        $this->assertSame('Vence en 68 días', $this->cifra($b, 'Renovación del contrato')['valor']);
        $this->assertSame('15/01/2027', $this->cifra($b, 'Renovación del contrato')['fecha']);
    }

    public function test_contrato_vencido_de_cliente_activo_es_rojo(): void
    {
        $cliente = Cliente::factory()->create(['fecha_renovacion_contrato' => '2026-10-20']);

        $b = $this->bloque($cliente, 'salud');

        $this->assertSame('rojo', $b['estado_semaforo']);
        $this->assertStringContainsString('Contrato vencido', $b['motivo']);
        $this->assertSame('Vencido hace 19 días', $this->cifra($b, 'Renovación del contrato')['valor']);
    }

    public function test_cliente_pausado_con_contrato_pasado_no_es_rojo_ni_verde(): void
    {
        $cliente = Cliente::factory()->create(['estado' => 'pausado', 'fecha_renovacion_contrato' => '2026-10-20']);

        $this->assertSame('neutro', $this->bloque($cliente, 'salud')['estado_semaforo']);
    }

    public function test_sin_fecha_de_renovacion_la_salud_es_neutra_y_no_verde(): void
    {
        $cliente = Cliente::factory()->create(['fecha_renovacion_contrato' => null]);

        $b = $this->bloque($cliente, 'salud');

        $this->assertSame('neutro', $b['estado_semaforo']);
        $this->assertNull($this->cifra($b, 'Renovación del contrato')['valor']);
    }

    public function test_pagos_vencidos_son_rojo_ya_sean_estado_vencido_o_pendientes_con_fecha_pasada(): void
    {
        $cliente = Cliente::factory()->create();
        $otro = Cliente::factory()->create();
        $f = fn (array $extra) => Finanza::create(array_merge(['tipo' => 'ingreso', 'concepto' => 'Mensualidad', 'monto' => 1000, 'fecha_emision' => '2026-09-01', 'mes' => 9, 'anio' => 2026], $extra));
        $f(['cliente_id' => $cliente->id, 'estado' => 'vencido', 'fecha_vencimiento' => '2026-09-10']);
        $f(['cliente_id' => $cliente->id, 'estado' => 'pendiente', 'fecha_vencimiento' => '2026-10-05', 'monto' => 500]);
        // No cuentan: pendiente con fecha futura, pagado, un gasto, y el ingreso de otro cliente.
        $f(['cliente_id' => $cliente->id, 'estado' => 'pendiente', 'fecha_vencimiento' => '2026-12-01']);
        $f(['cliente_id' => $cliente->id, 'estado' => 'pagado', 'fecha_vencimiento' => '2026-09-10', 'fecha_pago' => '2026-09-09']);
        $f(['cliente_id' => $cliente->id, 'estado' => 'vencido', 'tipo' => 'gasto', 'fecha_vencimiento' => '2026-09-10']);
        $f(['cliente_id' => $otro->id, 'estado' => 'vencido', 'fecha_vencimiento' => '2026-09-10']);

        $b = $this->bloque($cliente, 'salud');

        $this->assertSame('rojo', $b['estado_semaforo']);
        $c = $this->cifra($b, 'Pagos vencidos');
        $this->assertSame('2 · $1,500.00', $c['valor']);
        $this->assertSame('10/09/2026', $c['fecha']);
    }

    public function test_tareas_vencidas_dejan_la_salud_en_amarillo(): void
    {
        $cliente = Cliente::factory()->create();
        $proyecto = Proyecto::create(['cliente_id' => $cliente->id, 'nombre' => 'Web', 'tipo' => 'web_nueva', 'fase_actual' => 'planeacion']);
        Tarea::create(['proyecto_id' => $proyecto->id, 'titulo' => 'tarde', 'estado' => 'pendiente', 'fecha_limite' => '2026-11-01']);

        $b = $this->bloque($cliente, 'salud');

        $this->assertSame('amarillo', $b['estado_semaforo']);
        $this->assertSame('1 de 1 abiertas', $this->cifra($b, 'Tareas vencidas')['valor']);
    }

    public function test_captura_del_mes_incompleta_es_amarillo_y_completa_no_lo_es(): void
    {
        $cliente = Cliente::factory()->create();
        [$lista, $kw] = $this->listaConKeyword($cliente);
        $lista->forceFill(['created_at' => '2026-01-01 00:00:00'])->saveQuietly();

        $b = $this->bloque($cliente, 'salud');
        $this->assertSame('amarillo', $b['estado_semaforo']);
        $this->assertSame('1 de 1 fuentes pendientes', $this->cifra($b, 'Captura del mes')['valor']);
        $this->assertSame('noviembre 2026', $this->cifra($b, 'Captura del mes')['fecha']);

        KeywordMedicion::create(['keyword_id' => $kw->id, 'lista_id' => $lista->id, 'fecha' => '2026-11-03', 'posicion' => 7]);

        $b = $this->bloque($cliente, 'salud');
        $this->assertSame('verde', $b['estado_semaforo']);
        $this->assertSame('Completa (1 fuentes)', $this->cifra($b, 'Captura del mes')['valor']);
    }

    // --- SEO -----------------------------------------------------------------

    public function test_seo_con_campana_pero_sin_metricas_es_neutro(): void
    {
        $cliente = Cliente::factory()->create();
        $this->campanaSeo($cliente);

        $b = $this->bloque($cliente, 'seo');

        $this->assertSame('neutro', $b['estado_semaforo']);
        $this->assertStringContainsString('ninguna tiene métricas', $b['motivo']);
    }

    public function test_seo_muestra_el_mes_mas_reciente_con_fecha_y_fuente_y_pasa_a_amarillo_si_esta_viejo(): void
    {
        $cliente = Cliente::factory()->create();
        $campana = $this->campanaSeo($cliente);
        SeoMetricaMensual::create(['seo_campana_id' => $campana->id, 'ciclo' => 1, 'mes' => 8, 'anio' => 2026, 'trafico_organico' => 100]);
        $reciente = SeoMetricaMensual::create(['seo_campana_id' => $campana->id, 'ciclo' => 1, 'mes' => 10, 'anio' => 2026, 'trafico_organico' => 1200, 'keywords_top3' => 4, 'keywords_top10' => 15, 'backlinks_total' => 30, 'errores_pendientes' => 2]);

        $b = $this->bloque($cliente, 'seo');

        $this->assertSame('verde', $b['estado_semaforo']);
        $trafico = $this->cifra($b, 'Tráfico orgánico');
        $this->assertSame('1,200', $trafico['valor']);
        $this->assertSame('octubre 2026', $trafico['fecha']);
        $this->assertStringContainsString('Módulo SEO', $trafico['fuente']);
        $this->assertSame('08/11/2026', $trafico['capturado']);

        $this->envejecer('seo_metricas_mensuales', $reciente->id, '2026-09-20 10:00:00');
        $this->envejecer('seo_metricas_mensuales', SeoMetricaMensual::where('mes', 8)->value('id'), '2026-09-20 10:00:00');

        $viejo = $this->bloque($cliente, 'seo');
        $this->assertSame('amarillo', $viejo['estado_semaforo']);
        $this->assertStringContainsString('20/09/2026', $viejo['motivo']);
    }

    public function test_seo_marca_el_mes_parcial_de_search_console_y_avisa_si_la_sincronizacion_fallo(): void
    {
        $cliente = Cliente::factory()->create();
        $propiedad = GscPropiedad::create(['cliente_id' => $cliente->id, 'site_url' => 'https://ejemplo.com/', 'activa' => true, 'ultima_sync_en' => '2026-11-07 04:30:00', 'ultimo_ok' => true]);
        GscMetricaMensual::create(['propiedad_id' => $propiedad->id, 'periodo' => '2026-10-01', 'clics' => 900, 'impresiones' => 20000, 'ctr' => 0.045, 'posicion_media' => 14.2, 'parcial' => false]);
        GscMetricaMensual::create(['propiedad_id' => $propiedad->id, 'periodo' => '2026-11-01', 'clics' => 150, 'impresiones' => 3000, 'ctr' => 0.05, 'posicion_media' => 13.9, 'parcial' => true]);

        $b = $this->bloque($cliente, 'seo');

        $clics = $this->cifra($b, 'Clics (Search Console)');
        $this->assertSame('150', $clics['valor']);
        $this->assertTrue($clics['parcial']);
        $this->assertSame('noviembre 2026', $clics['fecha']);
        $this->assertSame('07/11/2026', $clics['capturado']);
        $this->assertSame('verde', $b['estado_semaforo']);

        $propiedad->update(['ultimo_ok' => false]);
        $this->assertSame('amarillo', $this->bloque($cliente, 'seo')['estado_semaforo']);
    }

    // --- Keywords ------------------------------------------------------------

    public function test_keywords_sin_keywords_o_sin_mediciones_es_neutro(): void
    {
        $cliente = Cliente::factory()->create();
        $this->assertSame('neutro', $this->bloque($cliente, 'keywords')['estado_semaforo']);

        $this->listaConKeyword($cliente);
        $b = $this->bloque($cliente, 'keywords');
        $this->assertSame('neutro', $b['estado_semaforo']);
        $this->assertStringContainsString('ninguna con medición', $b['motivo']);
    }

    public function test_keywords_usa_el_analisis_del_tablero_y_se_pone_amarillo_con_ronda_vieja(): void
    {
        $cliente = Cliente::factory()->create();
        $usuario = User::factory()->create(['name' => 'Ana Capturista']);
        [$lista, $kw] = $this->listaConKeyword($cliente);
        KeywordMedicion::create(['keyword_id' => $kw->id, 'lista_id' => $lista->id, 'fecha' => '2026-10-01', 'posicion' => 12]);
        KeywordMedicion::create(['keyword_id' => $kw->id, 'lista_id' => $lista->id, 'fecha' => '2026-11-02', 'posicion' => 3, 'registrado_por' => $usuario->id]);

        $b = $this->bloque($cliente, 'keywords');

        $this->assertSame('verde', $b['estado_semaforo']);
        $this->assertSame('1 de 1 (100 %)', $this->cifra($b, 'En el top 3')['valor']);
        $this->assertSame('#3', $this->cifra($b, 'Posición media')['valor']);
        $this->assertSame('Mejoró 9 posiciones', $this->cifra($b, 'Variación de la posición media')['valor']);
        $this->assertSame('02/11/2026', $this->cifra($b, 'Posición media')['fecha']);
        $this->assertSame('Ana Capturista', $this->cifra($b, 'Posición media')['registrado_por']);

        // Cerca del límite: 30 días exactos todavía están al día, 31 ya no.
        Carbon::setTestNow($this->hoy = Carbon::parse('2026-12-02 12:00:00'));
        $this->assertSame('verde', $this->bloque($cliente, 'keywords')['estado_semaforo']);
        Carbon::setTestNow($this->hoy = Carbon::parse('2026-12-03 12:00:00'));
        $this->assertSame('amarillo', $this->bloque($cliente, 'keywords')['estado_semaforo']);
    }

    // --- Ads -----------------------------------------------------------------

    public function test_google_ads_con_campana_sin_metricas_es_neutro_y_meta_sigue_sin_integracion(): void
    {
        $cliente = Cliente::factory()->create();
        $this->campanaAds($cliente);

        $r = $this->resumen($cliente)['bloques'];

        $this->assertSame('neutro', $r['google_ads']['estado_semaforo']);
        $this->assertStringContainsString('ninguna tiene métricas', $r['google_ads']['motivo']);
        $this->assertSame('Sin integración', $r['meta_ads']['leyenda']);
    }

    public function test_google_ads_calcula_cpa_y_roas_sin_dividir_entre_cero_y_filtra_por_plataforma(): void
    {
        $cliente = Cliente::factory()->create();
        $google = $this->campanaAds($cliente, 'google_ads');
        $meta = $this->campanaAds($cliente, 'meta_ads');
        AdsMetrica::factory()->create(['ads_campana_id' => $google->id, 'cliente_id' => $cliente->id, 'mes' => 10, 'anio' => 2026, 'inversion_real' => 2000, 'clics' => 200, 'conversiones' => 10, 'valor_conversion' => 8000, 'roas' => 4]);
        AdsMetrica::factory()->create(['ads_campana_id' => $meta->id, 'cliente_id' => $cliente->id, 'mes' => 10, 'anio' => 2026, 'inversion_real' => 999, 'clics' => 7, 'conversiones' => 0, 'valor_conversion' => 0, 'roas' => null]);

        $r = $this->resumen($cliente)['bloques'];

        $g = $r['google_ads'];
        $this->assertSame('verde', $g['estado_semaforo']);
        $this->assertSame('$2,000.00', $this->cifra($g, 'Inversión')['valor']);
        $this->assertSame('$200.00', $this->cifra($g, 'CPA')['valor']);
        $this->assertSame('4.00x', $this->cifra($g, 'ROAS')['valor']);
        $this->assertSame('octubre 2026', $this->cifra($g, 'Clics')['fecha']);

        // Meta con métricas manuales: se muestran sus cifras, sin mezclarlas con las de Google, y sin conversiones no hay CPA.
        $m = $r['meta_ads'];
        $this->assertSame('$999.00', $this->cifra($m, 'Inversión')['valor']);
        $this->assertNull($this->cifra($m, 'CPA')['valor']);
        $this->assertNull($this->cifra($m, 'ROAS')['valor']);
        $this->assertStringContainsString('Meta Ads', $this->cifra($m, 'Inversión')['fuente']);
    }

    public function test_ads_con_captura_de_hace_mas_de_30_dias_es_amarillo(): void
    {
        $cliente = Cliente::factory()->create();
        $m = AdsMetrica::factory()->create(['ads_campana_id' => $this->campanaAds($cliente)->id, 'cliente_id' => $cliente->id, 'mes' => 9, 'anio' => 2026]);
        $this->envejecer('ads_metricas', $m->id, '2026-10-01 10:00:00');

        $this->assertSame('amarillo', $this->bloque($cliente, 'google_ads')['estado_semaforo']);
    }

    public function test_resumir_ads_nunca_divide_entre_cero(): void
    {
        $r = Ads::resumir([(object) ['inversion_real' => 0, 'clics' => 0, 'conversiones' => 0, 'valor_conversion' => 0, 'roas' => null]]);

        $this->assertNull($r['cpa']);
        $this->assertNull($r['roas']);
        $this->assertSame(0, $r['conversiones']);
    }

    // --- Conversiones --------------------------------------------------------

    public function test_conversiones_sin_eventos_neutro_con_recientes_verde_y_con_viejos_amarillo(): void
    {
        $cliente = Cliente::factory()->create();
        $this->assertSame('neutro', $this->bloque($cliente, 'conversiones')['estado_semaforo']);

        $clic = AdsClic::create(['cliente_id' => $cliente->id, 'visitor_id' => 'v1', 'landing_url' => 'https://ejemplo.com']);
        $conv = AdsConversion::create(['cliente_id' => $cliente->id, 'ads_clic_id' => $clic->id, 'visitor_id' => 'v1', 'tipo' => 'whatsapp', 'valor' => 100, 'estado' => 'pendiente']);

        $b = $this->bloque($cliente, 'conversiones');
        $this->assertSame('verde', $b['estado_semaforo']);
        $this->assertSame('1', $this->cifra($b, 'Clics registrados')['valor']);
        $this->assertSame('08/11/2026', $this->cifra($b, 'Clics registrados')['fecha']);
        $this->assertSame('whatsapp 1', $this->cifra($b, 'Conversiones por tipo')['valor']);
        $this->assertSame('1', $this->cifra($b, 'Pendientes de exportar')['valor']);

        DB::table('ads_clics')->where('id', $clic->id)->update(['created_at' => '2026-09-01 10:00:00']);
        DB::table('ads_conversiones')->where('id', $conv->id)->update(['created_at' => '2026-09-02 10:00:00']);

        $this->assertSame('amarillo', $this->bloque($cliente, 'conversiones')['estado_semaforo']);
    }

    // --- Audiencias ----------------------------------------------------------

    public function test_audiencias_mide_presencia_de_personas_e_identidad(): void
    {
        $cliente = Cliente::factory()->create();
        $this->assertSame('neutro', $this->bloque($cliente, 'audiencias')['estado_semaforo']);

        // Una identidad vacía (fila sin nada capturado) no cuenta como definida.
        IdentidadMarca::create(['cliente_id' => $cliente->id]);
        $this->assertSame('neutro', $this->bloque($cliente, 'audiencias')['estado_semaforo']);

        BuyerPersona::create(['cliente_id' => $cliente->id, 'nombre' => 'Laura', 'orden' => 0]);
        $b = $this->bloque($cliente, 'audiencias');
        $this->assertSame('amarillo', $b['estado_semaforo']);
        $this->assertStringContainsString('identidad de marca', $b['motivo']);
        $this->assertTrue($b['requiere_cliente_activo']);

        IdentidadMarca::where('cliente_id', $cliente->id)->update(['slogan' => 'Hospitalidad real']);
        $b = $this->bloque($cliente, 'audiencias');
        $this->assertSame('verde', $b['estado_semaforo']);
        $this->assertSame('1', $this->cifra($b, 'Buyer personas')['valor']);
        $this->assertSame('Definida', $this->cifra($b, 'Identidad de marca')['valor']);
    }

    // --- Contenido y desarrollo ----------------------------------------------

    public function test_contenido_y_desarrollo_neutro_sin_nada_verde_con_trabajo_y_amarillo_con_tareas_vencidas(): void
    {
        $cliente = Cliente::factory()->create();
        $this->assertSame('neutro', $this->bloque($cliente, 'contenido')['estado_semaforo']);

        ElementoContenido::create(['cliente_id' => $cliente->id, 'tipo' => 'documento', 'nombre' => 'Brief']);
        $proyecto = Proyecto::create(['cliente_id' => $cliente->id, 'nombre' => 'Web', 'tipo' => 'web_nueva', 'fase_actual' => 'planeacion', 'estado' => 'activo', 'porcentaje_avance' => 40]);

        $b = $this->bloque($cliente, 'contenido');
        $this->assertSame('verde', $b['estado_semaforo']);
        $this->assertSame('1', $this->cifra($b, 'Elementos de contenido')['valor']);
        $this->assertSame('40 %', $this->cifra($b, 'Avance promedio')['valor']);

        Tarea::create(['proyecto_id' => $proyecto->id, 'titulo' => 'tarde', 'estado' => 'pendiente', 'fecha_limite' => '2026-11-01']);
        $this->assertSame('amarillo', $this->bloque($cliente, 'contenido')['estado_semaforo']);
    }

    // --- Alertas -------------------------------------------------------------

    public function test_alertas_son_solo_del_cliente_y_el_rojo_viene_de_pagos_vencidos(): void
    {
        $cliente = Cliente::factory()->create(['nombre' => 'Hotel A', 'empresa' => 'Hotel A', 'fecha_renovacion_contrato' => '2027-06-01']);
        $otro = Cliente::factory()->create(['nombre' => 'Hotel B', 'empresa' => 'Hotel B', 'fecha_renovacion_contrato' => '2026-11-20']);
        $base = ['tipo' => 'ingreso', 'concepto' => 'Mensualidad', 'monto' => 800, 'fecha_emision' => '2026-09-01', 'mes' => 9, 'anio' => 2026, 'estado' => 'vencido'];

        $this->assertSame('verde', $this->bloque($cliente, 'alertas')['estado_semaforo']);
        $this->assertSame([], $this->bloque($cliente, 'alertas')['cifras']);

        Finanza::create($base + ['cliente_id' => $otro->id]);
        $this->assertSame([], $this->bloque($cliente, 'alertas')['cifras'], 'Las alertas de otro cliente no deben filtrarse');

        Finanza::create($base + ['cliente_id' => $cliente->id]);
        $b = $this->bloque($cliente, 'alertas');
        $this->assertSame('rojo', $b['estado_semaforo']);
        $this->assertCount(1, $b['cifras']);
        $this->assertStringContainsString('Hotel A', $b['cifras'][0]['etiqueta']);
        $this->assertStringNotContainsString('Hotel B', $b['cifras'][0]['etiqueta']);
    }

    // --- Métricas propias: el único bloque con meta --------------------------

    public function test_metricas_con_meta_usan_el_semaforo_y_sin_meta_son_neutras(): void
    {
        $cliente = Cliente::factory()->create();
        $usuario = User::factory()->create(['name' => 'Beto']);
        $this->assertSame('neutro', $this->bloque($cliente, 'metricas')['estado_semaforo']);

        $llamadas = MetricaCliente::create(['cliente_id' => $cliente->id, 'nombre' => 'Llamadas', 'unidad' => 'llamadas', 'meta' => 100, 'sentido' => 'mayor', 'umbral_amarillo_pct' => 80]);
        $libre = MetricaCliente::create(['cliente_id' => $cliente->id, 'nombre' => 'Visitas', 'unidad' => '', 'sentido' => 'mayor']);
        MetricaClienteValor::create(['metrica_id' => $llamadas->id, 'periodo' => '2026-09-01', 'valor' => 10, 'registrado_por' => $usuario->id]);
        MetricaClienteValor::create(['metrica_id' => $llamadas->id, 'periodo' => '2026-10-01', 'valor' => 95, 'registrado_por' => $usuario->id]);
        MetricaClienteValor::create(['metrica_id' => $libre->id, 'periodo' => '2026-10-01', 'valor' => 5000]);

        $b = $this->bloque($cliente, 'metricas');

        // Último mes: 95 de 100 con umbral 80 % = amarillo (cerca de la meta).
        $this->assertSame('amarillo', $b['estado_semaforo']);
        $c = $this->cifra($b, 'Llamadas');
        $this->assertSame('95 llamadas', $c['valor']);
        $this->assertSame('amarillo', $c['semaforo']);
        $this->assertSame('octubre 2026', $c['fecha']);
        $this->assertSame('Beto', $c['registrado_por']);
        $this->assertSame('neutro', $this->cifra($b, 'Visitas')['semaforo']);

        MetricaClienteValor::where('metrica_id', $llamadas->id)->where('periodo', '2026-10-01')->update(['valor' => 40]);
        $this->assertSame('rojo', $this->bloque($cliente, 'metricas')['estado_semaforo']);

        MetricaClienteValor::where('metrica_id', $llamadas->id)->where('periodo', '2026-10-01')->update(['valor' => 120]);
        $this->assertSame('verde', $this->bloque($cliente, 'metricas')['estado_semaforo']);
    }

    public function test_metricas_solo_sin_meta_no_se_califican(): void
    {
        $cliente = Cliente::factory()->create();
        $m = MetricaCliente::create(['cliente_id' => $cliente->id, 'nombre' => 'Visitas', 'unidad' => '', 'sentido' => 'mayor']);
        MetricaClienteValor::create(['metrica_id' => $m->id, 'periodo' => '2026-10-01', 'valor' => 5000]);

        $b = $this->bloque($cliente, 'metricas');

        $this->assertSame('neutro', $b['estado_semaforo']);
        $this->assertSame('5,000', $this->cifra($b, 'Visitas')['valor']);
    }

    public function test_un_verde_con_captura_vieja_baja_a_amarillo_pero_un_rojo_se_queda_rojo(): void
    {
        $cliente = Cliente::factory()->create();
        $m = MetricaCliente::create(['cliente_id' => $cliente->id, 'nombre' => 'Ventas', 'unidad' => '', 'meta' => 10, 'sentido' => 'mayor']);
        $v = MetricaClienteValor::create(['metrica_id' => $m->id, 'periodo' => '2026-09-01', 'valor' => 20]);
        $this->envejecer('metrica_cliente_valores', $v->id, '2026-09-30 10:00:00');
        $this->assertSame('amarillo', $this->bloque($cliente, 'metricas')['estado_semaforo']);

        MetricaClienteValor::where('id', $v->id)->update(['valor' => 1]);
        $this->assertSame('rojo', $this->bloque($cliente, 'metricas')['estado_semaforo']);
    }

    // --- Aislamiento ---------------------------------------------------------

    public function test_los_datos_de_otro_cliente_no_entran_en_ningun_bloque(): void
    {
        $cliente = Cliente::factory()->create();
        $otro = Cliente::factory()->create();

        $campana = $this->campanaSeo($otro);
        SeoMetricaMensual::create(['seo_campana_id' => $campana->id, 'ciclo' => 1, 'mes' => 10, 'anio' => 2026, 'trafico_organico' => 9999]);
        AdsMetrica::factory()->create(['ads_campana_id' => $this->campanaAds($otro)->id, 'cliente_id' => $otro->id, 'mes' => 10, 'anio' => 2026]);
        [$lista, $kw] = $this->listaConKeyword($otro);
        KeywordMedicion::create(['keyword_id' => $kw->id, 'lista_id' => $lista->id, 'fecha' => '2026-11-02', 'posicion' => 1]);
        AdsClic::create(['cliente_id' => $otro->id, 'visitor_id' => 'x', 'landing_url' => 'https://otro.com']);
        BuyerPersona::create(['cliente_id' => $otro->id, 'nombre' => 'Ajena', 'orden' => 0]);
        ElementoContenido::create(['cliente_id' => $otro->id, 'tipo' => 'documento', 'nombre' => 'Ajeno']);
        $m = MetricaCliente::create(['cliente_id' => $otro->id, 'nombre' => 'Ajena', 'unidad' => '', 'sentido' => 'mayor']);
        MetricaClienteValor::create(['metrica_id' => $m->id, 'periodo' => '2026-10-01', 'valor' => 1]);

        $r = $this->resumen($cliente);

        foreach (['seo', 'keywords', 'google_ads', 'conversiones', 'metricas', 'audiencias', 'contenido'] as $clave) {
            $this->assertSame('neutro', $r['bloques'][$clave]['estado_semaforo'], "{$clave} no debería ver datos ajenos");
        }
    }

    // --- Piezas puras --------------------------------------------------------

    public function test_peor_estado_y_regla_de_30_dias(): void
    {
        $this->assertSame('rojo', Bloque::peor(['verde', 'rojo', 'amarillo']));
        $this->assertSame('amarillo', Bloque::peor(['verde', 'amarillo']));
        $this->assertSame('neutro', Bloque::peor([]));

        $hoy = Carbon::parse('2026-11-08 09:00:00');
        $this->assertFalse(Bloque::desactualizado(null, $hoy), 'Sin fecha no se puede afirmar que esté viejo');
        $this->assertFalse(Bloque::desactualizado(Carbon::parse('2026-10-09 23:00:00'), $hoy));
        $this->assertTrue(Bloque::desactualizado(Carbon::parse('2026-10-08 23:00:00'), $hoy));
    }

    public function test_estado_de_semaforo_mapea_sin_meta_y_sin_dato_a_neutro(): void
    {
        $this->assertSame('verde', Metricas::estadoDeSemaforo('verde'));
        $this->assertSame('rojo', Metricas::estadoDeSemaforo('rojo'));
        $this->assertSame('neutro', Metricas::estadoDeSemaforo('sin_meta'));
        $this->assertSame('neutro', Metricas::estadoDeSemaforo('sin_dato'));
    }
}
