<?php

namespace Tests\Feature\Admin;

use App\Enums\EstadoPropuesta;
use App\Enums\TipoArchivo;
use App\Models\Archivo;
use App\Models\Cliente;
use App\Models\Keyword;
use App\Models\Propuesta;
use App\Models\SeoCampana;
use App\Models\Servicio;
use App\Models\User;
use App\Support\Propuestas\Plantilla;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests for the new Propuestas de Continuidad SEO module
 * (PropuestaController + Propuesta + Plantilla::vacio()).
 *
 * Convention matches SeoCampanasTest/ReporteGeneracionTest: private setup
 * helpers instead of repeating factory boilerplate, one test_* per
 * scenario/assertion, JSON verbs for the AJAX endpoints (store/update/
 * destroy/secciones.actualizar/sugerir-consultas) and plain get()/post()
 * for the HTML/download endpoints (preview/pdf), with a dedicated 401 test
 * per JSON verb.
 */
class PropuestasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    // --- helpers -------------------------------------------------------------

    /** Cliente con una Campaña SEO real vinculada (servicio tipo 'seo'). */
    private function clienteConCampana(array $clienteOverrides = [], array $campanaOverrides = []): array
    {
        $cliente = Cliente::factory()->create($clienteOverrides);
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'seo']);
        $campana = SeoCampana::create(array_merge([
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Campaña SEO de Prueba',
            'estado' => 'activa',
        ], $campanaOverrides));

        return [$cliente, $campana];
    }

    /** Crea una Propuesta directo por Eloquent, mismos campos que store(). */
    private function propuesta(Cliente $cliente, array $overrides = []): Propuesta
    {
        return Propuesta::create(Plantilla::vacio() + array_merge([
            'cliente_id' => $cliente->id,
            'seo_campana_id' => null,
            'titulo' => 'Propuesta de Continuidad SEO',
            'estado' => EstadoPropuesta::Borrador->value,
        ], $overrides));
    }

    private function keyword(Cliente $cliente, array $overrides = []): Keyword
    {
        return Keyword::create(array_merge([
            'cliente_id' => $cliente->id,
            'keyword' => 'keyword de prueba',
            'posicion_actual' => 5,
        ], $overrides));
    }

    // --- store -----------------------------------------------------------------

    public function test_store_requires_cliente_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.propuestas.store'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('cliente_id');
    }

    public function test_store_creates_propuesta_with_borrador_estado_and_default_titulo(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $this->actingAs($user)->postJson(route('admin.propuestas.store'), [
            'cliente_id' => $cliente->id,
        ])->assertCreated();

        $propuesta = Propuesta::where('cliente_id', $cliente->id)->firstOrFail();

        $this->assertSame(EstadoPropuesta::Borrador, $propuesta->estado);
        $this->assertSame('Propuesta de Continuidad SEO', $propuesta->titulo);
        $this->assertSame($user->id, $propuesta->creado_por);
    }

    public function test_store_seeds_the_five_json_columns_from_plantilla_vacio(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $this->actingAs($user)->postJson(route('admin.propuestas.store'), [
            'cliente_id' => $cliente->id,
        ])->assertCreated();

        $propuesta = Propuesta::where('cliente_id', $cliente->id)->firstOrFail();

        $this->assertCount(5, $propuesta->situacion_actual['kpis']);
        $this->assertCount(3, $propuesta->condiciones_proyeccion['opciones_renegociacion']);
        $this->assertCount(6, $propuesta->condiciones_proyeccion['condiciones']);
        $this->assertNotNull($propuesta->resumen);
        $this->assertNotNull($propuesta->contexto_continuidad);
        $this->assertNotNull($propuesta->plan_detalle);
    }

    public function test_store_rejects_seo_campana_belonging_to_a_different_cliente(): void
    {
        $user = User::factory()->create();
        $clienteA = Cliente::factory()->create();
        [$clienteB, $campanaB] = $this->clienteConCampana();

        $response = $this->actingAs($user)->postJson(route('admin.propuestas.store'), [
            'cliente_id' => $clienteA->id,
            'seo_campana_id' => $campanaB->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('seo_campana_id');
    }

    public function test_store_accepts_seo_campana_belonging_to_the_same_cliente(): void
    {
        $user = User::factory()->create();
        [$cliente, $campana] = $this->clienteConCampana();

        $this->actingAs($user)->postJson(route('admin.propuestas.store'), [
            'cliente_id' => $cliente->id,
            'seo_campana_id' => $campana->id,
        ])->assertCreated();

        $propuesta = Propuesta::where('cliente_id', $cliente->id)->firstOrFail();
        $this->assertSame($campana->id, $propuesta->seo_campana_id);
    }

    public function test_store_response_has_show_url_and_torow_shaped_propuesta(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.propuestas.store'), [
            'cliente_id' => $cliente->id,
        ]);

        $response->assertCreated();
        $propuesta = Propuesta::where('cliente_id', $cliente->id)->firstOrFail();

        $response->assertJsonStructure([
            'show_url',
            'propuesta' => [
                'id', 'folio', 'titulo', 'cliente_id', 'cliente', 'estado',
                'estado_label', 'precio_mensual', 'fecha_emision', 'updated_at', 'show_url',
            ],
        ]);
        $response->assertJsonPath('show_url', route('admin.propuestas.show', $propuesta));
    }

    public function test_store_requires_authentication(): void
    {
        $cliente = Cliente::factory()->create();

        $response = $this->postJson(route('admin.propuestas.store'), ['cliente_id' => $cliente->id]);

        $response->assertStatus(401);
    }

    // --- update ------------------------------------------------------------------

    public function test_update_changes_estado(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);

        $this->actingAs(User::factory()->create())
            ->putJson(route('admin.propuestas.update', $propuesta), ['estado' => 'enviada'])
            ->assertOk();

        $this->assertSame(EstadoPropuesta::Enviada, $propuesta->refresh()->estado);
    }

    public function test_update_rejects_invalid_estado(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);

        $response = $this->actingAs(User::factory()->create())
            ->putJson(route('admin.propuestas.update', $propuesta), ['estado' => 'invalido']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('estado');
    }

    public function test_update_ignores_attempts_to_change_cliente_and_campana(): void
    {
        [$cliente, $campana] = $this->clienteConCampana();
        $propuesta = $this->propuesta($cliente, ['seo_campana_id' => $campana->id]);

        $otroCliente = Cliente::factory()->create();
        [, $otraCampana] = $this->clienteConCampana();

        $this->actingAs(User::factory()->create())->putJson(route('admin.propuestas.update', $propuesta), [
            'estado' => 'enviada',
            'cliente_id' => $otroCliente->id,
            'seo_campana_id' => $otraCampana->id,
        ])->assertOk();

        $propuesta->refresh();
        $this->assertSame($cliente->id, $propuesta->cliente_id);
        $this->assertSame($campana->id, $propuesta->seo_campana_id);
    }

    public function test_update_requires_authentication(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);

        $response = $this->putJson(route('admin.propuestas.update', $propuesta), ['estado' => 'enviada']);

        $response->assertStatus(401);
    }

    // --- destroy -------------------------------------------------------------------

    public function test_destroy_soft_deletes_and_returns_deleted_true(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);

        $response = $this->actingAs(User::factory()->create())
            ->deleteJson(route('admin.propuestas.destroy', $propuesta));

        $response->assertOk();
        $response->assertExactJson(['deleted' => true]);
        $this->assertSoftDeleted('propuestas', ['id' => $propuesta->id]);
    }

    public function test_destroy_requires_authentication(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);

        $response = $this->deleteJson(route('admin.propuestas.destroy', $propuesta));

        $response->assertStatus(401);
    }

    // --- actualizarSeccion: resumen --------------------------------------------------

    private function payloadResumen(array $overrides = []): array
    {
        return array_merge([
            'titulo' => 'Propuesta Editada',
            'precio_mensual' => 1500.50,
            'horas_mensuales' => 40,
            'tarifa_hora' => 37.51,
            'sitio_web' => 'https://cliente.com',
            'subtitulo_plan' => 'Plan Continuidad (Puente 3 meses)',
            'vigencia_label' => 'Septiembre – Noviembre 2026',
            'estadisticas_destacadas' => [
                ['etiqueta' => 'Impresiones', 'valor' => '120,000', 'nota' => '+30%'],
                ['etiqueta' => 'Clics orgánicos', 'valor' => '3,400', 'nota' => '+18%'],
            ],
        ], $overrides);
    }

    public function test_actualizar_seccion_resumen_persists_real_columns_and_json_from_one_payload(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);

        $this->actingAs(User::factory()->create())
            ->patchJson(route('admin.propuestas.secciones.actualizar', [$propuesta, 'resumen']), $this->payloadResumen())
            ->assertOk();

        $propuesta->refresh();

        // Columnas reales.
        $this->assertSame('Propuesta Editada', $propuesta->titulo);
        $this->assertSame(1500.50, (float) $propuesta->precio_mensual);
        $this->assertSame(40, $propuesta->horas_mensuales);
        $this->assertSame(37.51, (float) $propuesta->tarifa_hora);

        // Columna JSON `resumen` (sin las 4 anteriores).
        $this->assertSame('https://cliente.com', $propuesta->resumen['sitio_web']);
        $this->assertSame('Plan Continuidad (Puente 3 meses)', $propuesta->resumen['subtitulo_plan']);
        $this->assertSame('Septiembre – Noviembre 2026', $propuesta->resumen['vigencia_label']);
        $this->assertCount(2, $propuesta->resumen['estadisticas_destacadas']);
        $this->assertSame('Impresiones', $propuesta->resumen['estadisticas_destacadas'][0]['etiqueta']);
    }

    public function test_actualizar_seccion_resumen_rejects_fewer_than_two_estadisticas(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);

        $payload = $this->payloadResumen(['estadisticas_destacadas' => [
            ['etiqueta' => 'Impresiones', 'valor' => '120,000', 'nota' => ''],
        ]]);

        $response = $this->actingAs(User::factory()->create())
            ->patchJson(route('admin.propuestas.secciones.actualizar', [$propuesta, 'resumen']), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('estadisticas_destacadas');
    }

    public function test_actualizar_seccion_resumen_rejects_more_than_four_estadisticas(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);

        $payload = $this->payloadResumen(['estadisticas_destacadas' => array_fill(0, 5, [
            'etiqueta' => 'Etiqueta', 'valor' => '1', 'nota' => '',
        ])]);

        $response = $this->actingAs(User::factory()->create())
            ->patchJson(route('admin.propuestas.secciones.actualizar', [$propuesta, 'resumen']), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('estadisticas_destacadas');
    }

    // --- actualizarSeccion: situacion --------------------------------------------------

    private function payloadSituacion(array $overrides = []): array
    {
        return array_merge([
            'kpis' => [
                ['etiqueta' => 'Impresiones totales', 'valor' => '120,000', 'color' => '#0E1B2A'],
                ['etiqueta' => 'Clics orgánicos', 'valor' => '3,400', 'color' => '#0FA37F'],
                ['etiqueta' => 'CTR promedio', 'valor' => '2.83%', 'color' => '#0E1B2A'],
                ['etiqueta' => 'Posición media ponderada', 'valor' => '8.2', 'color' => '#0FA37F'],
                ['etiqueta' => 'Páginas sin indexar', 'valor' => '26', 'color' => '#C0392B'],
            ],
            'periodo_comparacion' => ['label_1' => 'Jul 2026', 'label_2' => 'Ago 2026'],
            'tabla_comparacion' => [
                ['metrica' => 'Impresiones', 'valor_1' => '100000', 'valor_2' => '120000'],
            ],
            'tabla_consultas' => [
                ['consulta' => 'hoteles cdmx', 'posicion' => '4', 'impresiones' => '500', 'clics' => '40', 'oportunidad' => 'alta'],
            ],
            'resumen_texto' => 'Texto de resumen personalizado.',
            'insight_texto' => 'Insight personalizado.',
        ], $overrides);
    }

    public function test_actualizar_seccion_situacion_persists_into_situacion_actual(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);

        $this->actingAs(User::factory()->create())
            ->patchJson(route('admin.propuestas.secciones.actualizar', [$propuesta, 'situacion']), $this->payloadSituacion())
            ->assertOk();

        $situacion = $propuesta->refresh()->situacion_actual;

        $this->assertCount(5, $situacion['kpis']);
        $this->assertSame('Jul 2026', $situacion['periodo_comparacion']['label_1']);
        $this->assertSame('hoteles cdmx', $situacion['tabla_consultas'][0]['consulta']);
        $this->assertSame('Texto de resumen personalizado.', $situacion['resumen_texto']);
    }

    public function test_actualizar_seccion_situacion_rejects_four_kpis(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);

        $payload = $this->payloadSituacion();
        array_pop($payload['kpis']);

        $response = $this->actingAs(User::factory()->create())
            ->patchJson(route('admin.propuestas.secciones.actualizar', [$propuesta, 'situacion']), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('kpis');
    }

    public function test_actualizar_seccion_situacion_rejects_six_kpis(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);

        $payload = $this->payloadSituacion();
        $payload['kpis'][] = ['etiqueta' => 'Extra', 'valor' => '1', 'color' => '#000'];

        $response = $this->actingAs(User::factory()->create())
            ->patchJson(route('admin.propuestas.secciones.actualizar', [$propuesta, 'situacion']), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('kpis');
    }

    public function test_actualizar_seccion_situacion_rejects_empty_tabla_consultas(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);

        $payload = $this->payloadSituacion(['tabla_consultas' => []]);

        $response = $this->actingAs(User::factory()->create())
            ->patchJson(route('admin.propuestas.secciones.actualizar', [$propuesta, 'situacion']), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('tabla_consultas');
    }

    // --- actualizarSeccion: contexto --------------------------------------------------

    private function payloadContexto(array $overrides = []): array
    {
        return array_merge([
            'tabla_riesgos' => [
                ['riesgo' => 'Pérdida de posiciones', 'impacto' => 'Caída de tráfico orgánico'],
            ],
            'checklist_protege' => [
                ['titulo' => 'Monitoreo continuo', 'descripcion' => 'Seguimiento semanal de posiciones'],
            ],
            'intro_texto' => 'Texto introductorio personalizado.',
            'logica_negocio_texto' => 'Lógica de negocio personalizada.',
        ], $overrides);
    }

    public function test_actualizar_seccion_contexto_persists_into_contexto_continuidad(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);

        $this->actingAs(User::factory()->create())
            ->patchJson(route('admin.propuestas.secciones.actualizar', [$propuesta, 'contexto']), $this->payloadContexto())
            ->assertOk();

        $contexto = $propuesta->refresh()->contexto_continuidad;

        $this->assertSame('Pérdida de posiciones', $contexto['tabla_riesgos'][0]['riesgo']);
        $this->assertSame('Monitoreo continuo', $contexto['checklist_protege'][0]['titulo']);
        $this->assertSame('Texto introductorio personalizado.', $contexto['intro_texto']);
    }

    public function test_actualizar_seccion_contexto_rejects_empty_tabla_riesgos(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);

        $payload = $this->payloadContexto(['tabla_riesgos' => []]);

        $response = $this->actingAs(User::factory()->create())
            ->patchJson(route('admin.propuestas.secciones.actualizar', [$propuesta, 'contexto']), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('tabla_riesgos');
    }

    public function test_actualizar_seccion_contexto_rejects_empty_checklist_protege(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);

        $payload = $this->payloadContexto(['checklist_protege' => []]);

        $response = $this->actingAs(User::factory()->create())
            ->patchJson(route('admin.propuestas.secciones.actualizar', [$propuesta, 'contexto']), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('checklist_protege');
    }

    // --- actualizarSeccion: plan --------------------------------------------------

    private function payloadPlan(array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Plan Continuidad',
            'duracion_meses' => '3',
            'vigencia_inicio_texto' => '1 de septiembre de 2026',
            'vigencia_fin_texto' => '30 de noviembre de 2026',
            'alcance_incluido' => ['Optimización on-page', 'Link building'],
            'alcance_no_incluido' => ['Rediseño del sitio'],
            'meses' => [
                ['mes_label' => 'M1', 'calendario' => 'Sep 2026', 'actividades' => 'Auditoría', 'entregable' => 'Reporte', 'horas' => '10'],
            ],
        ], $overrides);
    }

    public function test_actualizar_seccion_plan_persists_into_plan_detalle(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);

        $this->actingAs(User::factory()->create())
            ->patchJson(route('admin.propuestas.secciones.actualizar', [$propuesta, 'plan']), $this->payloadPlan())
            ->assertOk();

        $plan = $propuesta->refresh()->plan_detalle;

        $this->assertSame('Plan Continuidad', $plan['nombre']);
        $this->assertSame('Optimización on-page', $plan['alcance_incluido'][0]);
        $this->assertSame('Rediseño del sitio', $plan['alcance_no_incluido'][0]);
        $this->assertSame('M1', $plan['meses'][0]['mes_label']);
    }

    public function test_actualizar_seccion_plan_rejects_empty_alcance_incluido(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);

        $payload = $this->payloadPlan(['alcance_incluido' => []]);

        $response = $this->actingAs(User::factory()->create())
            ->patchJson(route('admin.propuestas.secciones.actualizar', [$propuesta, 'plan']), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('alcance_incluido');
    }

    public function test_actualizar_seccion_plan_rejects_empty_alcance_no_incluido(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);

        $payload = $this->payloadPlan(['alcance_no_incluido' => []]);

        $response = $this->actingAs(User::factory()->create())
            ->patchJson(route('admin.propuestas.secciones.actualizar', [$propuesta, 'plan']), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('alcance_no_incluido');
    }

    public function test_actualizar_seccion_plan_rejects_empty_meses(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);

        $payload = $this->payloadPlan(['meses' => []]);

        $response = $this->actingAs(User::factory()->create())
            ->patchJson(route('admin.propuestas.secciones.actualizar', [$propuesta, 'plan']), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('meses');
    }

    // --- actualizarSeccion: condiciones --------------------------------------------------

    private function payloadCondiciones(array $overrides = []): array
    {
        return array_merge([
            'condiciones' => [
                ['etiqueta' => 'Duración', 'valor' => '3 meses'],
                ['etiqueta' => 'Inversión', 'valor' => '$1,500 MXN/mes'],
            ],
            'opciones_renegociacion' => [
                ['nombre' => 'Opción A', 'descripcion' => 'Continuidad mensual'],
                ['nombre' => 'Opción B', 'descripcion' => 'Continuidad trimestral'],
                ['nombre' => 'Opción C', 'descripcion' => 'Continuidad anual'],
            ],
            'proyeccion_periodo_label' => 'Proyección Nov 2026',
            'tabla_proyeccion' => [
                ['metrica' => 'Impresiones', 'base' => '120000', 'proyeccion' => '150000', 'escenario' => 'Conservador'],
            ],
        ], $overrides);
    }

    public function test_actualizar_seccion_condiciones_persists_into_condiciones_proyeccion(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);

        $this->actingAs(User::factory()->create())
            ->patchJson(route('admin.propuestas.secciones.actualizar', [$propuesta, 'condiciones']), $this->payloadCondiciones())
            ->assertOk();

        $condiciones = $propuesta->refresh()->condiciones_proyeccion;

        $this->assertSame('Duración', $condiciones['condiciones'][0]['etiqueta']);
        $this->assertCount(3, $condiciones['opciones_renegociacion']);
        $this->assertSame('Proyección Nov 2026', $condiciones['proyeccion_periodo_label']);
        $this->assertSame('Impresiones', $condiciones['tabla_proyeccion'][0]['metrica']);
    }

    public function test_actualizar_seccion_condiciones_rejects_two_opciones_renegociacion(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);

        $payload = $this->payloadCondiciones();
        array_pop($payload['opciones_renegociacion']);

        $response = $this->actingAs(User::factory()->create())
            ->patchJson(route('admin.propuestas.secciones.actualizar', [$propuesta, 'condiciones']), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('opciones_renegociacion');
    }

    public function test_actualizar_seccion_condiciones_rejects_four_opciones_renegociacion(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);

        $payload = $this->payloadCondiciones();
        $payload['opciones_renegociacion'][] = ['nombre' => 'Opción D', 'descripcion' => 'Extra'];

        $response = $this->actingAs(User::factory()->create())
            ->patchJson(route('admin.propuestas.secciones.actualizar', [$propuesta, 'condiciones']), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('opciones_renegociacion');
    }

    public function test_actualizar_seccion_condiciones_rejects_empty_condiciones(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);

        $payload = $this->payloadCondiciones(['condiciones' => []]);

        $response = $this->actingAs(User::factory()->create())
            ->patchJson(route('admin.propuestas.secciones.actualizar', [$propuesta, 'condiciones']), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('condiciones');
    }

    public function test_actualizar_seccion_condiciones_rejects_empty_tabla_proyeccion(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);

        $payload = $this->payloadCondiciones(['tabla_proyeccion' => []]);

        $response = $this->actingAs(User::factory()->create())
            ->patchJson(route('admin.propuestas.secciones.actualizar', [$propuesta, 'condiciones']), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('tabla_proyeccion');
    }

    // --- actualizarSeccion: sección inválida / auth --------------------------------------

    public function test_actualizar_seccion_with_unknown_seccion_returns_404(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);

        $response = $this->actingAs(User::factory()->create())
            ->patchJson(route('admin.propuestas.secciones.actualizar', [$propuesta, 'bogus']), []);

        $response->assertStatus(404);
    }

    public function test_actualizar_seccion_requires_authentication(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);

        $response = $this->patchJson(
            route('admin.propuestas.secciones.actualizar', [$propuesta, 'resumen']),
            $this->payloadResumen()
        );

        $response->assertStatus(401);
    }

    // --- sugerirConsultas ------------------------------------------------------------

    public function test_sugerir_consultas_without_campana_returns_422(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente, ['seo_campana_id' => null]);

        $response = $this->actingAs(User::factory()->create())
            ->postJson(route('admin.propuestas.sugerir-consultas', $propuesta));

        $response->assertStatus(422);
        $response->assertJsonStructure(['message']);
    }

    public function test_sugerir_consultas_pulls_real_keywords_from_the_clientes_bank(): void
    {
        [$cliente, $campana] = $this->clienteConCampana();
        $propuesta = $this->propuesta($cliente, ['seo_campana_id' => $campana->id]);

        $this->keyword($cliente, ['keyword' => 'hoteles en cdmx', 'posicion_actual' => 4]);
        $this->keyword($cliente, ['keyword' => 'renta de salones', 'posicion_actual' => 11]);

        $response = $this->actingAs(User::factory()->create())
            ->postJson(route('admin.propuestas.sugerir-consultas', $propuesta));

        $response->assertOk();

        $consultas = collect($response->json('situacion_actual.tabla_consultas'));

        $fila1 = $consultas->firstWhere('consulta', 'hoteles en cdmx');
        $fila2 = $consultas->firstWhere('consulta', 'renta de salones');

        $this->assertNotNull($fila1);
        $this->assertSame('4', $fila1['posicion']);
        $this->assertNotNull($fila2);
        $this->assertSame('11', $fila2['posicion']);
    }

    public function test_sugerir_consultas_appends_without_removing_manually_entered_rows(): void
    {
        [$cliente, $campana] = $this->clienteConCampana();
        $propuesta = $this->propuesta($cliente, ['seo_campana_id' => $campana->id]);

        // Fila capturada a mano por el usuario, distinta de cualquier keyword del banco.
        $situacion = $propuesta->situacion_actual;
        $situacion['tabla_consultas'] = [
            ['consulta' => 'consulta manual escrita a mano', 'posicion' => '2', 'impresiones' => '900', 'clics' => '80', 'oportunidad' => 'alta'],
        ];
        $propuesta->update(['situacion_actual' => $situacion]);

        $this->keyword($cliente, ['keyword' => 'keyword del banco', 'posicion_actual' => 7]);

        $response = $this->actingAs(User::factory()->create())
            ->postJson(route('admin.propuestas.sugerir-consultas', $propuesta));

        $response->assertOk();

        $consultas = collect($response->json('situacion_actual.tabla_consultas'));

        $this->assertNotNull($consultas->firstWhere('consulta', 'consulta manual escrita a mano'));
        $this->assertNotNull($consultas->firstWhere('consulta', 'keyword del banco'));
    }

    public function test_sugerir_consultas_with_empty_keyword_bank_returns_422(): void
    {
        [$cliente, $campana] = $this->clienteConCampana();
        $propuesta = $this->propuesta($cliente, ['seo_campana_id' => $campana->id]);

        $response = $this->actingAs(User::factory()->create())
            ->postJson(route('admin.propuestas.sugerir-consultas', $propuesta));

        $response->assertStatus(422);
        $response->assertJsonStructure(['message']);
    }

    // --- generarPdf --------------------------------------------------------------------

    public function test_generar_pdf_first_call_assigns_folio_and_creates_archivo(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);
        $user = User::factory()->create();

        $this->assertNull($propuesta->folio);
        $this->assertNull($propuesta->fecha_emision);

        $this->actingAs($user)->post(route('admin.propuestas.pdf', $propuesta))->assertOk();

        $propuesta->refresh();
        $this->assertNotNull($propuesta->folio);
        $this->assertStringStartsWith('PROP-CONT-', $propuesta->folio);
        $this->assertNotNull($propuesta->fecha_emision);

        $archivo = Archivo::where('cliente_id', $propuesta->cliente_id)
            ->where('tipo', TipoArchivo::Propuesta->value)
            ->first();

        $this->assertNotNull($archivo);
        $this->assertSame(TipoArchivo::Propuesta, $archivo->tipo);
        $this->assertSame($propuesta->cliente_id, $archivo->cliente_id);
        $this->assertSame($user->id, $archivo->subido_por);
    }

    public function test_generar_pdf_second_call_keeps_the_same_folio(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('admin.propuestas.pdf', $propuesta))->assertOk();
        $primerFolio = $propuesta->refresh()->folio;

        $this->actingAs($user)->post(route('admin.propuestas.pdf', $propuesta))->assertOk();
        $segundoFolio = $propuesta->refresh()->folio;

        $this->assertSame($primerFolio, $segundoFolio);
    }

    // --- preview -----------------------------------------------------------------------

    public function test_preview_returns_ok_for_authenticated_user(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.propuestas.preview', $propuesta))
            ->assertOk();
    }

    // --- index -------------------------------------------------------------------------

    public function test_index_filters_by_estado_query_param(): void
    {
        $clienteA = Cliente::factory()->create();
        $clienteB = Cliente::factory()->create();
        $propuestaBorrador = $this->propuesta($clienteA, ['estado' => EstadoPropuesta::Borrador->value]);
        $propuestaAprobada = $this->propuesta($clienteB, ['estado' => EstadoPropuesta::Aprobada->value]);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('admin.propuestas.index', ['estado' => 'aprobada']));

        $response->assertOk();
        $ids = $response->viewData('propuestas')->pluck('id');

        $this->assertTrue($ids->contains($propuestaAprobada->id));
        $this->assertFalse($ids->contains($propuestaBorrador->id));
    }

    public function test_kpi_tasa_aprobacion_is_null_with_no_decided_propuestas(): void
    {
        $cliente = Cliente::factory()->create();
        $this->propuesta($cliente, ['estado' => EstadoPropuesta::Borrador->value]);
        $this->propuesta($cliente, ['estado' => EstadoPropuesta::Enviada->value]);

        $response = $this->actingAs(User::factory()->create())->get(route('admin.propuestas.index'));

        $this->assertNull($response->viewData('kpis')['tasa_aprobacion']);
    }

    public function test_kpi_tasa_aprobacion_computes_correct_percentage(): void
    {
        $cliente = Cliente::factory()->create();
        $this->propuesta($cliente, ['estado' => EstadoPropuesta::Aprobada->value]);
        $this->propuesta($cliente, ['estado' => EstadoPropuesta::Aprobada->value]);
        $this->propuesta($cliente, ['estado' => EstadoPropuesta::Rechazada->value]);
        $this->propuesta($cliente, ['estado' => EstadoPropuesta::Borrador->value]);

        $response = $this->actingAs(User::factory()->create())->get(route('admin.propuestas.index'));

        // 2 aprobadas de 3 decididas = 66.66...% -> redondeado a 67 (round() da float).
        $this->assertSame(67.0, $response->viewData('kpis')['tasa_aprobacion']);
    }

    public function test_soft_deleted_propuestas_are_excluded_from_index(): void
    {
        $cliente = Cliente::factory()->create();
        $propuesta = $this->propuesta($cliente);
        $propuesta->delete();

        $response = $this->actingAs(User::factory()->create())->get(route('admin.propuestas.index'));

        $this->assertFalse($response->viewData('propuestas')->pluck('id')->contains($propuesta->id));
    }
}
