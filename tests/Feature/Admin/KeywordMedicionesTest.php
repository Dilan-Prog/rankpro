<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Models\KeywordMedicion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Histórico de posiciones del banco de keywords.
 *
 * Lo que de verdad se prueba aquí no es el CRUD: es que `posicion_actual` y
 * `posicion_anterior` dejaron de ser un dato propio y son una caché de las dos
 * últimas mediciones POR FECHA. Por eso varios tests insertan rondas en desorden
 * cronológico: si la caché se calculara por orden de inserción, pasarían igual
 * los tests "felices" y fallarían estos.
 */
class KeywordMedicionesTest extends TestCase
{
    use RefreshDatabase;

    // No hay factories para KeywordLista/Keyword/KeywordMedicion (sólo existen
    // para AdsCampana, AdsMetrica, Articulo, Cliente, Servicio y User), así que
    // los helpers privados hacen ese papel, igual que en KeywordImportTest.

    private function lista(Cliente $cliente, array $overrides = []): KeywordLista
    {
        return KeywordLista::create(array_merge([
            'cliente_id' => $cliente->id,
            'nombre' => 'Lista de Mediciones',
            'canal' => 'seo',
            'estado' => 'en_uso',
        ], $overrides));
    }

    private function keyword(KeywordLista $lista, string $keyword, array $overrides = []): Keyword
    {
        return Keyword::create(array_merge([
            'cliente_id' => $lista->cliente_id,
            'lista_id' => $lista->id,
            'keyword' => $keyword,
            'tipo' => 'principal',
            'estado' => 'en_uso',
        ], $overrides));
    }

    /** Ronda guardada por la vía real (el controlador), no a pelo en la tabla. */
    private function ronda(User $user, KeywordLista $lista, string $fecha, array $mediciones)
    {
        return $this->actingAs($user)->postJson(
            route('admin.keywords.listas.mediciones.store', $lista),
            ['fecha' => $fecha, 'mediciones' => $mediciones]
        );
    }

    // --- store: la ronda ------------------------------------------------

    public function test_store_guarda_una_ronda_completa_con_la_misma_fecha_para_toda_la_lista(): void
    {
        $user = User::factory()->create();
        $lista = $this->lista(Cliente::factory()->create());
        $a = $this->keyword($lista, 'dentista cdmx');
        $b = $this->keyword($lista, 'ortodoncia invisible');
        $c = $this->keyword($lista, 'blanqueamiento dental');

        $response = $this->ronda($user, $lista, '2026-06-01', [
            ['keyword_id' => $a->id, 'posicion' => 4, 'url' => '/dentista', 'nota' => 'Se reescribió el H1'],
            ['keyword_id' => $b->id, 'posicion' => 12],
            ['keyword_id' => $c->id, 'posicion' => 30],
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['fecha', 'lista']);
        $response->assertJsonPath('fecha', '2026-06-01');
        $response->assertJsonPath('lista.id', $lista->id);
        // La media de la ronda que acaba de entrar: (4 + 12 + 30) / 3 = 15.3.
        $this->assertSame(15.3, (float) $response->json('lista.posicion_promedio'));

        $this->assertSame(3, KeywordMedicion::count());
        $this->assertSame(1, KeywordMedicion::distinct()->count('fecha'));

        $this->assertDatabaseHas('keyword_mediciones', [
            'keyword_id' => $a->id,
            'lista_id' => $lista->id,
            'fecha' => '2026-06-01',
            'posicion' => 4,
            'url' => '/dentista',
            'nota' => 'Se reescribió el H1',
        ]);
        $this->assertDatabaseHas('keyword_mediciones', [
            'keyword_id' => $b->id,
            'fecha' => '2026-06-01',
            'posicion' => 12,
        ]);
        $this->assertDatabaseHas('keyword_mediciones', [
            'keyword_id' => $c->id,
            'fecha' => '2026-06-01',
            'posicion' => 30,
        ]);
    }

    public function test_reenviar_la_misma_fecha_corrige_la_ronda_en_vez_de_duplicarla(): void
    {
        $user = User::factory()->create();
        $lista = $this->lista(Cliente::factory()->create());
        $a = $this->keyword($lista, 'keyword a');
        $b = $this->keyword($lista, 'keyword b');

        $this->ronda($user, $lista, '2026-06-01', [
            ['keyword_id' => $a->id, 'posicion' => 10, 'nota' => 'primera captura'],
            ['keyword_id' => $b->id, 'posicion' => 20],
        ])->assertCreated();

        // Misma fecha otra vez: es una corrección, no una segunda medición. El
        // único (keyword_id, fecha) lo impediría con un 500 si el controlador
        // no usara updateOrCreate; el 201 es justamente lo que se prueba.
        $this->ronda($user, $lista, '2026-06-01', [
            ['keyword_id' => $a->id, 'posicion' => 7, 'nota' => 'corregida'],
            ['keyword_id' => $b->id, 'posicion' => 20],
        ])->assertCreated();

        $this->assertSame(2, KeywordMedicion::count());
        $this->assertDatabaseHas('keyword_mediciones', [
            'keyword_id' => $a->id,
            'fecha' => '2026-06-01',
            'posicion' => 7,
            'nota' => 'corregida',
        ]);
        $this->assertSame(7, $a->fresh()->posicion_actual);
        $this->assertNull($a->fresh()->posicion_anterior);
    }

    public function test_keyword_de_otra_lista_devuelve_422_y_no_crea_nada(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente);
        $otraLista = $this->lista($cliente, ['nombre' => 'Otra lista']);

        $propia = $this->keyword($lista, 'keyword propia');
        $ajena = $this->keyword($otraLista, 'keyword ajena');

        $response = $this->ronda($user, $lista, '2026-06-01', [
            ['keyword_id' => $propia->id, 'posicion' => 5],
            ['keyword_id' => $ajena->id, 'posicion' => 9],
        ]);

        $response->assertStatus(422);

        // Ni siquiera la parte legítima del cuerpo debe haber entrado.
        $this->assertSame(0, KeywordMedicion::count());
        $this->assertNull($propia->fresh()->posicion_actual);
    }

