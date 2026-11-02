<?php

namespace Tests\Feature\Admin;

use App\Enums\EstadoOnPageAccion;
use App\Models\Cliente;
use App\Models\Keyword;
use App\Models\KeywordCluster;
use App\Models\KeywordMedicion;
use App\Models\MetricaCliente;
use App\Models\MetricaClienteValor;
use App\Models\SeoActividadNota;
use App\Models\SeoCampana;
use App\Models\SeoOnPageAccion;
use App\Models\SeoPlan;
use App\Models\Servicio;
use App\Models\User;
use App\Support\ClienteActivo;
use App\Support\SeoPlan\Resultados;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEO Plan: todo se acota al cliente activo (sesión) y la actividad cuelga de
 * una campaña SEO del cliente. Lo que más importa probar es esa frontera:
 * un id de otro cliente nunca debe leerse ni modificarse.
 */
class SeoPlanTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $cliente;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cliente = Cliente::factory()->create();
        $this->usuario = User::factory()->create();
    }

    /** Petición como usuario con el cliente indicado (o el de la prueba) como activo. */
    private function como(?Cliente $cliente = null): self
    {
        return $this->actingAs($this->usuario)->withSession([ClienteActivo::CLAVE => ($cliente ?? $this->cliente)->id]);
    }

    private function campana(?Cliente $cliente = null): SeoCampana
    {
        return SeoCampana::create([
            'cliente_id' => ($cliente ?? $this->cliente)->id,
            'servicio_id' => Servicio::factory()->create()->id,
            'nombre' => 'Campaña SEO',
            'url_sitio' => 'https://ejemplo.com',
            'estado' => 'activa',
            'fase_actual' => 'auditoria',
            'ciclo_actual' => 1,
            'fecha_inicio' => now()->subMonth(),
        ]);
    }

    private function actividad(SeoCampana $campana, array $extra = []): SeoOnPageAccion
    {
        return SeoOnPageAccion::create($extra + [
            'seo_campana_id' => $campana->id,
            'tipo' => 'on_page',
            'url_pagina' => '',
            'accion' => 'Optimizar títulos',
            'estado' => 'pendiente',
        ]);
    }

    private function keyword(string $texto, array $extra = []): Keyword
    {
        return Keyword::create($extra + [
            'cliente_id' => $this->cliente->id,
            'keyword' => $texto,
            'tipo' => 'principal',
            'estado' => 'en_uso',
        ]);
    }

    // --- pantalla ------------------------------------------------------

    public function test_sin_cliente_activo_muestra_estado_vacio(): void
    {
        $this->actingAs($this->usuario)->get(route('admin.seo-plan.index'))
            ->assertOk()
            ->assertSee('Elige un cliente en el selector del menú');
    }

    public function test_con_cliente_activo_muestra_las_tres_pestanas_y_estados_vacios(): void
    {
        $this->como()->get(route('admin.seo-plan.index'))
            ->assertOk()
            ->assertSee('Resultados')
            ->assertSee('Aún no hay actividades')
            ->assertSee('Todavía no hay posiciones registradas');
    }

    public function test_guardar_meta_general_crea_y_actualiza_una_sola_fila(): void
    {
        $this->como()->put(route('admin.seo-plan.plan'), ['meta_general' => 'Duplicar llamadas'])->assertRedirect();
        $this->como()->put(route('admin.seo-plan.plan'), ['meta_general' => 'Triplicar llamadas'])->assertRedirect();

        $this->assertSame(1, SeoPlan::query()->where('cliente_id', $this->cliente->id)->count());
        $this->assertSame('Triplicar llamadas', SeoPlan::first()->meta_general);
    }

    public function test_las_metas_muestran_semaforo_de_las_metricas_existentes(): void
    {
        $metrica = MetricaCliente::create([
            'cliente_id' => $this->cliente->id, 'nombre' => 'Llamadas al mes', 'meta' => 40,
            'sentido' => 'mayor', 'umbral_amarillo_pct' => 80, 'activa' => true,
        ]);
        MetricaClienteValor::create(['metrica_id' => $metrica->id, 'periodo' => now()->startOfMonth(), 'valor' => 45]);

        $this->como()->get(route('admin.seo-plan.index'))
            ->assertOk()
            ->assertSee('Llamadas al mes')
            ->assertSee('Verde');
    }

    // --- actividades ---------------------------------------------------

    public function test_crear_actividad_en_campana_del_cliente(): void
    {
        $campana = $this->campana();
        $cluster = KeywordCluster::create(['cliente_id' => $this->cliente->id, 'nombre' => 'Servicios']);

        $this->como()->post(route('admin.seo-plan.actividades.store'), [
            'seo_campana_id' => $campana->id,
            'accion' => 'Escribir guía',
            'tipo' => 'contenido',
            'cluster_id' => $cluster->id,
            'fecha_limite' => '2026-12-01',
        ])->assertRedirect(route('admin.seo-plan.index'));

        $this->assertDatabaseHas('seo_onpage_acciones', [
            'seo_campana_id' => $campana->id, 'accion' => 'Escribir guía', 'tipo' => 'contenido',
            'cluster_id' => $cluster->id, 'estado' => 'pendiente',
        ]);
    }

    public function test_no_se_crea_actividad_en_campana_de_otro_cliente(): void
    {
        $ajena = $this->campana(Cliente::factory()->create());

        $this->como()->post(route('admin.seo-plan.actividades.store'), [
            'seo_campana_id' => $ajena->id, 'accion' => 'X', 'tipo' => 'tecnica',
        ])->assertSessionHasErrors('seo_campana_id');

        $this->assertDatabaseCount('seo_onpage_acciones', 0);
    }

    public function test_actualizar_y_eliminar_actividad(): void
    {
        $accion = $this->actividad($this->campana());

        $this->como()->put(route('admin.seo-plan.actividades.update', $accion), [
            'accion' => 'Nuevo texto', 'tipo' => 'backlink', 'estado' => 'en_revision',
            'medicion_antes' => '3 enlaces',
        ])->assertRedirect();

        $accion->refresh();
        $this->assertSame('Nuevo texto', $accion->accion);
        $this->assertSame(EstadoOnPageAccion::EnRevision, $accion->estado);

        $this->como()->delete(route('admin.seo-plan.actividades.destroy', $accion))->assertRedirect();
        $this->assertDatabaseMissing('seo_onpage_acciones', ['id' => $accion->id]);
    }

    public function test_actividad_de_otro_cliente_responde_404(): void
    {
        $ajena = $this->actividad($this->campana(Cliente::factory()->create()));

        $this->como()->patchJson(route('admin.seo-plan.actividades.mover', $ajena), ['estado' => 'completada'])->assertNotFound();
        $this->como()->delete(route('admin.seo-plan.actividades.destroy', $ajena))->assertNotFound();
        $this->como()->getJson(route('admin.seo-plan.notas.index', $ajena))->assertNotFound();
    }

    // --- kanban --------------------------------------------------------

    public function test_mover_cambia_estado_y_persiste_el_orden_de_la_columna(): void
    {
        $campana = $this->campana();
        $a = $this->actividad($campana, ['accion' => 'A', 'estado' => 'en_progreso', 'orden' => 1]);
        $b = $this->actividad($campana, ['accion' => 'B', 'estado' => 'en_progreso', 'orden' => 2]);
        $c = $this->actividad($campana, ['accion' => 'C', 'estado' => 'pendiente', 'orden' => 1]);

        // C pasa a la columna «En curso» en medio de A y B.
        $this->como()->patchJson(route('admin.seo-plan.actividades.mover', $c), ['estado' => 'en_progreso', 'posicion' => 1])
            ->assertOk()->assertJson(['estado' => 'en_progreso']);

        $this->assertSame(['A', 'C', 'B'], SeoOnPageAccion::where('estado', 'en_progreso')->orderBy('orden')->pluck('accion')->all());
        $this->assertSame([1, 2, 3], SeoOnPageAccion::where('estado', 'en_progreso')->orderBy('orden')->pluck('orden')->all());
    }

    public function test_mover_a_hecha_fija_cerrada_en_y_sacarla_la_limpia(): void
    {
        $accion = $this->actividad($this->campana());

        $this->como()->patchJson(route('admin.seo-plan.actividades.mover', $accion), ['estado' => 'completada'])
            ->assertOk()->assertJson(['cerrada_en' => today()->toDateString()]);
        $this->assertNotNull($accion->fresh()->cerrada_en);

        $this->como()->patchJson(route('admin.seo-plan.actividades.mover', $accion), ['estado' => 'en_progreso'])->assertOk();
        $this->assertNull($accion->fresh()->cerrada_en);
    }

    public function test_mover_rechaza_un_estado_inexistente(): void
    {
        $accion = $this->actividad($this->campana());

        $this->como()->patchJson(route('admin.seo-plan.actividades.mover', $accion), ['estado' => 'volando'])
            ->assertUnprocessable();
    }

    // --- notas ---------------------------------------------------------

    public function test_notas_se_agregan_se_listan_y_se_eliminan(): void
    {
        $accion = $this->actividad($this->campana());

        $this->como()->postJson(route('admin.seo-plan.notas.store', $accion), ['nota' => 'Se publicó el borrador'])
            ->assertCreated()->assertJson(['nota' => 'Se publicó el borrador', 'autor' => $this->usuario->name]);

        $this->como()->getJson(route('admin.seo-plan.notas.index', $accion))->assertOk()->assertJsonCount(1);

        $nota = SeoActividadNota::first();
        $this->como()->deleteJson(route('admin.seo-plan.notas.destroy', [$accion, $nota]))->assertOk();
        $this->assertDatabaseCount('seo_actividad_notas', 0);
    }

    public function test_nota_vacia_no_se_guarda(): void
    {
        $accion = $this->actividad($this->campana());

        $this->como()->postJson(route('admin.seo-plan.notas.store', $accion), ['nota' => ''])->assertUnprocessable();
    }

    // --- clústeres y keywords -----------------------------------------

    public function test_crud_de_clusters(): void
    {
        $this->como()->post(route('admin.seo-plan.clusters.store'), ['nombre' => 'Hoteles', 'url' => '/hoteles'])->assertRedirect();
        $cluster = KeywordCluster::first();
        $this->assertSame($this->cliente->id, $cluster->cliente_id);

        $this->como()->put(route('admin.seo-plan.clusters.update', $cluster), ['nombre' => 'Hoteles MX'])->assertRedirect();
        $this->assertSame('Hoteles MX', $cluster->fresh()->nombre);

        $this->como()->delete(route('admin.seo-plan.clusters.destroy', $cluster))->assertRedirect();
        $this->assertDatabaseCount('keyword_clusters', 0);
    }

    public function test_cluster_requiere_nombre_y_no_se_toca_el_de_otro_cliente(): void
    {
        $this->como()->post(route('admin.seo-plan.clusters.store'), ['nombre' => ''])->assertSessionHasErrors('nombre');

        $ajeno = KeywordCluster::create(['cliente_id' => Cliente::factory()->create()->id, 'nombre' => 'Ajeno']);
        $this->como()->put(route('admin.seo-plan.clusters.update', $ajeno), ['nombre' => 'Mío'])->assertNotFound();
        $this->como()->delete(route('admin.seo-plan.clusters.destroy', $ajeno))->assertNotFound();
    }

    public function test_asignar_keyword_a_cluster_y_soltarla(): void
    {
        $cluster = KeywordCluster::create(['cliente_id' => $this->cliente->id, 'nombre' => 'Servicios']);
        $keyword = $this->keyword('agencia seo');

        $this->como()->patchJson(route('admin.seo-plan.keywords.cluster', $keyword), ['cluster_id' => $cluster->id])
            ->assertOk()->assertJson(['cluster_id' => $cluster->id]);
        $this->assertSame($cluster->id, $keyword->fresh()->cluster_id);

        $this->como()->patchJson(route('admin.seo-plan.keywords.cluster', $keyword), ['cluster_id' => null])->assertOk();
        $this->assertNull($keyword->fresh()->cluster_id);
    }

    public function test_no_se_asigna_keyword_ajena_ni_a_cluster_ajeno(): void
    {
        $otro = Cliente::factory()->create();
        $clusterAjeno = KeywordCluster::create(['cliente_id' => $otro->id, 'nombre' => 'Ajeno']);
        $keywordAjena = Keyword::create(['cliente_id' => $otro->id, 'keyword' => 'ajena', 'tipo' => 'principal', 'estado' => 'en_uso']);
        $keywordMia = $this->keyword('mia');

        $this->como()->patchJson(route('admin.seo-plan.keywords.cluster', $keywordAjena), ['cluster_id' => null])->assertNotFound();
        $this->como()->patchJson(route('admin.seo-plan.keywords.cluster', $keywordMia), ['cluster_id' => $clusterAjeno->id])->assertNotFound();
        $this->assertNull($keywordMia->fresh()->cluster_id);
    }

    public function test_borrar_cluster_suelta_keywords_sin_borrarlas(): void
    {
        $cluster = KeywordCluster::create(['cliente_id' => $this->cliente->id, 'nombre' => 'Servicios']);
        $keyword = $this->keyword('agencia seo', ['cluster_id' => $cluster->id]);

        $this->como()->delete(route('admin.seo-plan.clusters.destroy', $cluster))->assertRedirect();

        $this->assertNull($keyword->fresh()->cluster_id);
    }

    public function test_pestana_keywords_lee_posiciones_de_las_mediciones(): void
    {
        $cluster = KeywordCluster::create(['cliente_id' => $this->cliente->id, 'nombre' => 'Servicios']);
        $keyword = $this->keyword('agencia seo', ['cluster_id' => $cluster->id]);
        KeywordMedicion::create(['keyword_id' => $keyword->id, 'fecha' => '2026-09-01', 'posicion' => 12]);
        KeywordMedicion::create(['keyword_id' => $keyword->id, 'fecha' => '2026-10-01', 'posicion' => 7]);

        $respuesta = $this->como()->get(route('admin.seo-plan.index'))->assertOk()->assertSee('agencia seo');

        $posiciones = \App\Support\SeoPlan\PosicionesKeyword::para(collect([$keyword]));
        $this->assertSame(['actual' => 7, 'anterior' => 12], $posiciones[$keyword->id]);
        $respuesta->assertSee('Keywords por rango de posición');
    }

    // --- resultados ----------------------------------------------------

    public function test_resultados_cuentan_rangos_y_cierres_a_tiempo_o_tarde(): void
    {
        $rangos = Resultados::rangos([
            1 => ['actual' => 2, 'anterior' => null],
            2 => ['actual' => 9, 'anterior' => 20],
            3 => ['actual' => 15, 'anterior' => null],
            4 => ['actual' => 40, 'anterior' => null],
            5 => ['actual' => null, 'anterior' => null],
        ]);
        $this->assertSame(['top3' => 1, 'top10' => 1, 'top20' => 1, 'resto' => 1, 'sin_posicion' => 1, 'con_posicion' => 4], $rangos);

        $campana = $this->campana();
        $this->actividad($campana, ['estado' => 'completada', 'fecha_limite' => '2026-10-10', 'cerrada_en' => '2026-10-05']);
        $this->actividad($campana, ['estado' => 'completada', 'fecha_limite' => '2026-10-10', 'cerrada_en' => '2026-10-20']);
        $this->actividad($campana, ['estado' => 'completada']);
        $this->actividad($campana, ['estado' => 'pendiente', 'fecha_limite' => now()->subDay()]);

        $cierres = Resultados::cierres(SeoOnPageAccion::all());

        $this->assertSame(1, $cierres['a_tiempo']);
        $this->assertSame(1, $cierres['tarde']);
        $this->assertSame(1, $cierres['sin_fecha_limite']);
        $this->assertSame(1, $cierres['vencidas']);
        $this->assertSame(3, $cierres['cerradas']);
    }

    public function test_resultados_muestran_medicion_antes_y_despues_solo_de_cerradas(): void
    {
        $campana = $this->campana();
        $this->actividad($campana, ['accion' => 'Reescribir H1', 'estado' => 'completada', 'medicion_antes' => 'posición 14', 'medicion_despues' => 'posición 6']);
        $this->actividad($campana, ['accion' => 'Abierta con dato', 'estado' => 'pendiente', 'medicion_antes' => 'posición 30']);

        $this->como()->get(route('admin.seo-plan.index'))
            ->assertOk()
            ->assertSee('Reescribir H1')
            ->assertSee('posición 6')
            ->assertDontSee('posición 30');
    }

    // --- el módulo SEO existente sigue igual --------------------------

    public function test_el_modulo_seo_sigue_guardando_acciones_sin_las_columnas_nuevas(): void
    {
        $campana = $this->campana();

        $this->actingAs($this->usuario)->postJson(route('admin.seo.onpage.store', $campana), [
            'url_pagina' => '/servicios/seo', 'accion' => 'Optimizar title', 'estado' => 'completada',
        ])->assertCreated();

        $accion = SeoOnPageAccion::first();
        $this->assertNull($accion->tipo);
        $this->assertSame(today()->toDateString(), $accion->cerrada_en->toDateString());
    }
}
