<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\SeoCampana;
use App\Models\SeoFaseAuditoria;
use App\Models\SeoPosicion;
use App\Models\SeoReporte;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Tests for the rewritten SeoController: index() is now a client-picker
 * (one row per client with an SEO servicio, optionally carrying their most
 * recent SeoCampana's summary), and store()/update()/destroy() are JSON
 * endpoints for an AJAX modal. show() and the sub-resource controllers
 * (SeoFaseController, SeoPosicionController, etc.) are unchanged and are
 * covered by SeoOnPageAccionTest and friends — not duplicated here.
 */
class SeoCampanasTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Creates a Cliente with an SEO-type Servicio (the precondition for
     * appearing in the index() client-picker at all).
     */
    private function clienteConServicioSeo(array $clienteOverrides = [], array $servicioOverrides = []): array
    {
        $cliente = Cliente::factory()->create($clienteOverrides);
        $servicio = Servicio::factory()->create(array_merge([
            'cliente_id' => $cliente->id,
            'tipo' => 'seo',
        ], $servicioOverrides));

        return [$cliente, $servicio];
    }

    /**
     * Builds a SeoCampana directly via Eloquent, mirroring
     * SeoController::store()'s creation fields exactly, without going
     * through HTTP (so it doesn't disturb the acting user of the calling
     * test and doesn't create the 4 phase rows unless asked).
     */
    private function campana(Cliente $cliente, Servicio $servicio, array $overrides = []): SeoCampana
    {
        return SeoCampana::create(array_merge([
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña SEO de Prueba',
            'url_sitio' => 'https://ejemplo.com',
            'estado' => 'activa',
            'fase_actual' => 'auditoria',
            'ciclo_actual' => 1,
            'fecha_inicio' => now()->subMonths(2),
        ], $overrides));
    }

    // --- index -----------------------------------------------------------

    public function test_index_requires_authentication(): void
    {
        $response = $this->get(route('admin.seo.index'));

        $response->assertRedirect('/login');
    }

    public function test_index_returns_ok_and_correct_view(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.seo.index'));

        $response->assertOk();
        $response->assertViewIs('admin.seo.index');
    }

    public function test_client_without_seo_servicio_is_excluded(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'google_ads']);

        $response = $this->actingAs($user)->get(route('admin.seo.index'));

        $clientes = $response->viewData('clientes');
        $this->assertFalse($clientes->contains('cliente_id', $cliente->id));
    }

    public function test_client_with_seo_servicio_is_included(): void
    {
        $user = User::factory()->create();
        [$cliente] = $this->clienteConServicioSeo();

        $response = $this->actingAs($user)->get(route('admin.seo.index'));

        $clientes = $response->viewData('clientes');
        $this->assertTrue($clientes->contains('cliente_id', $cliente->id));
    }

    public function test_client_with_seo_servicio_and_no_campana_has_null_campaign_fields(): void
    {
        $user = User::factory()->create();
        [$cliente] = $this->clienteConServicioSeo();

        $response = $this->actingAs($user)->get(route('admin.seo.index'));

        $row = $response->viewData('clientes')->firstWhere('cliente_id', $cliente->id);

        $this->assertNotNull($row);
        $this->assertNull($row['campana_id']);
        $this->assertNull($row['campana_nombre']);
        $this->assertNull($row['url_sitio']);
        $this->assertNull($row['servicio_id']);
        $this->assertNull($row['estado']);
        $this->assertNull($row['fase_actual']);
        $this->assertNull($row['ciclo_actual']);
        $this->assertNull($row['fecha_inicio']);
        $this->assertNull($row['notas']);
        $this->assertNull($row['seo_score']);
        $this->assertNull($row['trafico_actual']);
        $this->assertNull($row['show_url']);
    }

    public function test_client_with_campana_shows_real_campaign_data(): void
    {
        $user = User::factory()->create();
        [$cliente, $servicio] = $this->clienteConServicioSeo();
        $campana = $this->campana($cliente, $servicio, [
            'nombre' => 'Campaña Real de Cliente',
            'estado' => 'pausada',
            'fase_actual' => 'estrategia',
            'ciclo_actual' => 2,
        ]);

        $response = $this->actingAs($user)->get(route('admin.seo.index'));

        $row = $response->viewData('clientes')->firstWhere('cliente_id', $cliente->id);

        $this->assertSame($campana->id, $row['campana_id']);
        $this->assertSame('Campaña Real de Cliente', $row['campana_nombre']);
        $this->assertSame('pausada', $row['estado']);
        $this->assertSame('estrategia', $row['fase_actual']);
        $this->assertSame(2, $row['ciclo_actual']);
        $this->assertSame(route('admin.seo.show', $campana->id), $row['show_url']);
    }

    public function test_only_most_recently_created_campana_is_surfaced(): void
    {
        $user = User::factory()->create();
        [$cliente, $servicio] = $this->clienteConServicioSeo();

        $antigua = $this->campana($cliente, $servicio, ['nombre' => 'Campaña Antigua']);
        $antigua->forceFill(['created_at' => now()->subDays(10)])->save();

        $reciente = $this->campana($cliente, $servicio, ['nombre' => 'Campaña Reciente']);
        $reciente->forceFill(['created_at' => now()])->save();

        $response = $this->actingAs($user)->get(route('admin.seo.index'));

        $row = $response->viewData('clientes')->firstWhere('cliente_id', $cliente->id);

        $this->assertSame($reciente->id, $row['campana_id']);
        $this->assertSame('Campaña Reciente', $row['campana_nombre']);
    }

    public function test_mrr_sums_only_seo_servicios_prices(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'seo', 'precio_mensual' => 1000.50]);
        Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'seo', 'precio_mensual' => 2000.25]);
        Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'google_ads', 'precio_mensual' => 5000]);

        $response = $this->actingAs($user)->get(route('admin.seo.index'));

        $row = $response->viewData('clientes')->firstWhere('cliente_id', $cliente->id);

        $this->assertSame(3000.75, $row['mrr']);
    }

    public function test_seo_score_comes_from_current_cycle_fase_auditoria(): void
    {
        $user = User::factory()->create();
        [$cliente, $servicio] = $this->clienteConServicioSeo();
        $campana = $this->campana($cliente, $servicio, ['ciclo_actual' => 1]);
        $campana->auditorias()->create(['ciclo' => 1, 'seo_score' => 77, 'checklist' => []]);

        $response = $this->actingAs($user)->get(route('admin.seo.index'));

        $row = $response->viewData('clientes')->firstWhere('cliente_id', $cliente->id);

        $this->assertSame(77, $row['seo_score']);
    }

    public function test_trafico_actual_comes_from_current_cycle_reporte(): void
    {
        $user = User::factory()->create();
        [$cliente, $servicio] = $this->clienteConServicioSeo();
        $campana = $this->campana($cliente, $servicio, ['ciclo_actual' => 1]);
        $campana->reportes()->create(['ciclo' => 1, 'trafico_actual' => 4321, 'checklist' => []]);

        $response = $this->actingAs($user)->get(route('admin.seo.index'));

        $row = $response->viewData('clientes')->firstWhere('cliente_id', $cliente->id);

        $this->assertSame(4321, $row['trafico_actual']);
    }

    public function test_null_seo_score_is_preserved_as_null_not_zero(): void
    {
        $user = User::factory()->create();
        [$cliente, $servicio] = $this->clienteConServicioSeo();
        $campana = $this->campana($cliente, $servicio, ['ciclo_actual' => 1]);
        $campana->auditorias()->create(['ciclo' => 1, 'seo_score' => null, 'checklist' => []]);

        $response = $this->actingAs($user)->get(route('admin.seo.index'));

        $row = $response->viewData('clientes')->firstWhere('cliente_id', $cliente->id);

        $this->assertNull($row['seo_score']);
        $this->assertNotSame(0, $row['seo_score']);
    }

    public function test_score_promedio_averages_only_non_null_scores(): void
    {
        $user = User::factory()->create();

        [$clienteA, $servicioA] = $this->clienteConServicioSeo();
        $campanaA = $this->campana($clienteA, $servicioA, ['ciclo_actual' => 1]);
        $campanaA->auditorias()->create(['ciclo' => 1, 'seo_score' => 60, 'checklist' => []]);

        [$clienteB, $servicioB] = $this->clienteConServicioSeo();
        $campanaB = $this->campana($clienteB, $servicioB, ['ciclo_actual' => 1]);
        $campanaB->auditorias()->create(['ciclo' => 1, 'seo_score' => 80, 'checklist' => []]);

        // Third client has a campaign but no score yet — must not be treated as 0.
        [$clienteC, $servicioC] = $this->clienteConServicioSeo();
        $campanaC = $this->campana($clienteC, $servicioC, ['ciclo_actual' => 1]);
        $campanaC->auditorias()->create(['ciclo' => 1, 'seo_score' => null, 'checklist' => []]);

        $response = $this->actingAs($user)->get(route('admin.seo.index'));

        $this->assertSame(70.0, $response->viewData('scorePromedio'));
    }

    public function test_score_promedio_is_null_when_no_scores_exist(): void
    {
        $user = User::factory()->create();

        // One client with no campaign at all, one with a campaign but a null score.
        $this->clienteConServicioSeo();
        [$cliente, $servicio] = $this->clienteConServicioSeo();
        $campana = $this->campana($cliente, $servicio, ['ciclo_actual' => 1]);
        $campana->auditorias()->create(['ciclo' => 1, 'seo_score' => null, 'checklist' => []]);

        $response = $this->actingAs($user)->get(route('admin.seo.index'));

        $this->assertNull($response->viewData('scorePromedio'));
    }

    public function test_trafico_total_sums_non_null_values_including_real_zero(): void
    {
        $user = User::factory()->create();

        [$clienteA, $servicioA] = $this->clienteConServicioSeo();
        $campanaA = $this->campana($clienteA, $servicioA, ['ciclo_actual' => 1]);
        $campanaA->reportes()->create(['ciclo' => 1, 'trafico_actual' => 100, 'checklist' => []]);

        // A client that genuinely measured zero traffic — must be included in the sum.
        [$clienteB, $servicioB] = $this->clienteConServicioSeo();
        $campanaB = $this->campana($clienteB, $servicioB, ['ciclo_actual' => 1]);
        $campanaB->reportes()->create(['ciclo' => 1, 'trafico_actual' => 0, 'checklist' => []]);

        // A client with no traffic report yet — must be excluded, not counted as 0.
        [$clienteC, $servicioC] = $this->clienteConServicioSeo();
        $this->campana($clienteC, $servicioC, ['ciclo_actual' => 1]);

        $response = $this->actingAs($user)->get(route('admin.seo.index'));

        $this->assertSame(100, $response->viewData('traficoTotal'));
    }

    // --- store -------------------------------------------------------------

    public function test_store_requires_cliente_id(): void
    {
        $user = User::factory()->create();
        $servicio = Servicio::factory()->create(['tipo' => 'seo']);

        $response = $this->actingAs($user)->postJson(route('admin.seo.store'), [
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña sin cliente',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('cliente_id');
    }

    public function test_store_requires_nombre(): void
    {
        $user = User::factory()->create();
        [$cliente, $servicio] = $this->clienteConServicioSeo();

        $response = $this->actingAs($user)->postJson(route('admin.seo.store'), [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('nombre');
    }

    public function test_store_rejects_nonexistent_cliente_id(): void
    {
        $user = User::factory()->create();
        $servicio = Servicio::factory()->create(['tipo' => 'seo']);

        $response = $this->actingAs($user)->postJson(route('admin.seo.store'), [
            'cliente_id' => 999999,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña con cliente inexistente',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('cliente_id');
    }

    public function test_store_valid_minimal_payload_succeeds(): void
    {
        $user = User::factory()->create();
        [$cliente, $servicio] = $this->clienteConServicioSeo();

        $response = $this->actingAs($user)->postJson(route('admin.seo.store'), [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña Mínima',
        ]);

        $response->assertCreated();
    }

    public function test_store_ignores_auditoria_phase_fields_in_payload(): void
    {
        $user = User::factory()->create();
        [$cliente, $servicio] = $this->clienteConServicioSeo();

        $response = $this->actingAs($user)->postJson(route('admin.seo.store'), [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña con Extras',
            'seo_score' => 90,
            'velocidad_mobile' => 95.5,
            'checklist' => ['algo' => true],
        ]);

        $response->assertCreated();

        $campana = SeoCampana::where('nombre', 'Campaña con Extras')->firstOrFail();

        $this->assertDatabaseHas('seo_fase_auditoria', [
            'seo_campana_id' => $campana->id,
            'ciclo' => 1,
            'seo_score' => null,
        ]);
    }

    public function test_store_response_is_exactly_show_url_shape(): void
    {
        $user = User::factory()->create();
        [$cliente, $servicio] = $this->clienteConServicioSeo();

        $response = $this->actingAs($user)->postJson(route('admin.seo.store'), [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña Nueva',
        ]);

        $response->assertCreated();
        $campana = SeoCampana::where('nombre', 'Campaña Nueva')->firstOrFail();

        $response->assertExactJson(['show_url' => route('admin.seo.show', $campana->id)]);
    }

    public function test_store_creates_campana_with_correct_defaults(): void
    {
        $user = User::factory()->create();
        [$cliente, $servicio] = $this->clienteConServicioSeo();

        $this->actingAs($user)->postJson(route('admin.seo.store'), [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña Defaults',
        ]);

        $campana = SeoCampana::where('nombre', 'Campaña Defaults')->firstOrFail();

        $this->assertSame('activa', $campana->estado->value);
        $this->assertSame('auditoria', $campana->fase_actual->value);
        $this->assertSame(1, $campana->ciclo_actual);
    }

    public function test_store_creates_four_empty_phase_rows_for_ciclo_1(): void
    {
        $user = User::factory()->create();
        [$cliente, $servicio] = $this->clienteConServicioSeo();

        $this->actingAs($user)->postJson(route('admin.seo.store'), [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña Cuatro Fases',
        ]);

        $campana = SeoCampana::where('nombre', 'Campaña Cuatro Fases')->firstOrFail();

        $this->assertDatabaseHas('seo_fase_auditoria', ['seo_campana_id' => $campana->id, 'ciclo' => 1]);
        $this->assertDatabaseHas('seo_fase_estrategia', ['seo_campana_id' => $campana->id, 'ciclo' => 1]);
        $this->assertDatabaseHas('seo_fase_ejecucion', ['seo_campana_id' => $campana->id, 'ciclo' => 1]);
        $this->assertDatabaseHas('seo_reportes', ['seo_campana_id' => $campana->id, 'ciclo' => 1]);

        $this->assertSame(1, $campana->auditorias()->count());
        $this->assertSame(1, $campana->estrategias()->count());
        $this->assertSame(1, $campana->ejecuciones()->count());
        $this->assertSame(1, $campana->reportes()->count());

        $this->assertSame([], $campana->auditorias()->first()->checklist);
    }

    public function test_store_requires_authentication(): void
    {
        [$cliente, $servicio] = $this->clienteConServicioSeo();

        $response = $this->postJson(route('admin.seo.store'), [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña sin autenticar',
        ]);

        $response->assertStatus(401);
    }

    // --- update --------------------------------------------------------------

    public function test_update_requires_estado(): void
    {
        [$cliente, $servicio] = $this->clienteConServicioSeo();
        $campana = $this->campana($cliente, $servicio);

        $response = $this->actingAs(User::factory()->create())->putJson(route('admin.seo.update', $campana), [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => $campana->nombre,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('estado');
    }

    public function test_update_rejects_invalid_estado(): void
    {
        [$cliente, $servicio] = $this->clienteConServicioSeo();
        $campana = $this->campana($cliente, $servicio);

        $response = $this->actingAs(User::factory()->create())->putJson(route('admin.seo.update', $campana), [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => $campana->nombre,
            'estado' => 'cancelada',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('estado');
    }

    public function test_update_valid_payload_succeeds(): void
    {
        [$cliente, $servicio] = $this->clienteConServicioSeo();
        $campana = $this->campana($cliente, $servicio);

        $response = $this->actingAs(User::factory()->create())->putJson(route('admin.seo.update', $campana), [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña Actualizada',
            'estado' => 'pausada',
            'notas' => 'Notas de prueba',
        ]);

        $response->assertOk();
    }

    /**
     * The asymmetry the task calls out explicitly: store() returns only
     * {"show_url": ...} (a redirect target for the newly-created campaign's
     * detail page), while update() returns the FULL client-card row shape
     * (an in-place patch for the still-open picker card) — never just
     * {"show_url": ...}.
     */
    public function test_update_response_is_client_card_shape_not_show_url_only(): void
    {
        [$cliente, $servicio] = $this->clienteConServicioSeo();
        $campana = $this->campana($cliente, $servicio);

        $response = $this->actingAs(User::factory()->create())->putJson(route('admin.seo.update', $campana), [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña Actualizada',
            'estado' => 'pausada',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'cliente_id', 'cliente', 'contacto', 'mrr', 'servicios_seo',
            'campana_id', 'campana_nombre', 'url_sitio', 'servicio_id',
            'estado', 'fase_actual', 'ciclo_actual', 'fecha_inicio', 'notas',
            'seo_score', 'trafico_actual', 'show_url',
        ]);
        $this->assertNotSame(['show_url'], array_keys($response->json()));
        $this->assertSame($cliente->id, $response->json('cliente_id'));
        $this->assertSame($campana->id, $response->json('campana_id'));
    }

    public function test_update_persists_changes_to_database(): void
    {
        [$cliente, $servicio] = $this->clienteConServicioSeo();
        $campana = $this->campana($cliente, $servicio, ['nombre' => 'Nombre Original', 'estado' => 'activa']);

        $this->actingAs(User::factory()->create())->putJson(route('admin.seo.update', $campana), [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Nombre Cambiado',
            'estado' => 'finalizada',
        ]);

        $campana->refresh();
        $this->assertSame('Nombre Cambiado', $campana->nombre);
        $this->assertSame('finalizada', $campana->estado->value);
    }

    public function test_update_requires_authentication(): void
    {
        [$cliente, $servicio] = $this->clienteConServicioSeo();
        $campana = $this->campana($cliente, $servicio);

        $response = $this->putJson(route('admin.seo.update', $campana), [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Intento sin autenticar',
            'estado' => 'activa',
        ]);

        $response->assertStatus(401);
    }

    // --- destroy -----------------------------------------------------------

    public function test_destroy_returns_client_card_with_null_campaign_fields(): void
    {
        [$cliente, $servicio] = $this->clienteConServicioSeo();
        $campana = $this->campana($cliente, $servicio);

        $response = $this->actingAs(User::factory()->create())->deleteJson(route('admin.seo.destroy', $campana));

        $response->assertOk();
        $this->assertSame($cliente->id, $response->json('cliente_id'));
        $this->assertNull($response->json('campana_id'));
        $this->assertNull($response->json('campana_nombre'));
        $this->assertNull($response->json('url_sitio'));
        $this->assertNull($response->json('servicio_id'));
        $this->assertNull($response->json('estado'));
        $this->assertNull($response->json('fase_actual'));
        $this->assertNull($response->json('ciclo_actual'));
        $this->assertNull($response->json('fecha_inicio'));
        $this->assertNull($response->json('notas'));
        $this->assertNull($response->json('seo_score'));
        $this->assertNull($response->json('trafico_actual'));
        $this->assertNull($response->json('show_url'));
    }

    /**
     * SeoCampana uses SoftDeletes, so destroy() leaves the row in place with
     * deleted_at set rather than physically removing it — assertSoftDeleted
     * (not assertDatabaseMissing, which would fail here) is the correct
     * assertion, matching AdsCampanasTest's equivalent test for AdsCampana
     * (which also uses SoftDeletes).
     */
    public function test_destroy_soft_deletes_campana(): void
    {
        [$cliente, $servicio] = $this->clienteConServicioSeo();
        $campana = $this->campana($cliente, $servicio);

        $this->actingAs(User::factory()->create())->deleteJson(route('admin.seo.destroy', $campana));

        $this->assertSoftDeleted('seo_campanas', ['id' => $campana->id]);
    }

    public function test_destroy_cascades_delete_to_posiciones(): void
    {
        [$cliente, $servicio] = $this->clienteConServicioSeo();
        $campana = $this->campana($cliente, $servicio);
        $posicion = SeoPosicion::create([
            'seo_campana_id' => $campana->id,
            'cliente_id' => $cliente->id,
            'keyword' => 'palabra clave de prueba',
            'posicion_actual' => 5,
        ]);

        $this->actingAs(User::factory()->create())->deleteJson(route('admin.seo.destroy', $campana));

        $this->assertDatabaseMissing('seo_posiciones', ['id' => $posicion->id]);
    }

    public function test_destroy_requires_authentication(): void
    {
        [$cliente, $servicio] = $this->clienteConServicioSeo();
        $campana = $this->campana($cliente, $servicio);

        $response = $this->deleteJson(route('admin.seo.destroy', $campana));

        $response->assertStatus(401);
    }

    // --- old routes regression ----------------------------------------------

    public function test_old_create_edit_routes_no_longer_exist(): void
    {
        $this->assertFalse(Route::has('admin.seo.create'));
        $this->assertFalse(Route::has('admin.seo.edit'));
    }

    /**
     * GET /admin/seo/nueva now accidentally matches the GET /{campana} (show)
     * route pattern — 'nueva' is passed as the {campana} route-model-binding
     * key, fails to resolve to any SeoCampana, and 404s via
     * ModelNotFoundException rather than "no route matched at all".
     */
    public function test_get_seo_nueva_404s_via_show_route_model_binding(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/seo/nueva');

        $response->assertStatus(404);
    }

    /**
     * GET /admin/seo/{campana}/editar has no matching route shape at all
     * (every /{campana}/algo route in this group is POST/PUT/DELETE), so it
     * 404s directly from the router with no model binding involved.
     */
    public function test_get_seo_campana_editar_404s_no_matching_route(): void
    {
        $user = User::factory()->create();
        [$cliente, $servicio] = $this->clienteConServicioSeo();
        $campana = $this->campana($cliente, $servicio);

        $response = $this->actingAs($user)->get("/admin/seo/{$campana->id}/editar");

        $response->assertStatus(404);
    }
}
