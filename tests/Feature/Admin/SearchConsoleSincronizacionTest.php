<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\GscConexion;
use App\Models\GscMetricaMensual;
use App\Models\GscPropiedad;
use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Models\KeywordMedicion;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookEntrega;
use App\Services\SearchConsole\Sincronizador;
use App\Support\Captura\TableroCaptura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\LlaveGoogleDePrueba;
use Tests\TestCase;

/**
 * Sincronización con Search Console: totales mensuales y mediciones de posición.
 * Hoy fijo en 2026-10-26: octubre es el mes en curso (parcial); septiembre es el último mes cerrado.
 */
class SearchConsoleSincronizacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-26 12:00:00');
        Cache::flush();
        config(['services.google_search_console.espera_ms' => 0]);
        $this->conexion();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function conexion(): void
    {
        // Fila única: forceFill con id=1 (create() descartaría el id y las pruebas siguientes no la encontrarían).
        (new GscConexion())->forceFill([
            'id' => 1,
            'client_email' => LlaveGoogleDePrueba::CORREO,
            'llave_json' => json_encode(['client_email' => LlaveGoogleDePrueba::CORREO, 'private_key' => LlaveGoogleDePrueba::par()['privada']]),
            'estado' => GscConexion::OK,
        ])->save();
    }

    private function propiedad(?Cliente $cliente = null, string $sitio = 'sc-domain:ejemplo.com', array $extra = []): GscPropiedad
    {
        return GscPropiedad::create(array_merge(['cliente_id' => ($cliente ?? Cliente::factory()->create())->id, 'site_url' => $sitio, 'activa' => true], $extra));
    }

    private function lista(Cliente $cliente, array $extra = []): KeywordLista
    {
        $lista = KeywordLista::create(array_merge(['cliente_id' => $cliente->id, 'nombre' => 'Principal', 'canal' => 'seo', 'estado' => 'en_uso'], $extra));
        // Creada antes de septiembre para que el tablero de captura la considere en ese mes.
        $lista->forceFill(['created_at' => '2026-01-01 00:00:00'])->save();

        return $lista;
    }

    private function keyword(KeywordLista $lista, string $texto, array $extra = []): Keyword
    {
        return Keyword::create(array_merge(['cliente_id' => $lista->cliente_id, 'lista_id' => $lista->id, 'keyword' => $texto, 'tipo' => 'principal', 'estado' => 'en_uso'], $extra));
    }

    /**
     * Google falso: $porFecha = filas de la dimensión date; $porMes = ['2026-09' => filas de query].
     * $fallos = ['sc-domain:malo.com' => 403] hace fallar a un sitio concreto.
     */
    private function google(array $porFecha = [], array $porMes = [], array $fallos = []): void
    {
        // Http::fake() acumula: sin esto, la primera simulación seguiría ganando en un segundo google().
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(function (Request $r) use ($porFecha, $porMes, $fallos) {
            if ($r->url() === 'https://oauth2.googleapis.com/token') {
                return Http::response(['access_token' => 'ya29.t', 'expires_in' => 3600]);
            }
            foreach ($fallos as $sitio => $estado) {
                if (str_contains($r->url(), rawurlencode($sitio))) {
                    return Http::response(['error' => ['message' => 'denegado']], $estado);
                }
            }
            if (($r['dimensions'] ?? []) === ['date']) {
                return Http::response(['rows' => $porFecha]);
            }

            return Http::response(['rows' => $porMes[substr($r['startDate'], 0, 7)] ?? []]);
        });
    }

    private function fila(string $clave, float $posicion, int $clics = 1, int $impresiones = 10): array
    {
        return ['keys' => [$clave], 'clicks' => $clics, 'impressions' => $impresiones, 'ctr' => $clics / max($impresiones, 1), 'position' => $posicion];
    }

    private function consultasQuery(): \Illuminate\Support\Collection
    {
        return Http::recorded(fn (Request $r) => str_contains($r->url(), 'searchAnalytics') && ($r['dimensions'] ?? []) === ['query'])
            ->map(fn ($par) => $par[0])->values();
    }

    // ---- totales mensuales ------------------------------------------------

    public function test_guarda_los_totales_mensuales_con_ctr_y_posicion_ponderada_y_marca_el_mes_en_curso_como_parcial(): void
    {
        $this->google(porFecha: [
            $this->fila('2026-09-10', 4.0, 10, 100),
            $this->fila('2026-09-11', 10.0, 5, 300),
            $this->fila('2026-10-02', 2.0, 7, 70),
        ]);
        $p = $this->propiedad();

        $r = app(Sincronizador::class)->sincronizar($p);

        $this->assertTrue($r['ok']);
        $sep = GscMetricaMensual::where('propiedad_id', $p->id)->where('periodo', '2026-09-01')->first();
        $this->assertSame(15, $sep->clics);
        $this->assertSame(400, $sep->impresiones);
        $this->assertEqualsWithDelta(0.0375, $sep->ctr, 0.000001);
        // (4*100 + 10*300) / 400 = 8.5: ponderada por impresiones, no el promedio simple (7).
        $this->assertEqualsWithDelta(8.5, $sep->posicion_media, 0.001);
        $this->assertFalse($sep->parcial);

        $oct = GscMetricaMensual::where('propiedad_id', $p->id)->where('periodo', '2026-10-01')->first();
        $this->assertTrue($oct->parcial);
        $this->assertSame(7, $oct->clics);
    }

    public function test_la_primera_vez_trae_16_meses_y_despues_solo_los_recientes(): void
    {
        $this->google(porFecha: [$this->fila('2026-09-10', 4.0)]);
        $p = $this->propiedad();
        $sync = app(Sincronizador::class);

        $sync->sincronizar($p);
        $this->assertSame('2025-07-01', Http::recorded()->map(fn ($x) => $x[0])->first(fn ($r) => ($r['dimensions'] ?? null) === ['date'])['startDate']);

        $this->google(porFecha: [$this->fila('2026-09-10', 4.0)]);
        $sync->sincronizar($p->fresh());
        $this->assertSame('2026-08-01', Http::recorded()->map(fn ($x) => $x[0])->first(fn ($r) => ($r['dimensions'] ?? null) === ['date'])['startDate']);
    }

    public function test_correr_dos_veces_no_duplica_totales(): void
    {
        $this->google(porFecha: [$this->fila('2026-09-10', 4.0, 10, 100)]);
        $p = $this->propiedad();
        $sync = app(Sincronizador::class);

        $sync->sincronizar($p);
        $sync->sincronizar($p->fresh());

        $this->assertSame(1, GscMetricaMensual::where('propiedad_id', $p->id)->count());
    }

    // ---- mediciones de keywords ------------------------------------------

    public function test_crea_mediciones_del_mes_cerrado_con_fecha_fin_de_mes_redondeo_y_nota(): void
    {
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente);
        $a = $this->keyword($lista, 'Hotel en Aguascalientes');
        $b = $this->keyword($lista, 'hoteles baratos');
        $c = $this->keyword($lista, 'hotel con alberca');
        $d = $this->keyword($lista, 'hotel centro');

        $this->google(
            porFecha: [$this->fila('2026-09-10', 4.0)],
            porMes: ['2026-09' => [
                $this->fila('hotel en aguascalientes', 3.4),   // baja: 3
                $this->fila('hoteles baratos', 3.5),           // mitad: sube a 4
                $this->fila('hotel con alberca', 0.4),         // mínimo posible: 1
                $this->fila('algo que no esta en el banco', 7.0),
            ]]
        );
        $p = $this->propiedad($cliente);

        $r = app(Sincronizador::class)->sincronizar($p, 2);

        $this->assertTrue($r['ok'], $r['mensaje']);
        $this->assertSame(3, KeywordMedicion::count());
        $m = KeywordMedicion::where('keyword_id', $a->id)->first();
        $this->assertSame('2026-09-30', $m->fecha->toDateString());
        $this->assertSame(3, $m->posicion);
        $this->assertSame('Search Console · promedio del mes', $m->nota);
        $this->assertNull($m->registrado_por);
        $this->assertSame($lista->id, $m->lista_id);
        $this->assertSame(4, KeywordMedicion::where('keyword_id', $b->id)->value('posicion'));
        $this->assertSame(1, KeywordMedicion::where('keyword_id', $c->id)->value('posicion'));
        // Sin dato en Google no se inventa nada, ni siquiera un null.
        $this->assertSame(0, KeywordMedicion::where('keyword_id', $d->id)->count());
        // La caché del banco quedó sincronizada con el histórico.
        $this->assertSame(3, $a->fresh()->posicion_actual);
        $this->assertStringContainsString('3 nuevas', $r['mensaje']);
    }

    public function test_empareja_keywords_sin_importar_acentos_mayusculas_ni_espacios(): void
    {
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente);
        $k = $this->keyword($lista, '  Cafés   de Especialidad ');

        $this->google(porMes: ['2026-09' => [$this->fila('cafes de especialidad', 6.0)]]);
        app(Sincronizador::class)->sincronizar($this->propiedad($cliente), 2);

        $this->assertSame(6, KeywordMedicion::where('keyword_id', $k->id)->value('posicion'));
    }

    public function test_el_mes_en_curso_no_crea_medicion_ni_siquiera_se_consulta_por_query(): void
    {
        $cliente = Cliente::factory()->create();
        $k = $this->keyword($this->lista($cliente), 'hotel centro');
        $this->google(
            porFecha: [$this->fila('2026-10-05', 5.0)],
            porMes: ['2026-10' => [$this->fila('hotel centro', 5.0)]]
        );

        $r = app(Sincronizador::class)->sincronizar($this->propiedad($cliente), 1);

        $this->assertTrue($r['ok']);
        $this->assertSame(0, KeywordMedicion::where('keyword_id', $k->id)->count());
        $this->assertCount(0, $this->consultasQuery());
        // El total de octubre sí queda, como parcial.
        $this->assertTrue(GscMetricaMensual::where('periodo', '2026-10-01')->first()->parcial);
    }

    public function test_no_pisa_una_medicion_manual_del_mismo_mes_ni_choca_con_una_del_ultimo_dia(): void
    {
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente);
        $conManual = $this->keyword($lista, 'hotel centro');
        $enFinDeMes = $this->keyword($lista, 'hotel alberca');
        $libre = $this->keyword($lista, 'hotel familiar');
        $usuario = User::factory()->create();

        KeywordMedicion::create(['keyword_id' => $conManual->id, 'lista_id' => $lista->id, 'fecha' => '2026-09-12', 'posicion' => 22, 'nota' => 'Cambié el title', 'registrado_por' => $usuario->id]);
        // Manual el día 30: misma fecha que usaría la importación (unique keyword+fecha).
        KeywordMedicion::create(['keyword_id' => $enFinDeMes->id, 'lista_id' => $lista->id, 'fecha' => '2026-09-30', 'posicion' => 9, 'nota' => 'Manual', 'registrado_por' => $usuario->id]);

        $this->google(porMes: ['2026-09' => [
            $this->fila('hotel centro', 3.0), $this->fila('hotel alberca', 3.0), $this->fila('hotel familiar', 3.0),
        ]]);

        $r = app(Sincronizador::class)->sincronizar($this->propiedad($cliente), 2);

        $this->assertTrue($r['ok'], $r['mensaje']);
        $manual = KeywordMedicion::where('keyword_id', $conManual->id)->get();
        $this->assertCount(1, $manual);
        $this->assertSame(22, $manual[0]->posicion);
        $this->assertSame('Cambié el title', $manual[0]->nota);
        $this->assertSame($usuario->id, $manual[0]->registrado_por);

        $fin = KeywordMedicion::where('keyword_id', $enFinDeMes->id)->get();
        $this->assertCount(1, $fin);
        $this->assertSame(9, $fin[0]->posicion);

        $this->assertSame(3, KeywordMedicion::where('keyword_id', $libre->id)->value('posicion'));
        $this->assertStringContainsString('2 respetadas', $r['mensaje']);
    }

    public function test_es_idempotente_y_actualiza_su_propia_medicion_si_google_cambia_la_cifra(): void
    {
        $cliente = Cliente::factory()->create();
        $k = $this->keyword($this->lista($cliente), 'hotel centro');
        $p = $this->propiedad($cliente);
        $sync = app(Sincronizador::class);

        $this->google(porMes: ['2026-09' => [$this->fila('hotel centro', 5.0)]]);
        $sync->sincronizar($p, 2);
        $sync->sincronizar($p->fresh(), 2);
        $this->assertSame(1, KeywordMedicion::where('keyword_id', $k->id)->count());

        // Google cierra el mes con otra cifra: se corrige la medición importada, no se agrega otra.
        $this->google(porMes: ['2026-09' => [$this->fila('hotel centro', 7.2)]]);
        $r = $sync->sincronizar($p->fresh(), 2);

        $this->assertSame(1, KeywordMedicion::where('keyword_id', $k->id)->count());
        $this->assertSame(7, KeywordMedicion::where('keyword_id', $k->id)->value('posicion'));
        $this->assertStringContainsString('1 actualizadas', $r['mensaje']);
        $this->assertSame(7, $k->fresh()->posicion_actual);
    }

    public function test_ignora_keywords_descartadas_de_listas_descartadas_y_de_otros_clientes(): void
    {
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente);
        $descartada = $this->keyword($lista, 'hotel centro', ['estado' => 'descartada']);
        $listaMuerta = $this->lista($cliente, ['nombre' => 'Vieja', 'estado' => 'descartada']);
        $enListaMuerta = $this->keyword($listaMuerta, 'hotel viejo');
        $ajena = $this->keyword($this->lista(Cliente::factory()->create()), 'hotel ajeno');

        $this->google(porMes: ['2026-09' => [
            $this->fila('hotel centro', 3.0), $this->fila('hotel viejo', 3.0), $this->fila('hotel ajeno', 3.0),
        ]]);
        app(Sincronizador::class)->sincronizar($this->propiedad($cliente), 2);

        $this->assertSame(0, KeywordMedicion::count());
    }

    public function test_cada_ronda_emite_un_solo_aviso_y_no_uno_por_medicion(): void
    {
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente);
        foreach (['uno', 'dos', 'tres', 'cuatro'] as $t) {
            $this->keyword($lista, "hotel {$t}");
        }
        $webhook = Webhook::factory()->create(['eventos' => ['keyword.ronda_registrada', 'keyword.medicion_registrada']]);

        $this->google(porMes: ['2026-09' => collect(['uno', 'dos', 'tres', 'cuatro'])->map(fn ($t) => $this->fila("hotel {$t}", 4.0))->all()]);
        app(Sincronizador::class)->sincronizar($this->propiedad($cliente), 2);

        $this->assertSame(4, KeywordMedicion::count());
        $this->assertSame(1, WebhookEntrega::where('webhook_id', $webhook->id)->where('evento', 'keyword.ronda_registrada')->count());
        $this->assertSame(0, WebhookEntrega::where('webhook_id', $webhook->id)->where('evento', 'keyword.medicion_registrada')->count());

        $payload = WebhookEntrega::where('evento', 'keyword.ronda_registrada')->first()->payload;
        $datos = is_string($payload) ? json_decode($payload, true) : $payload;
        $this->assertSame(4, $datos['datos']['total_mediciones']);
        $this->assertSame('2026-09-30', $datos['datos']['fecha']);
    }

    // ---- tablero de captura ----------------------------------------------

    public function test_el_tablero_de_captura_cuenta_las_mediciones_importadas(): void
    {
        $cliente = Cliente::factory()->create(['estado' => 'activo']);
        $lista = $this->lista($cliente);
        $a = $this->keyword($lista, 'hotel centro');
        $b = $this->keyword($lista, 'hotel alberca');
        $mes = Carbon::parse('2026-09-01');

        $celda = fn () => collect(app(TableroCaptura::class)->calcular($mes)['filas'])->firstWhere('cliente.id', $cliente->id)['celdas']['keywords'];
        $this->assertSame('falta', $celda()['estado']);

        // Google solo conoce una de las dos: la celda queda parcial, no completa.
        $this->google(porMes: ['2026-09' => [$this->fila('hotel centro', 3.0)]]);
        $p = $this->propiedad($cliente);
        app(Sincronizador::class)->sincronizar($p, 2);
        $this->assertSame('parcial', $celda()['estado']);

        $this->google(porMes: ['2026-09' => [$this->fila('hotel centro', 3.0), $this->fila('hotel alberca', 8.0)]]);
        app(Sincronizador::class)->sincronizar($p->fresh(), 2);
        $this->assertSame('completo', $celda()['estado']);
    }

    // ---- fallos y candado -------------------------------------------------

    public function test_sin_llave_el_resultado_queda_guardado_con_un_mensaje_claro(): void
    {
        GscConexion::query()->delete();
        Http::fake();
        $p = $this->propiedad();

        $r = app(Sincronizador::class)->sincronizar($p);

        $this->assertFalse($r['ok']);
        $p->refresh();
        $this->assertFalse($p->ultimo_ok);
        $this->assertStringContainsString('Aún no hay llave', $p->ultimo_resultado);
        $this->assertNotNull($p->ultima_sync_en);
        Http::assertNothingSent();
    }

    public function test_el_fallo_de_un_cliente_no_frena_a_los_demas_y_queda_persistido(): void
    {
        $this->google(
            porFecha: [$this->fila('2026-09-10', 4.0, 10, 100)],
            fallos: ['sc-domain:malo.com' => 403]
        );
        $malo = $this->propiedad(null, 'sc-domain:malo.com');
        $bueno = $this->propiedad(null, 'sc-domain:bueno.com');

        $this->artisan('gsc:sincronizar')->assertExitCode(1);

        $malo->refresh();
        $this->assertFalse($malo->ultimo_ok);
        $this->assertStringContainsString('no tiene acceso a la propiedad sc-domain:malo.com', $malo->ultimo_resultado);
        $this->assertStringContainsString(LlaveGoogleDePrueba::CORREO, $malo->ultimo_resultado);
        $this->assertLessThanOrEqual(400, mb_strlen($malo->ultimo_resultado));

        $bueno->refresh();
        $this->assertTrue($bueno->ultimo_ok);
        $this->assertSame(1, GscMetricaMensual::where('propiedad_id', $bueno->id)->count());
    }

    public function test_el_mensaje_guardado_no_contiene_rutas_ni_la_llave(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_grant', 'error_description' => 'fallo en C:\\laragon\\www\\rankpro\\app\\X.php '.LlaveGoogleDePrueba::par()['privada']], 400),
        ]);
        $p = $this->propiedad();

        app(Sincronizador::class)->sincronizar($p);

        $guardado = $p->fresh()->ultimo_resultado;
        $this->assertStringNotContainsString('laragon', $guardado);
        $this->assertStringNotContainsString('PRIVATE KEY', $guardado);
    }

    public function test_el_candado_por_propiedad_impide_dos_sincronizaciones_a_la_vez_y_responde_409(): void
    {
        $this->google(porFecha: [$this->fila('2026-09-10', 4.0)]);
        $p = $this->propiedad();
        $user = User::factory()->create();

        $candado = Cache::lock("gsc:sincronizar:{$p->id}", 60);
        $this->assertTrue($candado->get());

        $this->actingAs($user)->postJson(route('admin.integraciones.search-console.sincronizar', $p))
            ->assertStatus(409)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Ya hay una sincronización en curso para esta propiedad. Espera a que termine e inténtalo de nuevo.');

        // El comando, en la misma situación, avisa y salta esa propiedad sin tocarla.
        $this->artisan('gsc:sincronizar')->expectsOutputToContain('sincronización en curso')->assertExitCode(0);
        $this->assertNull($p->fresh()->ultima_sync_en);
        Http::assertNothingSent();

        $candado->release();
        $this->actingAs($user)->postJson(route('admin.integraciones.search-console.sincronizar', $p))->assertOk()->assertJsonPath('ok', true);
    }

    public function test_el_candado_se_libera_aunque_la_sincronizacion_falle(): void
    {
        $this->google(fallos: ['sc-domain:ejemplo.com' => 404]);
        $p = $this->propiedad();

        app(Sincronizador::class)->sincronizar($p);

        $lock = Cache::lock("gsc:sincronizar:{$p->id}", 5);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    // ---- comando ----------------------------------------------------------

    public function test_el_comando_acepta_cliente_y_meses_y_salta_las_propiedades_pausadas(): void
    {
        $this->google(porFecha: [$this->fila('2026-10-02', 4.0)]);
        $a = $this->propiedad(null, 'sc-domain:a.com');
        $b = $this->propiedad(null, 'sc-domain:b.com');
        $pausada = $this->propiedad(null, 'sc-domain:c.com', ['activa' => false]);

        $this->artisan('gsc:sincronizar', ['--cliente' => $b->cliente_id, '--meses' => 1])->assertExitCode(0);

        $this->assertNull($a->fresh()->ultima_sync_en);
        $this->assertNotNull($b->fresh()->ultima_sync_en);
        $this->assertNull($pausada->fresh()->ultima_sync_en);
        // --meses=1 = solo el mes en curso.
        $inicio = Http::recorded(fn (Request $r) => ($r['dimensions'] ?? null) === ['date'])->first()[0]['startDate'];
        $this->assertSame('2026-10-01', $inicio);

        $this->artisan('gsc:sincronizar')->assertExitCode(0);
        $this->assertNotNull($a->fresh()->ultima_sync_en);
        $this->assertNull($pausada->fresh()->ultima_sync_en);
    }

    public function test_el_comando_rechaza_un_numero_de_meses_invalido(): void
    {
        Http::fake();
        $this->propiedad();

        $this->artisan('gsc:sincronizar', ['--meses' => 99])->assertExitCode(2);
        $this->artisan('gsc:sincronizar', ['--meses' => 'abc'])->assertExitCode(2);
        Http::assertNothingSent();
    }

    public function test_el_comando_esta_programado_una_vez_al_dia_de_madrugada_sin_solaparse(): void
    {
        $evento = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command, 'gsc:sincronizar'));

        $this->assertNotNull($evento);
        $this->assertSame('30 4 * * *', $evento->expression);
        $this->assertTrue($evento->withoutOverlapping);
    }
}
