<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\Finanza;
use App\Models\Servicio;
use App\Models\User;
use App\Support\FinanzasMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FinanzasTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Builds a Finanza row with sensible defaults (no FinanzaFactory exists
     * in database/factories/, so this mirrors the hand-rolled-helper
     * convention AdsCampanasTest uses for its own campanaConFases() helper).
     */
    private function finanza(array $overrides = []): Finanza
    {
        $cliente = $overrides['cliente_id'] ?? null;
        unset($overrides['cliente_id']);

        return Finanza::create(array_merge([
            'cliente_id' => $cliente ?? Cliente::factory()->create()->id,
            'servicio_id' => null,
            'concepto' => 'Servicio SEO — Julio',
            'tipo' => 'ingreso',
            'monto' => 1000,
            'estado' => 'pagado',
            'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => null,
            'fecha_pago' => null,
            'mes' => now()->month,
            'anio' => now()->year,
            'notas' => null,
        ], $overrides));
    }

    // --- index ---------------------------------------------------------

    public function test_index_requires_authentication(): void
    {
        $response = $this->get(route('admin.finanzas.index'));

        $response->assertRedirect('/login');
    }

    public function test_index_returns_ok_with_expected_view_data(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $this->finanza(['cliente_id' => $cliente->id]);

        $response = $this->actingAs($user)->get(route('admin.finanzas.index'));

        $response->assertOk();
        $response->assertViewIs('admin.finanzas.index');
        $response->assertViewHasAll([
            'mrr', 'cobrado', 'pendiente', 'gastos', 'utilidad',
            'facturasPendientes', 'facturasPagadas', 'ingresos6m',
            'ticketPromedio', 'revenueData', 'carteraBuckets',
            'mrrPorCliente', 'facturas', 'clientes',
        ]);
    }

    // --- store ---------------------------------------------------------

    public function test_store_creates_finanza_and_returns_toRow_shape(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id]);

        $response = $this->actingAs($user)->postJson(route('admin.finanzas.store'), [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'concepto' => 'SEO + Google Ads — Julio 2026',
            'tipo' => 'ingreso',
            'monto' => 15000.50,
            'estado' => 'pendiente',
            'fecha_emision' => '2026-07-01',
            'fecha_vencimiento' => '2026-07-15',
            'mes' => 7,
            'anio' => 2026,
            'notas' => 'Nota de prueba',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'id', 'folio', 'cliente_id', 'cliente', 'servicio_id', 'concepto',
            'tipo', 'monto', 'estado', 'fecha_emision', 'fecha_vencimiento',
            'fecha_pago', 'mes', 'anio', 'notas',
        ]);

        $id = $response->json('id');
        $response->assertJson([
            'cliente_id' => $cliente->id,
            'cliente' => $cliente->nombre,
            'servicio_id' => $servicio->id,
            'concepto' => 'SEO + Google Ads — Julio 2026',
            'tipo' => 'ingreso',
            'monto' => 15000.5,
            'estado' => 'pendiente',
            'mes' => 7,
            'anio' => 2026,
            'notas' => 'Nota de prueba',
            'folio' => 'F-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT),
        ]);

        $this->assertDatabaseHas('finanzas', [
            'id' => $id,
            'cliente_id' => $cliente->id,
            'concepto' => 'SEO + Google Ads — Julio 2026',
            'tipo' => 'ingreso',
            'estado' => 'pendiente',
        ]);
    }

    public function test_store_folio_is_padded_to_five_digits(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.finanzas.store'), [
            'cliente_id' => $cliente->id,
            'concepto' => 'Registro folio',
            'tipo' => 'gasto',
            'monto' => 500,
            'estado' => 'pagado',
            'mes' => 1,
            'anio' => 2026,
        ]);

        $response->assertCreated();
        $id = $response->json('id');
        $response->assertJsonPath('folio', 'F-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT));
    }

    public function test_store_requires_cliente_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.finanzas.store'), [
            'concepto' => 'Sin cliente',
            'tipo' => 'ingreso',
            'monto' => 100,
            'estado' => 'pagado',
            'mes' => 1,
            'anio' => 2026,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('cliente_id');
    }

    public function test_store_rejects_nonexistent_cliente_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.finanzas.store'), [
            'cliente_id' => 999999,
            'concepto' => 'Cliente inexistente',
            'tipo' => 'ingreso',
            'monto' => 100,
            'estado' => 'pagado',
            'mes' => 1,
            'anio' => 2026,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('cliente_id');
    }

    public function test_store_requires_concepto(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.finanzas.store'), [
            'cliente_id' => $cliente->id,
            'tipo' => 'ingreso',
            'monto' => 100,
            'estado' => 'pagado',
            'mes' => 1,
            'anio' => 2026,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('concepto');
    }

    public function test_store_requires_tipo(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.finanzas.store'), [
            'cliente_id' => $cliente->id,
            'concepto' => 'Sin tipo',
            'monto' => 100,
            'estado' => 'pagado',
            'mes' => 1,
            'anio' => 2026,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('tipo');
    }

    public function test_store_rejects_invalid_tipo(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.finanzas.store'), [
            'cliente_id' => $cliente->id,
            'concepto' => 'Tipo invalido',
            'tipo' => 'invalid',
            'monto' => 100,
            'estado' => 'pagado',
            'mes' => 1,
            'anio' => 2026,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('tipo');
    }

    public function test_store_requires_monto(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.finanzas.store'), [
            'cliente_id' => $cliente->id,
            'concepto' => 'Sin monto',
            'tipo' => 'ingreso',
            'estado' => 'pagado',
            'mes' => 1,
            'anio' => 2026,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('monto');
    }

    public function test_store_rejects_negative_monto(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.finanzas.store'), [
            'cliente_id' => $cliente->id,
            'concepto' => 'Monto negativo',
            'tipo' => 'ingreso',
            'monto' => -50,
            'estado' => 'pagado',
            'mes' => 1,
            'anio' => 2026,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('monto');
    }

    public function test_store_requires_estado(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.finanzas.store'), [
            'cliente_id' => $cliente->id,
            'concepto' => 'Sin estado',
            'tipo' => 'ingreso',
            'monto' => 100,
            'mes' => 1,
            'anio' => 2026,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('estado');
    }

    public function test_store_rejects_invalid_estado(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.finanzas.store'), [
            'cliente_id' => $cliente->id,
            'concepto' => 'Estado invalido',
            'tipo' => 'ingreso',
            'monto' => 100,
            'estado' => 'cancelado',
            'mes' => 1,
            'anio' => 2026,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('estado');
    }

    public function test_store_rejects_mes_out_of_range(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.finanzas.store'), [
            'cliente_id' => $cliente->id,
            'concepto' => 'Mes invalido',
            'tipo' => 'ingreso',
            'monto' => 100,
            'estado' => 'pagado',
            'mes' => 13,
            'anio' => 2026,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('mes');
    }

    public function test_store_rejects_anio_out_of_range(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.finanzas.store'), [
            'cliente_id' => $cliente->id,
            'concepto' => 'Anio invalido',
            'tipo' => 'ingreso',
            'monto' => 100,
            'estado' => 'pagado',
            'mes' => 1,
            'anio' => 1999,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('anio');
    }

    // --- update ---------------------------------------------------------

    public function test_update_updates_finanza_and_returns_toRow_shape(): void
    {
        $finanza = $this->finanza(['concepto' => 'Original', 'monto' => 500, 'estado' => 'pendiente']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson(route('admin.finanzas.update', $finanza), [
            'cliente_id' => $finanza->cliente_id,
            'concepto' => 'Actualizado',
            'tipo' => 'ingreso',
            'monto' => 750,
            'estado' => 'pagado',
            'fecha_pago' => now()->toDateString(),
            'mes' => $finanza->mes,
            'anio' => $finanza->anio,
        ]);

        $response->assertOk();
        $response->assertJson([
            'id' => $finanza->id,
            'concepto' => 'Actualizado',
            'monto' => 750.0,
            'estado' => 'pagado',
        ]);

        $this->assertDatabaseHas('finanzas', [
            'id' => $finanza->id,
            'concepto' => 'Actualizado',
            'monto' => 750.00,
            'estado' => 'pagado',
        ]);
    }

    public function test_update_allows_reassigning_cliente_id_since_there_is_no_implicit_scoping(): void
    {
        $clienteOriginal = Cliente::factory()->create();
        $otroCliente = Cliente::factory()->create();
        $finanza = $this->finanza(['cliente_id' => $clienteOriginal->id]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson(route('admin.finanzas.update', $finanza), [
            'cliente_id' => $otroCliente->id,
            'concepto' => $finanza->concepto,
            'tipo' => $finanza->tipo,
            'monto' => $finanza->monto,
            'estado' => $finanza->estado->value,
            'mes' => $finanza->mes,
            'anio' => $finanza->anio,
        ]);

        $response->assertOk();
        $response->assertJsonPath('cliente_id', $otroCliente->id);
        $response->assertJsonPath('cliente', $otroCliente->nombre);
        $this->assertDatabaseHas('finanzas', [
            'id' => $finanza->id,
            'cliente_id' => $otroCliente->id,
        ]);
    }

    public function test_update_requires_estado(): void
    {
        $finanza = $this->finanza();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson(route('admin.finanzas.update', $finanza), [
            'cliente_id' => $finanza->cliente_id,
            'concepto' => $finanza->concepto,
            'tipo' => $finanza->tipo,
            'monto' => $finanza->monto,
            'mes' => $finanza->mes,
            'anio' => $finanza->anio,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('estado');
    }

    public function test_update_rejects_invalid_tipo(): void
    {
        $finanza = $this->finanza();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson(route('admin.finanzas.update', $finanza), [
            'cliente_id' => $finanza->cliente_id,
            'concepto' => $finanza->concepto,
            'tipo' => 'not-a-tipo',
            'monto' => $finanza->monto,
            'estado' => $finanza->estado->value,
            'mes' => $finanza->mes,
            'anio' => $finanza->anio,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('tipo');
    }

    public function test_update_requires_authentication(): void
    {
        $finanza = $this->finanza();

        $response = $this->putJson(route('admin.finanzas.update', $finanza), [
            'cliente_id' => $finanza->cliente_id,
            'concepto' => $finanza->concepto,
            'tipo' => $finanza->tipo,
            'monto' => $finanza->monto,
            'estado' => $finanza->estado->value,
            'mes' => $finanza->mes,
            'anio' => $finanza->anio,
        ]);

        $response->assertStatus(401);
    }

    // --- destroy ---------------------------------------------------------

    public function test_destroy_deletes_finanza(): void
    {
        $finanza = $this->finanza();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->deleteJson(route('admin.finanzas.destroy', $finanza));

        $response->assertOk();
        $response->assertJson(['deleted' => true]);
        $this->assertDatabaseMissing('finanzas', ['id' => $finanza->id]);
    }

    public function test_destroy_requires_authentication(): void
    {
        $finanza = $this->finanza();

        $response = $this->deleteJson(route('admin.finanzas.destroy', $finanza));

        $response->assertStatus(401);
    }

    public function test_store_requires_authentication(): void
    {
        $cliente = Cliente::factory()->create();

        $response = $this->postJson(route('admin.finanzas.store'), [
            'cliente_id' => $cliente->id,
            'concepto' => 'Sin autenticar',
            'tipo' => 'ingreso',
            'monto' => 100,
            'estado' => 'pagado',
            'mes' => 1,
            'anio' => 2026,
        ]);

        $response->assertStatus(401);
    }

    // --- exportar ---------------------------------------------------------

    public function test_exportar_streams_csv_with_expected_header_and_rows(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create(['nombre' => 'Cliente CSV']);
        $finanza = $this->finanza([
            'cliente_id' => $cliente->id,
            'concepto' => 'Concepto exportado',
            'tipo' => 'ingreso',
            'monto' => 2500,
            'estado' => 'pagado',
        ]);

        $response = $this->actingAs($user)->get(route('admin.finanzas.exportar'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('.csv', $response->headers->get('content-disposition'));

        // fputcsv quotes any field containing a space (e.g. "Fecha de Pago",
        // "Cliente CSV") — not just fields containing the delimiter/enclosure
        // — so the header/rows come back with those cells wrapped in quotes.
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Folio,Cliente,Concepto,Tipo,Monto,Estado,Vencimiento,"Fecha de Pago"', $csv);
        $this->assertStringContainsString('"Cliente CSV"', $csv);
        $this->assertStringContainsString('"Concepto exportado"', $csv);
        $this->assertStringContainsString('F-' . str_pad((string) $finanza->id, 5, '0', STR_PAD_LEFT), $csv);
    }

    public function test_exportar_filters_by_cliente_id(): void
    {
        $user = User::factory()->create();
        $clienteA = Cliente::factory()->create(['nombre' => 'Cliente A']);
        $clienteB = Cliente::factory()->create(['nombre' => 'Cliente B']);

        $this->finanza(['cliente_id' => $clienteA->id, 'concepto' => 'Concepto de A']);
        $this->finanza(['cliente_id' => $clienteB->id, 'concepto' => 'Concepto de B']);

        $response = $this->actingAs($user)->get(route('admin.finanzas.exportar', ['cliente_id' => $clienteA->id]));

        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Concepto de A', $csv);
        $this->assertStringContainsString('Cliente A', $csv);
        $this->assertStringNotContainsString('Concepto de B', $csv);
        $this->assertStringNotContainsString('Cliente B', $csv);
    }

    // --- old routes removed -----------------------------------------------

    public function test_old_create_and_edit_routes_no_longer_exist(): void
    {
        $this->assertFalse(Route::has('admin.finanzas.create'));
        $this->assertFalse(Route::has('admin.finanzas.edit'));
    }

    /**
     * "/admin/finanzas/nuevo" no longer matches any GET route, but it DOES
     * still match the URI shape of the surviving PUT/DELETE
     * "/admin/finanzas/{finanza}" routes (with "nuevo" bound as {finanza}),
     * so Laravel correctly reports 405 Method Not Allowed rather than 404 —
     * the path exists for other verbs, just not GET. The "route no longer
     * exists" property itself is proven by
     * test_old_create_and_edit_routes_no_longer_exist() above via
     * Route::has().
     */
    public function test_old_nuevo_path_is_no_longer_a_valid_get_route(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/finanzas/nuevo');

        $response->assertStatus(405);
    }

    public function test_old_editar_path_returns_404(): void
    {
        $finanza = $this->finanza();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get("/admin/finanzas/{$finanza->id}/editar");

        $response->assertNotFound();
    }

    // --- regression: Dashboard and Finanzas must agree ---------------------

    /**
     * This is the whole point of extracting App\Support\FinanzasMetrics: before
     * the refactor, FinanzasController computed "the current period" as
     * whichever (mes, anio) pair happened to be newest in the finanzas table,
     * while DashboardController used the real calendar month — so the two
     * pages could show different MRR/pendiente numbers whenever data entry
     * lagged behind the calendar. Both controllers now call the same
     * FinanzasMetrics methods anchored on the real $now, so they can never
     * diverge again.
     *
     * The seeded data deliberately makes the *newest inserted row* (by id)
     * belong to a future month, not the current calendar month — exactly the
     * scenario that broke the old "newest (mes, anio) in the table" logic.
     */
    public function test_dashboard_and_finanzas_report_identical_mrr_and_pendiente_for_current_month(): void
    {
        $now = Carbon::now();
        $user = User::factory()->create();

        $clienteActivo = Cliente::factory()->create();
        Servicio::factory()->create(['cliente_id' => $clienteActivo->id, 'estado' => 'activo', 'precio_mensual' => 12000]);
        Servicio::factory()->create(['cliente_id' => $clienteActivo->id, 'estado' => 'pausado', 'precio_mensual' => 99999]);

        // Row belonging to the real current month — this is what SHOULD count.
        $this->finanza([
            'cliente_id' => $clienteActivo->id,
            'tipo' => 'ingreso',
            'estado' => 'pendiente',
            'monto' => 2000,
            'mes' => $now->month,
            'anio' => $now->year,
        ]);

        // Row inserted AFTER (higher id) but dated a future month — under the
        // old "newest period in the table" logic this row's period would have
        // been picked instead of the real current month.
        $futuro = $now->copy()->addMonth();
        $this->finanza([
            'cliente_id' => $clienteActivo->id,
            'tipo' => 'ingreso',
            'estado' => 'pendiente',
            'monto' => 50000,
            'mes' => $futuro->month,
            'anio' => $futuro->year,
        ]);

        $expectedMrr = FinanzasMetrics::mrr();
        $expectedPeriodo = FinanzasMetrics::periodoActual($now);

        $this->assertSame(12000.0, $expectedMrr);
        $this->assertSame(2000.0, $expectedPeriodo['pendiente']);
        $this->assertSame(1, $expectedPeriodo['facturas_pendientes']);

        $finanzasResponse = $this->actingAs($user)->get(route('admin.finanzas.index'));
        $finanzasResponse->assertOk();
        $this->assertSame($expectedMrr, $finanzasResponse->viewData('mrr'));
        $this->assertSame($expectedPeriodo['pendiente'], $finanzasResponse->viewData('pendiente'));
        $this->assertSame($expectedPeriodo['facturas_pendientes'], $finanzasResponse->viewData('facturasPendientes'));

        $dashboardResponse = $this->actingAs($user)->get(route('admin.dashboard'));
        $dashboardResponse->assertOk();
        $kpis = collect($dashboardResponse->viewData('kpis'))->keyBy('label');

        $expectedMrrLabel = '$' . number_format($expectedMrr / 1000, 0) . 'K';
        $expectedPendienteLabel = '$' . number_format($expectedPeriodo['pendiente'] / 1000, 0) . 'K';

        $this->assertSame($expectedMrrLabel, $kpis['MRR Activo']['value']);
        $this->assertSame($expectedPendienteLabel, $kpis['Pagos Pendientes']['value']);
        $this->assertSame($expectedPeriodo['facturas_pendientes'] . ' facturas pendientes', $kpis['Pagos Pendientes']['sub']);
    }
}
