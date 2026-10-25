<?php

namespace Tests\Feature\Admin;

use App\Models\AdsCampana;
use App\Models\AdsMetrica;
use App\Models\Cliente;
use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Models\KeywordMedicion;
use App\Models\MetricaCliente;
use App\Models\MetricaClienteValor;
use App\Models\SeoCampana;
use App\Models\SeoMetricaMensual;
use App\Models\Servicio;
use App\Models\User;
use App\Support\Captura\TableroCaptura;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tablero de pendientes de captura: cada fuente en sus tres estados, el
 * aislamiento por mes, quién entra al tablero y el resumen.
 */
class CapturaTest extends TestCase
{
    use RefreshDatabase;

    private const MES = '2026-03';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function tablero(string $mes = self::MES, bool $soloPendientes = false): array
    {
        return app(TableroCaptura::class)->calcular(Carbon::createFromFormat('!Y-m', $mes), $soloPendientes);
    }

    /** Estado de una celda de un cliente, o null si el cliente no sale en el tablero. */
    private function estado(array $tablero, Cliente $cliente, string $fuente): ?string
    {
        foreach ($tablero['filas'] as $fila) {
            if ($fila['cliente']->id === $cliente->id) {
                return $fila['celdas'][$fuente]['estado'];
            }
        }

        return null;
    }

    private function lista(Cliente $cliente, int $keywords = 1): KeywordLista
    {
        $lista = KeywordLista::create(['cliente_id' => $cliente->id, 'nombre' => 'Lista '.uniqid(), 'canal' => 'seo', 'estado' => 'en_uso']);
        // Anterior al mes de prueba: una lista creada después no cuenta para ese mes.
        $lista->forceFill(['created_at' => '2026-01-01 00:00:00'])->saveQuietly();
        for ($i = 0; $i < $keywords; $i++) {
            Keyword::create(['cliente_id' => $cliente->id, 'lista_id' => $lista->id, 'keyword' => 'kw '.uniqid(), 'tipo' => 'principal', 'estado' => 'en_uso']);
        }

        return $lista;
    }

    private function medir(KeywordLista $lista, string $fecha): void
    {
        KeywordMedicion::create(['keyword_id' => $lista->keywords()->first()->id, 'lista_id' => $lista->id, 'fecha' => $fecha, 'posicion' => 5]);
    }