    public function test_posicion_nula_se_guarda_como_null_y_no_es_lo_mismo_que_no_medir(): void
    {
        $user = User::factory()->create();
        $lista = $this->lista(Cliente::factory()->create());
        $medida = $this->keyword($lista, 'keyword medida');
        $sinDato = $this->keyword($lista, 'keyword sin dato');
        $noEnviada = $this->keyword($lista, 'keyword no enviada');

        $this->ronda($user, $lista, '2026-06-01', [
            ['keyword_id' => $medida->id, 'posicion' => 8],
            ['keyword_id' => $sinDato->id, 'posicion' => null, 'nota' => 'No aparece en el top 100'],
        ])->assertCreated();

        // "Sin dato" tiene fila con posicion null; "no medida" no tiene fila.
        $this->assertSame(2, KeywordMedicion::count());

        $fila = KeywordMedicion::where('keyword_id', $sinDato->id)->first();
        $this->assertNotNull($fila);
        $this->assertNull($fila->posicion);
        $this->assertSame('No aparece en el top 100', $fila->nota);

        $this->assertSame(0, KeywordMedicion::where('keyword_id', $noEnviada->id)->count());
        $this->assertNull($sinDato->fresh()->posicion_actual);
        $this->assertSame(8, $medida->fresh()->posicion_actual);
    }

    public function test_posicion_cero_es_422(): void
    {
        $user = User::factory()->create();
        $lista = $this->lista(Cliente::factory()->create());
        $k = $this->keyword($lista, 'keyword cero');

        // 0 era el "sin dato" del banco viejo; en el histórico eso es null y un
        // 0 explícito es un error, no un valor.
        $this->ronda($user, $lista, '2026-06-01', [
            ['keyword_id' => $k->id, 'posicion' => 0],
        ])->assertStatus(422)->assertJsonValidationErrors('mediciones.0.posicion');

        $this->assertSame(0, KeywordMedicion::count());
    }

    public function test_posicion_mayor_que_mil_es_422(): void
    {
        $user = User::factory()->create();
        $lista = $this->lista(Cliente::factory()->create());
        $k = $this->keyword($lista, 'keyword grande');

        $this->ronda($user, $lista, '2026-06-01', [
            ['keyword_id' => $k->id, 'posicion' => 1001],
        ])->assertStatus(422)->assertJsonValidationErrors('mediciones.0.posicion');

        $this->assertSame(0, KeywordMedicion::count());
    }

