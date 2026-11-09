<?php

namespace Tests\Feature\Admin;

use App\Models\AdsBriefing;
use App\Models\AdsConfiguracion;
use App\Models\AdsCampana;
use App\Models\AdsCreativo;
use App\Models\AdsGrupo;
use App\Models\AdsLanzamiento;
use App\Models\AdsMetrica;
use App\Models\AdsReporte;
use App\Models\Cliente;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdsCampanasTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Builds a campaign plus its 4 phase rows directly via Eloquent, mirroring
     * AdsController::store()'s creation logic exactly. Built manually (not
     * via HTTP) so it doesn't disturb the authenticated user of the calling test.
     */
    private function campanaConFases(?Cliente $cliente = null, ?Servicio $servicio = null, array $overrides = []): AdsCampana
    {
        $cliente ??= Cliente::factory()->create();
        $servicio ??= Servicio::factory()->create(['cliente_id' => $cliente->id]);

        $campana = AdsCampana::create(array_merge([
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña de Prueba',
            'plataforma' => 'google_ads',
            'objetivo' => 'ventas',
            'presupuesto_mensual' => 5000,
            'estado' => 'activa',
            'fase_actual' => 'briefing',
            'ciclo_actual' => 1,
        ], $overrides));

        $campana->briefings()->create([
            'ciclo' => 1,
            'checklist' => collect(array_keys(AdsBriefing::CHECKLIST))->mapWithKeys(fn ($key) => [$key => false])->all(),
        ]);
        $campana->configuraciones()->create(['ciclo' => 1, 'checklist' => []]);
        $campana->lanzamientos()->create(['ciclo' => 1, 'checklist' => []]);
        $campana->reportes()->create(['ciclo' => 1, 'checklist' => []]);

        return $campana->fresh();
    }

    // --- store ---------------------------------------------------------

    public function test_store_creates_campana_with_briefing_configuracion_lanzamiento_reporte_rows(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id]);

        $response = $this->actingAs($user)->postJson(route('admin.ads.store'), [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña Google Ads',
            'plataforma' => 'google_ads',
            'objetivo' => 'ventas',
            'presupuesto_mensual' => 8000,
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'id', 'cliente_id', 'cliente', 'cliente_contacto', 'servicio_id',
            'nombre', 'plataforma', 'objetivo', 'estado', 'fase_actual',
            'ciclo_actual', 'presupuesto_mensual', 'inversion', 'impresiones',
            'clics', 'conversiones', 'roas', 'ingreso_atribuido', 'show_url',
        ]);

        $campana = AdsCampana::findOrFail($response->json('id'));
        $this->assertSame('activa', $campana->estado->value);
        $this->assertSame('briefing', $campana->fase_actual->value);
        $this->assertSame(1, $campana->ciclo_actual);

        $this->assertSame(1, AdsBriefing::where('ads_campana_id', $campana->id)->count());
        $briefing = AdsBriefing::where('ads_campana_id', $campana->id)->first();
        $this->assertSame(1, $briefing->ciclo);
        foreach (array_keys(AdsBriefing::CHECKLIST) as $key) {
            $this->assertArrayHasKey($key, $briefing->checklist);
            $this->assertFalse($briefing->checklist[$key]);
        }

        $this->assertSame(1, AdsConfiguracion::where('ads_campana_id', $campana->id)->count());
        $this->assertSame(1, AdsConfiguracion::where('ads_campana_id', $campana->id)->first()->ciclo);

        $this->assertSame(1, AdsLanzamiento::where('ads_campana_id', $campana->id)->count());
        $this->assertSame(1, AdsLanzamiento::where('ads_campana_id', $campana->id)->first()->ciclo);

        $this->assertSame(1, AdsReporte::where('ads_campana_id', $campana->id)->count());
        $this->assertSame(1, AdsReporte::where('ads_campana_id', $campana->id)->first()->ciclo);
    }

    public function test_store_requires_cliente_id(): void
    {
        $user = User::factory()->create();
        $servicio = Servicio::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.ads.store'), [
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña sin cliente',
            'plataforma' => 'google_ads',
            'objetivo' => 'ventas',
            'presupuesto_mensual' => 1000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('cliente_id');
    }

    public function test_store_requires_servicio_id(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.ads.store'), [
            'cliente_id' => $cliente->id,
            'nombre' => 'Campaña sin servicio',
            'plataforma' => 'google_ads',
            'objetivo' => 'ventas',
            'presupuesto_mensual' => 1000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('servicio_id');
    }

    public function test_store_requires_nombre(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id]);

        $response = $this->actingAs($user)->postJson(route('admin.ads.store'), [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'plataforma' => 'google_ads',
            'objetivo' => 'ventas',
            'presupuesto_mensual' => 1000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('nombre');
    }

    public function test_store_requires_plataforma(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id]);

        $response = $this->actingAs($user)->postJson(route('admin.ads.store'), [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña sin plataforma',
            'objetivo' => 'ventas',
            'presupuesto_mensual' => 1000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('plataforma');
    }

    public function test_store_rejects_invalid_plataforma(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id]);

        $response = $this->actingAs($user)->postJson(route('admin.ads.store'), [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña plataforma invalida',
            'plataforma' => 'twitter_ads',
            'objetivo' => 'ventas',
            'presupuesto_mensual' => 1000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('plataforma');
    }

    public function test_store_requires_objetivo(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id]);

        $response = $this->actingAs($user)->postJson(route('admin.ads.store'), [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña sin objetivo',
            'plataforma' => 'google_ads',
            'presupuesto_mensual' => 1000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('objetivo');
    }

    public function test_store_rejects_invalid_objetivo(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id]);

        $response = $this->actingAs($user)->postJson(route('admin.ads.store'), [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña objetivo invalido',
            'plataforma' => 'google_ads',
            'objetivo' => 'notoriedad',
            'presupuesto_mensual' => 1000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('objetivo');
    }

    public function test_store_requires_presupuesto_mensual(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id]);

        $response = $this->actingAs($user)->postJson(route('admin.ads.store'), [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña sin presupuesto',
            'plataforma' => 'google_ads',
            'objetivo' => 'ventas',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('presupuesto_mensual');
    }

    public function test_store_accepts_valid_fecha_inicio(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id]);

        $response = $this->actingAs($user)->postJson(route('admin.ads.store'), [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña con fecha inicio',
            'plataforma' => 'meta_ads',
            'objetivo' => 'leads',
            'presupuesto_mensual' => 2500,
            'fecha_inicio' => '2026-09-01',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('ads_campanas', [
            'id' => $response->json('id'),
            'fecha_inicio' => '2026-09-01',
        ]);
    }

    public function test_store_omitting_fecha_inicio_is_valid(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id]);

        $response = $this->actingAs($user)->postJson(route('admin.ads.store'), [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña sin fecha inicio',
            'plataforma' => 'tiktok_ads',
            'objetivo' => 'trafico',
            'presupuesto_mensual' => 3000,
        ]);

        $response->assertCreated();
    }

    // --- update ---------------------------------------------------------

    public function test_update_changes_general_fields_without_touching_phase_rows(): void
    {
        $campana = $this->campanaConFases();

        $briefingAntes = AdsBriefing::where('ads_campana_id', $campana->id)->first();
        $checklistAntes = $briefingAntes->checklist;

        $response = $this->actingAs(User::factory()->create())->putJson(route('admin.ads.update', $campana), [
            'cliente_id' => $campana->cliente_id,
            'servicio_id' => $campana->servicio_id,
            'nombre' => 'Nombre actualizado',
            'plataforma' => $campana->plataforma,
            'objetivo' => $campana->objetivo,
            'presupuesto_mensual' => 9999,
            'estado' => 'pausada',
        ]);

        $response->assertOk();
        $response->assertJson([
            'nombre' => 'Nombre actualizado',
            'presupuesto_mensual' => 9999.0,
            'estado' => 'pausada',
        ]);

        $this->assertSame(1, AdsBriefing::where('ads_campana_id', $campana->id)->count());
        $this->assertSame(1, AdsConfiguracion::where('ads_campana_id', $campana->id)->count());
        $this->assertSame(1, AdsLanzamiento::where('ads_campana_id', $campana->id)->count());
        $this->assertSame(1, AdsReporte::where('ads_campana_id', $campana->id)->count());

        $briefingDespues = AdsBriefing::where('ads_campana_id', $campana->id)->first();
        $this->assertSame($checklistAntes, $briefingDespues->checklist);
        $this->assertSame($briefingAntes->ciclo, $briefingDespues->ciclo);
    }

    public function test_update_requires_estado(): void
    {
        $campana = $this->campanaConFases();

        $response = $this->actingAs(User::factory()->create())->putJson(route('admin.ads.update', $campana), [
            'cliente_id' => $campana->cliente_id,
            'servicio_id' => $campana->servicio_id,
            'nombre' => $campana->nombre,
            'plataforma' => $campana->plataforma,
            'objetivo' => $campana->objetivo,
            'presupuesto_mensual' => $campana->presupuesto_mensual,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('estado');
    }

    public function test_update_rejects_invalid_estado(): void
    {
        $campana = $this->campanaConFases();

        $response = $this->actingAs(User::factory()->create())->putJson(route('admin.ads.update', $campana), [
            'cliente_id' => $campana->cliente_id,
            'servicio_id' => $campana->servicio_id,
            'nombre' => $campana->nombre,
            'plataforma' => $campana->plataforma,
            'objetivo' => $campana->objetivo,
            'presupuesto_mensual' => $campana->presupuesto_mensual,
            'estado' => 'cancelada',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('estado');
    }

    public function test_update_rejects_fecha_fin_before_fecha_inicio(): void
    {
        $campana = $this->campanaConFases();

        $response = $this->actingAs(User::factory()->create())->putJson(route('admin.ads.update', $campana), [
            'cliente_id' => $campana->cliente_id,
            'servicio_id' => $campana->servicio_id,
            'nombre' => $campana->nombre,
            'plataforma' => $campana->plataforma,
            'objetivo' => $campana->objetivo,
            'presupuesto_mensual' => $campana->presupuesto_mensual,
            'estado' => 'activa',
            'fecha_inicio' => '2026-09-15',
            'fecha_fin' => '2026-09-01',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('fecha_fin');
    }

    // --- destroy ---------------------------------------------------------

    public function test_destroy_soft_deletes_campana_and_hard_deletes_children(): void
    {
        $campana = $this->campanaConFases();

        $metrica = AdsMetrica::create([
            'ads_campana_id' => $campana->id,
            'cliente_id' => $campana->cliente_id,
            'mes' => 8,
            'anio' => 2026,
            'inversion_real' => 100,
        ]);

        $creativo = AdsCreativo::create([
            'ads_campana_id' => $campana->id,
            'titulo' => 'Creativo de prueba',
            'tipo' => 'imagen',
            'estado' => 'activo',
        ]);

        $grupo = AdsGrupo::create([
            'ads_campana_id' => $campana->id,
            'nombre' => 'Grupo de prueba',
            'presupuesto' => 500,
            'estado' => 'activo',
        ]);

        $response = $this->actingAs(User::factory()->create())->deleteJson(route('admin.ads.destroy', $campana));

        $response->assertOk();
        $response->assertJson(['deleted' => true]);

        $this->assertSoftDeleted('ads_campanas', ['id' => $campana->id]);

        $this->assertDatabaseMissing('ads_metricas', ['id' => $metrica->id]);
        $this->assertDatabaseMissing('ads_creativos', ['id' => $creativo->id]);
        $this->assertDatabaseMissing('ads_grupos', ['id' => $grupo->id]);
        $this->assertDatabaseMissing('ads_briefing', ['ads_campana_id' => $campana->id]);
        $this->assertDatabaseMissing('ads_configuracion', ['ads_campana_id' => $campana->id]);
        $this->assertDatabaseMissing('ads_lanzamiento', ['ads_campana_id' => $campana->id]);
        $this->assertDatabaseMissing('ads_reporte', ['ads_campana_id' => $campana->id]);
    }

    // --- routes regression -----------------------------------------------

    public function test_old_create_edit_routes_no_longer_exist(): void
    {
        $this->assertFalse(Route::has('admin.ads.create'));
        $this->assertFalse(Route::has('admin.ads.edit'));
    }

    public function test_show_route_still_works(): void
    {
        $campana = $this->campanaConFases();

        $response = $this->actingAs(User::factory()->create())->get(route('admin.ads.show', $campana));

        $response->assertOk();
    }

    // --- auth ---------------------------------------------------------

    public function test_store_requires_authentication(): void
    {
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id]);

        $response = $this->postJson(route('admin.ads.store'), [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña sin autenticar',
            'plataforma' => 'google_ads',
            'objetivo' => 'ventas',
            'presupuesto_mensual' => 1000,
        ]);

        $response->assertStatus(401);
    }

    public function test_update_requires_authentication(): void
    {
        $campana = $this->campanaConFases();

        $response = $this->putJson(route('admin.ads.update', $campana), [
            'cliente_id' => $campana->cliente_id,
            'servicio_id' => $campana->servicio_id,
            'nombre' => 'Intento sin autenticar',
            'plataforma' => $campana->plataforma,
            'objetivo' => $campana->objetivo,
            'presupuesto_mensual' => $campana->presupuesto_mensual,
            'estado' => 'activa',
        ]);

        $response->assertStatus(401);
    }

    public function test_destroy_requires_authentication(): void
    {
        $campana = $this->campanaConFases();

        $response = $this->deleteJson(route('admin.ads.destroy', $campana));

        $response->assertStatus(401);
    }
    public function test_index_incluye_columnas_ctr_cpc_cpa_con_division_segura_y_datos_de_la_cuenta(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create(['contacto_nombre' => 'Marta Pérez']);
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'estado' => 'activo', 'precio_mensual' => 4200]);
        // Un pago único y un gratuito no son MRR: no deben sumarse a la cuenta.
        Servicio::factory()->unico()->create(['cliente_id' => $cliente->id, 'estado' => 'activo', 'precio_mensual' => 30000]);
        Servicio::factory()->gratuito()->create(['cliente_id' => $cliente->id, 'estado' => 'activo']);
        $campana = $this->campanaConFases($cliente, $servicio, ['nombre' => 'Con metricas']);
        $this->campanaConFases($cliente, $servicio, ['nombre' => 'Sin metricas']);

        AdsMetrica::create([
            'ads_campana_id' => $campana->id,
            'cliente_id' => $cliente->id,
            'mes' => now()->month,
            'anio' => now()->year,
            'inversion_real' => 1000,
            'impresiones' => 10000,
            'clics' => 500,
            'conversiones' => 20,
            'roas' => 3,
        ]);

        $response = $this->actingAs($user)->get(route('admin.ads.index'));

        $response->assertOk();
        $response->assertSeeInOrder(['Presup.', 'Invertido', 'CTR', 'CPC', 'CPA', 'ROAS']);
        // 500/10000 = 5.00 %, 1000/500 = $2.00 por clic, 1000/20 = $50.00 por conversión.
        $response->assertSee('5.00%', false);
        $response->assertSee('$2.00', false);
        $response->assertSee('$50.00', false);
        // La campaña sin métricas no divide entre cero: muestra guion.
        $response->assertSee('—', false);

        $cuentas = $response->viewData('cuentasInfo');
        $this->assertSame(4200.0, $cuentas[$cliente->id]['mrr']);
        $this->assertSame('Marta Pérez', $cuentas[$cliente->id]['contacto']);
        $this->assertSame(['Google Ads'], $cuentas[$cliente->id]['servicios']);

        $filas = $response->viewData('campanas')->keyBy('nombre');
        $this->assertNull($filas['Sin metricas']['ctr']);
        $this->assertNull($filas['Sin metricas']['cpc']);
        $this->assertNull($filas['Sin metricas']['cpa']);
        $this->assertNull($filas['Sin metricas']['inversion_mes']);
        $this->assertSame(5.0, $filas['Con metricas']['ctr']);
        $this->assertSame(1000.0, $filas['Con metricas']['inversion_mes']);
    }

    // --- campos de plataforma y moneda ---------------------------------------

    private function payloadCampana(Cliente $cliente, Servicio $servicio, array $extra = []): array
    {
        return $extra + [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña con plataforma',
            'plataforma' => 'google_ads',
            'objetivo' => 'leads',
            'presupuesto_mensual' => 3000,
        ];
    }

    public function test_store_guarda_y_devuelve_ids_de_plataforma_y_moneda(): void
    {
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.ads.store'), $this->payloadCampana($cliente, $servicio, [
                'plataforma_cuenta_id' => '123-456-7890',
                'plataforma_campana_id' => '987654321',
                'moneda' => 'USD',
            ]))
            ->assertCreated()
            ->assertJsonPath('plataforma_cuenta_id', '123-456-7890')
            ->assertJsonPath('plataforma_campana_id', '987654321')
            ->assertJsonPath('moneda', 'USD');

        $campana = AdsCampana::latest('id')->firstOrFail();
        $this->assertSame('123-456-7890', $campana->plataforma_cuenta_id);
        $this->assertSame('987654321', $campana->plataforma_campana_id);
        $this->assertSame('USD', $campana->moneda);
    }

    public function test_store_sin_moneda_ni_ids_usa_mxn_y_null(): void
    {
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.ads.store'), $this->payloadCampana($cliente, $servicio))
            ->assertCreated()
            ->assertJsonPath('moneda', 'MXN')
            ->assertJsonPath('plataforma_cuenta_id', null);

        $this->assertSame('MXN', AdsCampana::latest('id')->firstOrFail()->moneda);
    }

    public function test_moneda_invalida_o_ids_con_simbolos_son_422(): void
    {
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id]);
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->postJson(route('admin.ads.store'), $this->payloadCampana($cliente, $servicio, ['moneda' => 'EUR']))
            ->assertStatus(422)->assertJsonValidationErrors('moneda');
        $this->actingAs($usuario)->postJson(route('admin.ads.store'), $this->payloadCampana($cliente, $servicio, ['plataforma_cuenta_id' => '123 456; DROP']))
            ->assertStatus(422)->assertJsonValidationErrors('plataforma_cuenta_id');
        $this->actingAs($usuario)->postJson(route('admin.ads.store'), $this->payloadCampana($cliente, $servicio, ['plataforma_campana_id' => str_repeat('1', 41)]))
            ->assertStatus(422)->assertJsonValidationErrors('plataforma_campana_id');

        $this->assertSame(0, AdsCampana::count());
    }

    public function test_update_cambia_ids_y_moneda_y_una_moneda_vacia_no_la_borra(): void
    {
        $campana = $this->campanaConFases(null, null, ['moneda' => 'USD']);
        $datos = [
            'cliente_id' => $campana->cliente_id,
            'servicio_id' => $campana->servicio_id,
            'nombre' => $campana->nombre,
            'plataforma' => 'google_ads',
            'objetivo' => 'ventas',
            'presupuesto_mensual' => 5000,
            'estado' => 'activa',
            'plataforma_cuenta_id' => '555-111-2222',
            'plataforma_campana_id' => 'C-42',
        ];

        $this->actingAs(User::factory()->create())
            ->putJson(route('admin.ads.update', $campana), array_merge($datos, ['moneda' => '']))
            ->assertOk()
            ->assertJsonPath('plataforma_cuenta_id', '555-111-2222')
            ->assertJsonPath('moneda', 'USD');

        $campana->refresh();
        $this->assertSame('C-42', $campana->plataforma_campana_id);
        $this->assertSame('USD', $campana->moneda);

        $this->actingAs(User::factory()->create())
            ->putJson(route('admin.ads.update', $campana), array_merge($datos, ['moneda' => 'MXN', 'plataforma_cuenta_id' => '']))
            ->assertOk();
        $this->assertSame('MXN', $campana->fresh()->moneda);
        $this->assertNull($campana->fresh()->plataforma_cuenta_id);
    }

    public function test_el_default_de_la_columna_moneda_es_mxn(): void
    {
        $this->assertSame('MXN', $this->campanaConFases()->moneda);
    }

    public function test_la_ficha_y_la_lista_muestran_plataforma_y_moneda(): void
    {
        $campana = $this->campanaConFases(null, null, [
            'plataforma_cuenta_id' => '123-456-7890', 'plataforma_campana_id' => 'CAMP-77', 'moneda' => 'USD',
        ]);
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get(route('admin.ads.show', $campana))
            ->assertOk()->assertSee('123-456-7890')->assertSee('CAMP-77')->assertSee('5,000 USD');
        $this->actingAs($usuario)->get(route('admin.ads.index'))
            ->assertOk()->assertSee('name="plataforma_cuenta_id"', false)->assertSee('name="moneda"', false);
    }
}