    private function seo(Cliente $cliente, array $extra = []): SeoCampana
    {
        return SeoCampana::create($extra + [
            'cliente_id' => $cliente->id,
            'servicio_id' => Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'seo'])->id,
            'nombre' => 'SEO '.uniqid(), 'url_sitio' => 'https://ejemplo.com', 'estado' => 'activa',
            'fase_actual' => 'ejecucion', 'ciclo_actual' => 1, 'fecha_inicio' => '2026-01-01',
        ]);
    }

    private function ads(Cliente $cliente, array $extra = []): AdsCampana
    {
        return AdsCampana::factory()->create($extra + [
            'cliente_id' => $cliente->id,
            'servicio_id' => Servicio::factory()->create(['cliente_id' => $cliente->id])->id,
            'fecha_inicio' => '2026-01-01',
        ]);
    }

    private function metrica(Cliente $cliente, array $extra = []): MetricaCliente
    {
        return MetricaCliente::create($extra + ['cliente_id' => $cliente->id, 'nombre' => 'M '.uniqid(), 'unidad' => 'u', 'sentido' => 'mayor']);
    }

    // --- acceso ----------------------------------------------------------

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get(route('admin.captura.index'))->assertRedirect('/login');
    }

    public function test_usuario_autenticado_ve_el_tablero_del_mes_actual_por_defecto(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.captura.index'))
            ->assertOk()
            ->assertSee('pendientes del mes');
    }

    // --- fuentes ---------------------------------------------------------

    public function test_keywords_en_sus_tres_estados_y_no_aplica(): void
    {
        $completo = Cliente::factory()->create();
        $parcial = Cliente::factory()->create();
        $falta = Cliente::factory()->create();
        $sinListas = Cliente::factory()->create();
        $this->metrica($sinListas); // para que salga en el tablero

        $this->medir($this->lista($completo), '2026-03-10');

        $this->medir($this->lista($parcial), '2026-03-31');
        $this->lista($parcial);

        $this->lista($falta);

        $t = $this->tablero();
        $this->assertSame('completo', $this->estado($t, $completo, 'keywords'));
        $this->assertSame('parcial', $this->estado($t, $parcial, 'keywords'));
        $this->assertSame('falta', $this->estado($t, $falta, 'keywords'));
        $this->assertSame('no_aplica', $this->estado($t, $sinListas, 'keywords'));
    }

    public function test_keywords_una_lista_sin_keywords_o_descartada_no_cuenta(): void
    {
        $cliente = Cliente::factory()->create();
        $this->medir($this->lista($cliente), '2026-03-05');
        $this->lista($cliente, 0);
        KeywordLista::create(['cliente_id' => $cliente->id, 'nombre' => 'Vieja', 'canal' => 'seo', 'estado' => 'descartada']);

        $this->assertSame('completo', $this->estado($this->tablero(), $cliente, 'keywords'));
    }

    public function test_keywords_la_medicion_de_otro_mes_no_cuenta(): void
    {
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente);
        $this->medir($lista, '2026-02-28');
        $this->medir($lista, '2026-04-01');

        $this->assertSame('falta', $this->estado($this->tablero('2026-03'), $cliente, 'keywords'));
        $this->assertSame('completo', $this->estado($this->tablero('2026-02'), $cliente, 'keywords'));
    }

    public function test_seo_en_sus_tres_estados_y_no_aplica(): void
    {
        $completo = Cliente::factory()->create();
        $parcial = Cliente::factory()->create();
        $falta = Cliente::factory()->create();
        $sin = Cliente::factory()->create();
        $this->metrica($sin);

        SeoMetricaMensual::create(['seo_campana_id' => $this->seo($completo)->id, 'ciclo' => 1, 'mes' => 3, 'anio' => 2026]);
        SeoMetricaMensual::create(['seo_campana_id' => $this->seo($parcial)->id, 'ciclo' => 1, 'mes' => 3, 'anio' => 2026]);
        $this->seo($parcial);
        $this->seo($falta);
        // Pausada: no genera captura.
        $this->seo($sin, ['estado' => 'pausada']);

        $t = $this->tablero();
        $this->assertSame('completo', $this->estado($t, $completo, 'seo'));
        $this->assertSame('parcial', $this->estado($t, $parcial, 'seo'));
        $this->assertSame('falta', $this->estado($t, $falta, 'seo'));
        $this->assertSame('no_aplica', $this->estado($t, $sin, 'seo'));
    }

    public function test_seo_el_mes_o_anio_distinto_no_cuenta_y_campana_futura_no_aplica(): void
    {
        $cliente = Cliente::factory()->create();
        $campana = $this->seo($cliente);
        SeoMetricaMensual::create(['seo_campana_id' => $campana->id, 'ciclo' => 1, 'mes' => 3, 'anio' => 2025]);
        SeoMetricaMensual::create(['seo_campana_id' => $campana->id, 'ciclo' => 1, 'mes' => 4, 'anio' => 2026]);

        $this->assertSame('falta', $this->estado($this->tablero('2026-03'), $cliente, 'seo'));
        // La campaña arrancó en enero: en diciembre aún no existía.
        $this->assertNull($this->estado($this->tablero('2025-12'), $cliente, 'seo'));
    }

    public function test_ads_en_sus_tres_estados_y_no_aplica(): void
    {
        $completo = Cliente::factory()->create();
        $parcial = Cliente::factory()->create();
        $falta = Cliente::factory()->create();
        $sin = Cliente::factory()->create();
        $this->metrica($sin);

        AdsMetrica::factory()->create(['ads_campana_id' => $this->ads($completo)->id, 'cliente_id' => $completo->id, 'mes' => 3, 'anio' => 2026]);
        AdsMetrica::factory()->create(['ads_campana_id' => $this->ads($parcial)->id, 'cliente_id' => $parcial->id, 'mes' => 3, 'anio' => 2026]);
        $this->ads($parcial);
        $this->ads($falta);
        $this->ads($sin, ['estado' => 'finalizada']);

        $t = $this->tablero();
        $this->assertSame('completo', $this->estado($t, $completo, 'ads'));
        $this->assertSame('parcial', $this->estado($t, $parcial, 'ads'));
        $this->assertSame('falta', $this->estado($t, $falta, 'ads'));
        $this->assertSame('no_aplica', $this->estado($t, $sin, 'ads'));
    }

    public function test_metricas_personalizadas_en_sus_tres_estados_y_no_aplica(): void
    {
        $completo = Cliente::factory()->create();
        $parcial = Cliente::factory()->create();
        $falta = Cliente::factory()->create();
        $sin = Cliente::factory()->create();
        $this->seo($sin);

        foreach ([$completo, $parcial] as $c) {
            MetricaClienteValor::create(['metrica_id' => $this->metrica($c)->id, 'periodo' => '2026-03-01', 'valor' => 10]);
        }
        $this->metrica($parcial);
        $this->metrica($falta);
        // Inactiva: no se exige.
        $this->metrica($completo, ['activa' => false]);
        $this->metrica($sin, ['activa' => false]);

        $t = $this->tablero();
        $this->assertSame('completo', $this->estado($t, $completo, 'metricas'));
        $this->assertSame('parcial', $this->estado($t, $parcial, 'metricas'));
        $this->assertSame('falta', $this->estado($t, $falta, 'metricas'));
        $this->assertSame('no_aplica', $this->estado($t, $sin, 'metricas'));
    }

    public function test_metricas_el_valor_de_otro_mes_no_cuenta(): void
    {
        $cliente = Cliente::factory()->create();
        $metrica = $this->metrica($cliente);
        MetricaClienteValor::create(['metrica_id' => $metrica->id, 'periodo' => '2026-02-01', 'valor' => 3]);

        $this->assertSame('falta', $this->estado($this->tablero('2026-03'), $cliente, 'metricas'));
        $this->assertSame('completo', $this->estado($this->tablero('2026-02'), $cliente, 'metricas'));
    }

    // --- quién sale ------------------------------------------------------

    public function test_cliente_inactivo_no_sale(): void
    {
        $pausado = Cliente::factory()->create(['estado' => 'pausado']);
        $cancelado = Cliente::factory()->create(['estado' => 'cancelado']);
        $this->metrica($pausado);
        $this->metrica($cancelado);

        $t = $this->tablero();
        $this->assertNull($this->estado($t, $pausado, 'metricas'));
        $this->assertNull($this->estado($t, $cancelado, 'metricas'));
        $this->assertSame(['pendientes' => 0, 'total' => 0], $t['resumen']);
    }

    public function test_cliente_activo_sin_ninguna_fuente_no_sale(): void
    {
        $cliente = Cliente::factory()->create();
        Servicio::factory()->create(['cliente_id' => $cliente->id]);

        $this->assertNull($this->estado($this->tablero(), $cliente, 'seo'));
    }

    // --- resumen y filtro ------------------------------------------------

    public function test_resumen_cuenta_pendientes_sobre_celdas_aplicables(): void
    {
        $a = Cliente::factory()->create();
        // keywords completo, seo falta, metricas parcial -> 3 aplicables, 2 pendientes.
        $this->medir($this->lista($a), '2026-03-02');
        $this->seo($a);
        MetricaClienteValor::create(['metrica_id' => $this->metrica($a)->id, 'periodo' => '2026-03-01', 'valor' => 1]);
        $this->metrica($a);

        // ads completo -> 1 aplicable, 0 pendientes.
        $b = Cliente::factory()->create();
        AdsMetrica::factory()->create(['ads_campana_id' => $this->ads($b)->id, 'cliente_id' => $b->id, 'mes' => 3, 'anio' => 2026]);

        $this->assertSame(['pendientes' => 2, 'total' => 4], $this->tablero()['resumen']);
    }

    public function test_filtro_de_pendientes_oculta_clientes_completos_sin_alterar_el_resumen(): void
    {
        $completo = Cliente::factory()->create(['nombre' => 'Cliente Al Dia']);
        $pendiente = Cliente::factory()->create(['nombre' => 'Cliente Atrasado']);
        MetricaClienteValor::create(['metrica_id' => $this->metrica($completo)->id, 'periodo' => '2026-03-01', 'valor' => 1]);
        $this->metrica($pendiente);

        $t = $this->tablero(self::MES, true);
        $this->assertNull($this->estado($t, $completo, 'metricas'));
        $this->assertSame('falta', $this->estado($t, $pendiente, 'metricas'));
        $this->assertSame(['pendientes' => 1, 'total' => 2], $t['resumen']);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.captura.index', ['mes' => self::MES, 'pendientes' => 1]))
            ->assertOk()
            ->assertSee('Cliente Atrasado')
            ->assertDontSee('Cliente Al Dia');
    }

    // --- enlaces y vista -------------------------------------------------

    public function test_la_celda_enlaza_a_la_pantalla_de_captura_con_cliente_y_mes(): void
    {
        $cliente = Cliente::factory()->create();
        $this->metrica($cliente);
        $campana = $this->seo($cliente);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('admin.captura.index', ['mes' => self::MES]));

        $response->assertOk()
            ->assertSee(e(route('admin.metricas.index', ['cliente' => $cliente->id, 'mes' => self::MES])), false)
            ->assertSee(route('admin.seo.show', $campana->id).'#metricasCard', false)
            ->assertSee('data-estado="falta"', false)
            ->assertSee('data-estado="no_aplica"', false);
    }

    public function test_el_enlace_apunta_a_la_primera_unidad_pendiente(): void
    {
        $cliente = Cliente::factory()->create();
        $hecha = $this->seo($cliente);
        $pendiente = $this->seo($cliente);
        SeoMetricaMensual::create(['seo_campana_id' => $hecha->id, 'ciclo' => 1, 'mes' => 3, 'anio' => 2026]);

        $url = $this->tablero()['filas'][0]['celdas']['seo']['url'];
        $this->assertSame(route('admin.seo.show', $pendiente->id).'#metricasCard', $url);
    }

    public function test_mes_invalido_cae_al_mes_actual(): void
    {
        $this->assertSame(now()->format('Y-m'), TableroCaptura::resolverMes('basura')->format('Y-m'));
        $this->assertSame('2026-03', TableroCaptura::resolverMes('2026-03')->format('Y-m'));
    }

    public function test_mes_como_arreglo_en_la_url_no_rompe_la_pantalla(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.captura.index', ['mes' => ['x']]))
            ->assertOk();
    }

    public function test_no_hay_consultas_por_cliente(): void
    {
        foreach (range(1, 6) as $_) {
            $c = Cliente::factory()->create();
            $this->medir($this->lista($c), '2026-03-02');
            $this->seo($c);
            $this->ads($c);
            $this->metrica($c);
        }

        \DB::flushQueryLog();
        \DB::enableQueryLog();
        $this->tablero();
        $consultas = count(\DB::getQueryLog());

        // 1 clientes + 2 por fuente de tipo lista/campaña (definiciones + hechas): cota fija, no crece con clientes.
        $this->assertLessThanOrEqual(10, $consultas);
    }

    // --- reglas de aplicabilidad -----------------------------------------

    public function test_keywords_una_ronda_a_medias_deja_la_lista_pendiente_y_parcial(): void
    {
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente, 3);
        $this->medir($lista, '2026-03-05'); // 1 de 3

        $t = $this->tablero();
        $this->assertSame('parcial', $this->estado($t, $cliente, 'keywords'));
        $celda = $t['filas'][0]['celdas']['keywords'];
        $this->assertSame(0, $celda['hechas']);
        $this->assertSame(1, $celda['total']);
        $this->assertSame(1, $t['resumen']['pendientes']);
    }

    public function test_keywords_completa_cuando_todas_las_vivas_estan_medidas_aunque_la_posicion_sea_null(): void
    {
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente, 2);
        Keyword::create(['cliente_id' => $cliente->id, 'lista_id' => $lista->id, 'keyword' => 'x '.uniqid(), 'tipo' => 'principal', 'estado' => 'descartada']);
        foreach ($lista->keywords()->where('estado', '!=', 'descartada')->get() as $kw) {
            KeywordMedicion::create(['keyword_id' => $kw->id, 'lista_id' => $lista->id, 'fecha' => '2026-03-31', 'posicion' => null]);
        }

        $this->assertSame('completo', $this->estado($this->tablero(), $cliente, 'keywords'));
    }

    public function test_keywords_varias_mediciones_de_la_misma_keyword_no_completan_la_lista(): void
    {
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente, 2);
        $this->medir($lista, '2026-03-01');
        $this->medir($lista, '2026-03-02');

        $this->assertSame('parcial', $this->estado($this->tablero(), $cliente, 'keywords'));
    }

    public function test_keywords_lista_creada_despues_del_mes_no_cuenta_y_el_ultimo_segundo_si(): void
    {
        $cliente = Cliente::factory()->create();
        $this->metrica($cliente);
        $despues = $this->lista($cliente);
        $despues->forceFill(['created_at' => '2026-04-01 00:00:00'])->saveQuietly();
        $ultimo = $this->lista($cliente);
        $ultimo->forceFill(['created_at' => '2026-03-31 23:59:59'])->saveQuietly();

        $celda = $this->tablero()['filas'][0]['celdas']['keywords'];
        $this->assertSame(1, $celda['total']);
        $this->assertSame('falta', $celda['estado']);
    }

    public function test_ads_campana_con_fecha_fin_anterior_al_mes_no_aplica_y_en_la_frontera_si(): void
    {
        $terminada = Cliente::factory()->create();
        $this->metrica($terminada);
        $this->ads($terminada, ['fecha_fin' => '2026-02-28']);

        $frontera = Cliente::factory()->create();
        $this->ads($frontera, ['fecha_fin' => '2026-03-01']);

        $sinFin = Cliente::factory()->create();
        $this->ads($sinFin, ['fecha_fin' => null]);

        $t = $this->tablero();
        $this->assertSame('no_aplica', $this->estado($t, $terminada, 'ads'));
        $this->assertSame('falta', $this->estado($t, $frontera, 'ads'));
        $this->assertSame('falta', $this->estado($t, $sinFin, 'ads'));
        // Abril: la de fecha_fin 03-01 ya no exige captura y el cliente deja de salir.
        $this->assertNull($this->estado($this->tablero('2026-04'), $frontera, 'ads'));
    }
}