    public function test_registrado_por_queda_con_el_usuario_autenticado(): void
    {
        $user = User::factory()->create(['name' => 'Ana Registradora']);
        $otro = User::factory()->create();
        $lista = $this->lista(Cliente::factory()->create());
        $k = $this->keyword($lista, 'keyword firmada');

        $this->ronda($user, $lista, '2026-06-01', [
            ['keyword_id' => $k->id, 'posicion' => 3],
        ])->assertCreated();

        $this->assertDatabaseHas('keyword_mediciones', [
            'keyword_id' => $k->id,
            'registrado_por' => $user->id,
        ]);
        $this->assertDatabaseMissing('keyword_mediciones', [
            'keyword_id' => $k->id,
            'registrado_por' => $otro->id,
        ]);
    }

    // --- la caché derivada ----------------------------------------------

    public function test_la_cache_sale_de_las_dos_ultimas_mediciones_por_fecha(): void
    {
        $user = User::factory()->create();
        $lista = $this->lista(Cliente::factory()->create());
        $k = $this->keyword($lista, 'keyword con historia');

        $this->ronda($user, $lista, '2026-03-01', [['keyword_id' => $k->id, 'posicion' => 10]])->assertCreated();
        $this->assertSame(10, $k->fresh()->posicion_actual);
        $this->assertNull($k->fresh()->posicion_anterior);

        $this->ronda($user, $lista, '2026-06-01', [['keyword_id' => $k->id, 'posicion' => 5]])->assertCreated();
        $this->assertSame(5, $k->fresh()->posicion_actual);
        $this->assertSame(10, $k->fresh()->posicion_anterior);
    }

    public function test_una_ronda_con_fecha_anterior_insertada_despues_no_cambia_la_cache(): void
    {
        $user = User::factory()->create();
        $lista = $this->lista(Cliente::factory()->create());
        $k = $this->keyword($lista, 'keyword desordenada');

        $this->ronda($user, $lista, '2026-03-01', [['keyword_id' => $k->id, 'posicion' => 10]])->assertCreated();
        $this->ronda($user, $lista, '2026-06-01', [['keyword_id' => $k->id, 'posicion' => 5]])->assertCreated();

        // Ronda vieja que se captura tarde: es la última insertada pero la más
        // antigua por fecha, así que no debe tocar `posicion_actual`.
        $this->ronda($user, $lista, '2026-01-15', [['keyword_id' => $k->id, 'posicion' => 30]])->assertCreated();

        $k->refresh();
        $this->assertSame(5, $k->posicion_actual);
        $this->assertSame(10, $k->posicion_anterior);
        $this->assertSame(3, KeywordMedicion::where('keyword_id', $k->id)->count());
    }

    public function test_borrar_la_medicion_mas_reciente_hace_retroceder_la_cache(): void
    {
        $user = User::factory()->create();
        $lista = $this->lista(Cliente::factory()->create());
        $k = $this->keyword($lista, 'keyword a destiempo');

        $this->ronda($user, $lista, '2026-01-15', [['keyword_id' => $k->id, 'posicion' => 30]])->assertCreated();
        $this->ronda($user, $lista, '2026-03-01', [['keyword_id' => $k->id, 'posicion' => 10]])->assertCreated();
        $this->ronda($user, $lista, '2026-06-01', [['keyword_id' => $k->id, 'posicion' => 5]])->assertCreated();

        $ultima = KeywordMedicion::where('keyword_id', $k->id)->orderByDesc('fecha')->first();

        $this->actingAs($user)
            ->deleteJson(route('admin.keywords.listas.mediciones.destroy', $ultima))
            ->assertOk()
            ->assertJson(['deleted' => true]);

        $k->refresh();
        $this->assertSame(10, $k->posicion_actual);
        $this->assertSame(30, $k->posicion_anterior);
    }

    public function test_corregir_una_medicion_con_update_resincroniza_la_cache(): void
    {
        $user = User::factory()->create();
        $lista = $this->lista(Cliente::factory()->create());
        $k = $this->keyword($lista, 'keyword corregida');

        $this->ronda($user, $lista, '2026-03-01', [['keyword_id' => $k->id, 'posicion' => 10]])->assertCreated();
        $this->ronda($user, $lista, '2026-06-01', [['keyword_id' => $k->id, 'posicion' => 5]])->assertCreated();

        $reciente = KeywordMedicion::where('keyword_id', $k->id)->orderByDesc('fecha')->first();

        $this->actingAs($user)
            ->putJson(route('admin.keywords.listas.mediciones.update', $reciente), [
                'posicion' => 2,
                'nota' => 'Estaba mal leída',
            ])
            ->assertOk()
            ->assertJsonPath('medicion.posicion', 2)
            ->assertJsonPath('medicion.nota', 'Estaba mal leída');

        $k->refresh();
        $this->assertSame(2, $k->posicion_actual);
        $this->assertSame(10, $k->posicion_anterior);
    }

