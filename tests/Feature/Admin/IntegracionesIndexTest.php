<?php

namespace Tests\Feature\Admin;

use App\Models\AdsClic;
use App\Models\AdsConversion;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests for IntegracionesController::index() — the top-level
 * /admin/integraciones page. Only index() (and its private toClienteRow()
 * helper) was rewritten: it used to render nothing but the 8 decorative
 * "próximamente" third-party SaaS cards; it now ALSO computes real
 * per-client tracking-integration state (a client picker) mirroring the
 * same query shape clienteIndex() already used, alongside the unchanged
 * fake SaaS list. clienteIndex()/regenerarToken()/clics()/conversiones()/
 * conversionesEmbudo()/exportarCsv()/exportarExcel()/asignarCampana()/
 * asignarEtapa() are pre-existing and out of scope here.
 *
 * Time is frozen for the whole file so diffForHumans() labels computed by
 * the controller at request time are byte-for-byte comparable against
 * labels computed in the test from explicitly-seeded created_at values —
 * comparing "most recent signal" logic via live wall-clock timestamps
 * would be flaky near rounding boundaries.
 */
class IntegracionesIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-01 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * No AdsClicFactory exists in database/factories/, so this hand-rolls
     * one the way ArchivosTest's archivoConArchivoReal() and FinanzasTest's
     * finanza() do for models without a factory. created_at is set via
     * forceFill()->save() after create() because 'created_at' is not in
     * AdsClic::$fillable (mass-assigning it would silently be dropped).
     */
    private function adsClic(Cliente $cliente, array $overrides = []): AdsClic
    {
        $createdAt = $overrides['created_at'] ?? now();
        unset($overrides['created_at']);

        $clic = AdsClic::create(array_merge([
            'cliente_id' => $cliente->id,
            'visitor_id' => Str::random(20),
            'landing_url' => 'https://cliente-example.com/landing',
        ], $overrides));

        $clic->forceFill(['created_at' => $createdAt])->save();

        return $clic;
    }

    /**
     * Same rationale as adsClic() above — no AdsConversionFactory, and
     * 'created_at' is not in AdsConversion::$fillable either.
     */
    private function adsConversion(Cliente $cliente, array $overrides = []): AdsConversion
    {
        $createdAt = $overrides['created_at'] ?? now();
        unset($overrides['created_at']);

        $conversion = AdsConversion::create(array_merge([
            'cliente_id' => $cliente->id,
            'visitor_id' => Str::random(20),
            'tipo' => 'formulario',
            'estado' => 'pendiente',
        ], $overrides));

        $conversion->forceFill(['created_at' => $createdAt])->save();

        return $conversion;
    }

    // --- auth / basic response -------------------------------------------

    public function test_index_requires_authentication(): void
    {
        $response = $this->get(route('admin.integraciones.index'));

        $response->assertRedirect('/login');
    }

    public function test_index_returns_ok_and_correct_view(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.integraciones.index'));

        $response->assertOk();
        $response->assertViewIs('admin.integraciones.index');
    }

    // --- decorative fake SaaS list (unchanged by the rewrite) ------------

    public function test_fake_saas_integraciones_list_still_has_eight_entries(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.integraciones.index'));

        $this->assertCount(8, $response->viewData('integraciones'));
    }

    public function test_fake_saas_integraciones_entries_keep_their_known_shape(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.integraciones.index'));

        $integraciones = collect($response->viewData('integraciones'));

        $googleAds = $integraciones->firstWhere('name', 'Google Ads API');
        $this->assertNotNull($googleAds);
        $this->assertSame('Sincronización automática de campañas', $googleAds['desc']);
        $this->assertSame('fa-google', $googleAds['icon']);
        $this->assertTrue($googleAds['brand']);

        $slack = $integraciones->firstWhere('name', 'Slack');
        $this->assertNotNull($slack);
        $this->assertSame('Notificaciones y alertas en tiempo real', $slack['desc']);
        $this->assertSame('fa-slack', $slack['icon']);
        $this->assertTrue($slack['brand']);
    }

    // --- real per-client "clientes" rows -----------------------------------

    public function test_clientes_view_data_has_one_row_per_real_cliente(): void
    {
        $user = User::factory()->create();
        Cliente::factory()->count(3)->create();

        $response = $this->actingAs($user)->get(route('admin.integraciones.index'));

        $this->assertSame(Cliente::count(), $response->viewData('clientes')->count());
        $this->assertSame(3, $response->viewData('clientes')->count());
    }

    public function test_cliente_row_has_expected_shape(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.integraciones.index'));

        $row = $response->viewData('clientes')->firstWhere('cliente_id', $cliente->id);

        $this->assertSame([
            'cliente_id', 'cliente', 'conectado', 'pendientes_count',
            'ultima_senal', 'ultima_senal_label', 'show_url',
        ], array_keys($row));
        $this->assertSame($cliente->nombre, $row['cliente']);
    }

    public function test_show_url_matches_route_for_each_cliente(): void
    {
        $user = User::factory()->create();
        $clienteA = Cliente::factory()->create();
        $clienteB = Cliente::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.integraciones.index'));

        $rowA = $response->viewData('clientes')->firstWhere('cliente_id', $clienteA->id);
        $rowB = $response->viewData('clientes')->firstWhere('cliente_id', $clienteB->id);

        $this->assertSame(route('admin.clientes.integraciones', $clienteA->id), $rowA['show_url']);
        $this->assertSame(route('admin.clientes.integraciones', $clienteB->id), $rowB['show_url']);
    }

    public function test_conectado_is_true_only_for_clients_with_a_real_api_token_and_does_not_leak(): void
    {
        $user = User::factory()->create();

        $conConTouken = Cliente::factory()->create();
        $conConTouken->forceFill(['api_token' => 'rp_live_'.Str::random(56)])->save();

        $sinToken = Cliente::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.integraciones.index'));

        $rowConectado = $response->viewData('clientes')->firstWhere('cliente_id', $conConTouken->id);
        $rowSinConectar = $response->viewData('clientes')->firstWhere('cliente_id', $sinToken->id);

        $this->assertTrue($rowConectado['conectado']);
        $this->assertFalse($rowSinConectar['conectado']);
    }

    public function test_pendientes_count_only_counts_that_clients_pendiente_conversiones_and_does_not_leak(): void
    {
        $user = User::factory()->create();
        $clienteA = Cliente::factory()->create();
        $clienteB = Cliente::factory()->create();

        // Cliente A: 2 pendiente + 1 exportada (exportada must NOT be counted).
        $this->adsConversion($clienteA, ['estado' => 'pendiente']);
        $this->adsConversion($clienteA, ['estado' => 'pendiente']);
        $this->adsConversion($clienteA, ['estado' => 'exportada']);

        // Cliente B: 3 pendiente — must not leak into cliente A's count, and
        // cliente A's rows must not leak into cliente B's count.
        $this->adsConversion($clienteB, ['estado' => 'pendiente']);
        $this->adsConversion($clienteB, ['estado' => 'pendiente']);
        $this->adsConversion($clienteB, ['estado' => 'pendiente']);

        $response = $this->actingAs($user)->get(route('admin.integraciones.index'));

        $rowA = $response->viewData('clientes')->firstWhere('cliente_id', $clienteA->id);
        $rowB = $response->viewData('clientes')->firstWhere('cliente_id', $clienteB->id);

        $this->assertSame(2, $rowA['pendientes_count']);
        $this->assertSame(3, $rowB['pendientes_count']);
    }

    public function test_ultima_senal_label_uses_clic_when_it_is_more_recent_than_the_latest_conversion(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $clicReciente = now()->subHour();
        $conversionAntigua = now()->subDays(3);

        $this->adsClic($cliente, ['created_at' => $clicReciente]);
        $this->adsConversion($cliente, ['created_at' => $conversionAntigua]);

        $response = $this->actingAs($user)->get(route('admin.integraciones.index'));

        $row = $response->viewData('clientes')->firstWhere('cliente_id', $cliente->id);

        $this->assertSame($clicReciente->diffForHumans(), $row['ultima_senal_label']);
    }

    public function test_ultima_senal_label_uses_conversion_when_it_is_more_recent_than_the_latest_clic(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $clicAntiguo = now()->subDays(3);
        $conversionReciente = now()->subHour();

        $this->adsClic($cliente, ['created_at' => $clicAntiguo]);
        $this->adsConversion($cliente, ['created_at' => $conversionReciente]);

        $response = $this->actingAs($user)->get(route('admin.integraciones.index'));

        $row = $response->viewData('clientes')->firstWhere('cliente_id', $cliente->id);

        $this->assertSame($conversionReciente->diffForHumans(), $row['ultima_senal_label']);
    }

    public function test_ultima_senal_label_fallback_when_cliente_has_no_clics_or_conversiones(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.integraciones.index'));

        $row = $response->viewData('clientes')->firstWhere('cliente_id', $cliente->id);

        $this->assertNull($row['ultima_senal']);
        $this->assertSame('Sin señal registrada', $row['ultima_senal_label']);
    }

    // --- top-level aggregates ---------------------------------------------

    public function test_clientes_conectados_counts_only_clients_with_a_real_token(): void
    {
        $user = User::factory()->create();

        $conToken1 = Cliente::factory()->create();
        $conToken1->forceFill(['api_token' => 'rp_live_'.Str::random(56)])->save();

        $conToken2 = Cliente::factory()->create();
        $conToken2->forceFill(['api_token' => 'rp_live_'.Str::random(56)])->save();

        Cliente::factory()->create(); // sin token
        Cliente::factory()->create(); // sin token

        $response = $this->actingAs($user)->get(route('admin.integraciones.index'));

        $this->assertSame(2, $response->viewData('clientesConectados'));
    }

    public function test_clientes_total_equals_real_cliente_count(): void
    {
        $user = User::factory()->create();
        Cliente::factory()->count(4)->create();

        $response = $this->actingAs($user)->get(route('admin.integraciones.index'));

        $this->assertSame(Cliente::count(), $response->viewData('clientesTotal'));
        $this->assertSame(4, $response->viewData('clientesTotal'));
    }

    public function test_pendientes_total_sums_pending_conversiones_across_all_clientes(): void
    {
        $user = User::factory()->create();
        $clienteA = Cliente::factory()->create();
        $clienteB = Cliente::factory()->create();

        $this->adsConversion($clienteA, ['estado' => 'pendiente']);
        $this->adsConversion($clienteA, ['estado' => 'pendiente']);
        $this->adsConversion($clienteA, ['estado' => 'exportada']);

        $this->adsConversion($clienteB, ['estado' => 'pendiente']);
        $this->adsConversion($clienteB, ['estado' => 'pendiente']);
        $this->adsConversion($clienteB, ['estado' => 'pendiente']);

        $response = $this->actingAs($user)->get(route('admin.integraciones.index'));

        // 2 (cliente A) + 3 (cliente B) = 5, not e.g. just the last client's count.
        $this->assertSame(5, $response->viewData('pendientesTotal'));
    }

    public function test_ultima_senal_global_label_reflects_the_most_recent_signal_across_all_clientes(): void
    {
        $user = User::factory()->create();
        $clienteA = Cliente::factory()->create();
        $clienteB = Cliente::factory()->create();
        $clienteC = Cliente::factory()->create();

        $this->adsClic($clienteA, ['created_at' => now()->subDays(5)]);
        $this->adsConversion($clienteB, ['created_at' => now()->subDays(2)]);

        // The genuinely most recent signal globally — belongs to cliente C,
        // not the client whose row happens to be computed/iterated last.
        $masReciente = now()->subMinutes(10);
        $this->adsClic($clienteC, ['created_at' => $masReciente]);

        $response = $this->actingAs($user)->get(route('admin.integraciones.index'));

        $this->assertSame($masReciente->diffForHumans(), $response->viewData('ultimaSenalGlobalLabel'));
    }

    public function test_ultima_senal_global_label_fallback_when_no_clients_have_any_signal(): void
    {
        $user = User::factory()->create();
        Cliente::factory()->count(2)->create();

        $response = $this->actingAs($user)->get(route('admin.integraciones.index'));

        $this->assertSame('Sin señal registrada todavía', $response->viewData('ultimaSenalGlobalLabel'));
    }
}