    public function test_update_devuelve_la_medicion_y_la_lista_con_el_promedio_ya_recalculado(): void
    {
        $user = User::factory()->create();
        $lista = $this->lista(Cliente::factory()->create());
        $a = $this->keyword($lista, 'aaa keyword');
        $b = $this->keyword($lista, 'bbb keyword');

        // Media de la lista antes de corregir: (10 + 20) / 2 = 15.
        $this->ronda($user, $lista, '2026-06-01', [
            ['keyword_id' => $a->id, 'posicion' => 10],
            ['keyword_id' => $b->id, 'posicion' => 20],
        ])->assertCreated();

        $medicion = KeywordMedicion::where('keyword_id', $a->id)->first();

        $response = $this->actingAs($user)->putJson(
            route('admin.keywords.listas.mediciones.update', $medicion),
            ['posicion' => 4]
        );

        $response->assertOk();
        $response->assertJsonStructure([
            'medicion' => ['id', 'keyword_id', 'fecha', 'posicion', 'url', 'nota', 'registrado_por'],
            'lista',
        ]);
        $response->assertJsonPath('medicion.id', $medicion->id);
        $response->assertJsonPath('medicion.posicion', 4);
        $response->assertJsonPath('medicion.registrado_por', $user->name);

        // La fila ya no viaja en la raíz: el cliente lee `medicion`.
        $this->assertArrayNotHasKey('posicion', $response->json());
        $this->assertArrayNotHasKey('id', $response->json());

        // El motivo de devolver la lista: la media del banco cambia con la
        // corrección —(4 + 20) / 2 = 12— y la tabla debe poder repintarla sin
        // recargar la página.
        $response->assertJsonPath('lista.id', $lista->id);
        $this->assertSame(12.0, (float) $response->json('lista.posicion_promedio'));
    }

    public function test_update_devuelve_lista_null_si_la_medicion_se_quedo_sin_lista(): void
    {
        $user = User::factory()->create();
        $lista = $this->lista(Cliente::factory()->create());
        $k = $this->keyword($lista, 'keyword huerfana');

        $this->ronda($user, $lista, '2026-06-01', [['keyword_id' => $k->id, 'posicion' => 9]])->assertCreated();

        // Borrado real de la lista: el nullOnDelete de la FK deja la medición
        // sin `lista_id`, que es el caso que el contrato admite.
        $lista->forceDelete();

        $medicion = KeywordMedicion::where('keyword_id', $k->id)->first();
        $this->assertNull($medicion->lista_id);

        $response = $this->actingAs($user)->putJson(
            route('admin.keywords.listas.mediciones.update', $medicion),
            ['posicion' => 3]
        );

        $response->assertOk();
        $response->assertJsonPath('medicion.posicion', 3);
        $this->assertNull($response->json('lista'));

        $this->assertSame(3, $k->fresh()->posicion_actual);
    }

    public function test_keyword_sin_ninguna_medicion_queda_con_ambas_columnas_en_null(): void
    {
        $user = User::factory()->create();
        $lista = $this->lista(Cliente::factory()->create());
        $k = $this->keyword($lista, 'keyword que se queda sin historia');

        $this->ronda($user, $lista, '2026-06-01', [['keyword_id' => $k->id, 'posicion' => 5]])->assertCreated();
        $this->assertSame(5, $k->fresh()->posicion_actual);

        $unica = KeywordMedicion::where('keyword_id', $k->id)->first();

        $this->actingAs($user)
            ->deleteJson(route('admin.keywords.listas.mediciones.destroy', $unica))
            ->assertOk();

        $k->refresh();
        $this->assertNull($k->posicion_actual);
        $this->assertNull($k->posicion_anterior);
        $this->assertSame(0, KeywordMedicion::where('keyword_id', $k->id)->count());
    }

    // --- index: la matriz ------------------------------------------------

    public function test_index_devuelve_fechas_ordenadas_una_fila_por_keyword_y_el_mapa_por_fecha(): void
    {
        $user = User::factory()->create();
        $lista = $this->lista(Cliente::factory()->create());
        $a = $this->keyword($lista, 'aaa keyword');
        $b = $this->keyword($lista, 'bbb keyword');

        // Se capturan en desorden a propósito: `fechas` debe salir ordenada.
        $this->ronda($user, $lista, '2026-06-01', [
            ['keyword_id' => $a->id, 'posicion' => 5],
            ['keyword_id' => $b->id, 'posicion' => 15],
        ])->assertCreated();
        $this->ronda($user, $lista, '2026-03-01', [
            ['keyword_id' => $a->id, 'posicion' => 10, 'nota' => 'Enlazada desde el blog'],
            ['keyword_id' => $b->id, 'posicion' => 20],
        ])->assertCreated();

        $response = $this->actingAs($user)->getJson(route('admin.keywords.listas.mediciones.index', $lista));

        $response->assertOk();
        $response->assertJsonPath('fechas', ['2026-03-01', '2026-06-01']);
        $response->assertJsonCount(2, 'keywords');
        $response->assertJsonPath('keywords.0.keyword', 'aaa keyword');
        $response->assertJsonPath('keywords.1.keyword', 'bbb keyword');
        $response->assertJsonPath('keywords.0.mediciones.2026-03-01.posicion', 10);
        $response->assertJsonPath('keywords.0.mediciones.2026-03-01.nota', 'Enlazada desde el blog');
        $response->assertJsonPath('keywords.0.mediciones.2026-06-01.posicion', 5);
        $response->assertJsonPath('keywords.1.mediciones.2026-06-01.posicion', 15);
        $response->assertJsonPath('keywords.0.mediciones.2026-03-01.registrado_por', $user->name);
    }

    public function test_index_no_mezcla_las_mediciones_de_otra_lista(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente);
        $otra = $this->lista($cliente, ['nombre' => 'Lista vecina']);

        $mia = $this->keyword($lista, 'keyword mia');
        $suya = $this->keyword($otra, 'keyword suya');

        $this->ronda($user, $lista, '2026-06-01', [['keyword_id' => $mia->id, 'posicion' => 5]])->assertCreated();
        $this->ronda($user, $otra, '2026-07-01', [['keyword_id' => $suya->id, 'posicion' => 50]])->assertCreated();

        $response = $this->actingAs($user)->getJson(route('admin.keywords.listas.mediciones.index', $lista));

        $response->assertOk();
        $response->assertJsonPath('fechas', ['2026-06-01']);
        $response->assertJsonCount(1, 'keywords');
        $response->assertJsonPath('keywords.0.keyword', 'keyword mia');
    }

    public function test_promedios_solo_cuentan_las_keywords_con_posicion_no_nula(): void
    {
        $user = User::factory()->create();
        $lista = $this->lista(Cliente::factory()->create());
        $a = $this->keyword($lista, 'aaa keyword');
        $b = $this->keyword($lista, 'bbb keyword');
        $c = $this->keyword($lista, 'ccc keyword');

        // Media real de la ronda: (10 + 20) / 2 = 15. Si la keyword sin dato
        // contara como cero, saldría 10.
        $this->ronda($user, $lista, '2026-06-01', [
            ['keyword_id' => $a->id, 'posicion' => 10],
            ['keyword_id' => $b->id, 'posicion' => 20],
            ['keyword_id' => $c->id, 'posicion' => null],
        ])->assertCreated();

        // Ronda entera sin dato: no hay media que dar, y 0 sería mentira.
        $this->ronda($user, $lista, '2026-07-01', [
            ['keyword_id' => $a->id, 'posicion' => null],
            ['keyword_id' => $b->id, 'posicion' => null],
        ])->assertCreated();

        $response = $this->actingAs($user)->getJson(route('admin.keywords.listas.mediciones.index', $lista));

        $response->assertOk();
        $this->assertSame(15.0, (float) $response->json('promedios.2026-06-01'));
        $this->assertNull($response->json('promedios.2026-07-01'));
    }

    public function test_lista_sin_mediciones_devuelve_las_tres_claves_vacias(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $vacia = $this->lista($cliente, ['nombre' => 'Lista vacia']);

        $response = $this->actingAs($user)->getJson(route('admin.keywords.listas.mediciones.index', $vacia));

        $response->assertOk();
        $response->assertJsonStructure(['fechas', 'keywords', 'promedios']);
        $this->assertSame([], $response->json('fechas'));
        $this->assertSame([], $response->json('keywords'));
        $this->assertSame([], $response->json('promedios'));

        // Con keywords pero sin ninguna ronda: la matriz existe, sólo que sin
        // columnas. Tampoco es un error.
        $conKeywords = $this->lista($cliente, ['nombre' => 'Lista sin rondas']);
        $this->keyword($conKeywords, 'keyword sin medir');

        $response = $this->actingAs($user)->getJson(route('admin.keywords.listas.mediciones.index', $conKeywords));

        $response->assertOk();
        $this->assertSame([], $response->json('fechas'));
        $this->assertSame([], $response->json('promedios'));
        $response->assertJsonCount(1, 'keywords');
        $this->assertSame([], $response->json('keywords.0.mediciones'));
    }

    // --- la edición desde la ficha de keyword ----------------------------

    /** Payload mínimo válido para PUT admin.keywords.update. */
    private function fichaBase(Keyword $k, array $overrides = []): array
    {
        return array_merge([
            'cliente_id' => $k->cliente_id,
            'lista_id' => $k->lista_id,
            'keyword' => $k->keyword,
            'tipo' => $k->tipo,
            'estado' => $k->estado->value,
        ], $overrides);
    }

    public function test_editar_la_posicion_desde_la_ficha_crea_una_medicion_de_hoy(): void
    {
        Carbon::setTestNow('2026-09-03 10:00:00');

        $user = User::factory()->create();
        $lista = $this->lista(Cliente::factory()->create());
        $k = $this->keyword($lista, 'keyword desde la ficha', ['url_asignada' => '/ficha']);

        $response = $this->actingAs($user)->putJson(
            route('admin.keywords.update', $k),
            $this->fichaBase($k, ['posicion_actual' => 7])
        );

        $response->assertOk();
        $response->assertJsonPath('posicion_actual', 7);

        $this->assertDatabaseHas('keyword_mediciones', [
            'keyword_id' => $k->id,
            'lista_id' => $lista->id,
            'fecha' => '2026-09-03',
            'posicion' => 7,
            'url' => '/ficha',
            'registrado_por' => $user->id,
        ]);

        $k->refresh();
        $this->assertSame(7, $k->posicion_actual);
        $this->assertNull($k->posicion_anterior);

        Carbon::setTestNow();
    }

    public function test_cambiar_otro_campo_no_crea_ninguna_medicion(): void
    {
        Carbon::setTestNow('2026-09-03 10:00:00');

        $user = User::factory()->create();
        $lista = $this->lista(Cliente::factory()->create());
        $k = $this->keyword($lista, 'keyword sin tocar la posicion');

        $response = $this->actingAs($user)->putJson(
            route('admin.keywords.update', $k),
            $this->fichaBase($k, ['volumen_busqueda' => 4400])
        );

        $response->assertOk();
        $response->assertJsonPath('volumen_busqueda', 4400);

        $this->assertSame(0, KeywordMedicion::count());
        $this->assertNull($k->fresh()->posicion_actual);

        Carbon::setTestNow();
    }

    public function test_guardar_la_misma_posicion_que_ya_tenia_no_crea_una_medicion_nueva(): void
    {
        Carbon::setTestNow('2026-09-03 10:00:00');

        $user = User::factory()->create();
        $lista = $this->lista(Cliente::factory()->create());
        $k = $this->keyword($lista, 'keyword estable');

        // La posición viene de una ronda de otro día: reguardar la ficha sin
        // cambiarla no debe inventar una medición de hoy.
        $this->ronda($user, $lista, '2026-06-01', [['keyword_id' => $k->id, 'posicion' => 5]])->assertCreated();
        $this->assertSame(1, KeywordMedicion::count());

        $response = $this->actingAs($user)->putJson(
            route('admin.keywords.update', $k->fresh()),
            $this->fichaBase($k, ['posicion_actual' => 5, 'volumen_busqueda' => 900])
        );

        $response->assertOk();
        $this->assertSame(1, KeywordMedicion::count());
        $this->assertDatabaseMissing('keyword_mediciones', [
            'keyword_id' => $k->id,
            'fecha' => '2026-09-03',
        ]);

        $k->refresh();
        $this->assertSame(5, $k->posicion_actual);
        $this->assertSame(900, $k->volumen_busqueda);

        Carbon::setTestNow();
    }
}
