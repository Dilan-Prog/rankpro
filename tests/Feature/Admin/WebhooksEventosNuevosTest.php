<?php

namespace Tests\Feature\Admin;

use App\Enums\AreaReporte;
use App\Enums\EstadoReporte;
use App\Models\Cliente;
use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Models\MetricaCliente;
use App\Models\MetricaClienteValor;
use App\Models\Reporte;
use App\Models\ReporteProgramacion;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookEntrega;
use App\Support\Webhooks\Eventos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Webhooks de Métricas, Reportes programados y rondas de keywords. Se leen las
 * filas de webhook_entregas (el Despachador las inserta al emitir, sin HTTP).
 */
class WebhooksEventosNuevosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        Mail::fake();
        Carbon::setTestNow(Carbon::create(2026, 11, 5, 10, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function suscribir(array $eventos = ['*']): Webhook
    {
        return Webhook::factory()->create(['eventos' => $eventos]);
    }

    private function entregas(string $evento)
    {
        return WebhookEntrega::where('evento', $evento)->get();
    }

    private function metrica(Cliente $cliente, array $extra = []): MetricaCliente
    {
        return MetricaCliente::create($extra + [
            'cliente_id' => $cliente->id, 'nombre' => 'Llamadas', 'unidad' => 'llamadas',
            'meta' => 100, 'sentido' => 'mayor', 'umbral_amarillo_pct' => 80,
        ]);
    }

    private function valor(MetricaCliente $m, string $periodo, $valor): MetricaClienteValor
    {
        return MetricaClienteValor::create(['metrica_id' => $m->id, 'periodo' => $periodo, 'valor' => $valor]);
    }

    public function test_el_catalogo_trae_los_eventos_nuevos_y_la_pantalla_los_lista(): void
    {
        foreach (['metrica.valor_registrado', 'metrica.valor_eliminado', 'metrica.semaforo_rojo', 'reporte_programado.enviado', 'reporte_programado.fallido', 'keyword.ronda_registrada'] as $e) {
            $this->assertTrue(Eventos::existe($e), $e);
        }

        $this->actingAs(User::factory()->create())
            ->get('/admin/integraciones/webhooks')
            ->assertOk()
            ->assertSee('metrica.semaforo_rojo')
            ->assertSee('reporte_programado.fallido')
            ->assertSee('keyword.ronda_registrada');
    }

    public function test_valor_nuevo_emite_registrado_con_payload_completo_y_anterior(): void
    {
        $this->suscribir();
        $cliente = Cliente::factory()->create(['nombre' => 'Hotel Sol']);
        $metrica = $this->metrica($cliente);
        $this->valor($metrica, '2026-09-01', 90);
        WebhookEntrega::query()->delete();

        $this->valor($metrica, '2026-10-01', 95);

        $entregas = $this->entregas('metrica.valor_registrado');
        $this->assertCount(1, $entregas);
        $this->assertEquals([
            'cliente_id' => $cliente->id, 'cliente' => 'Hotel Sol', 'metrica_id' => $metrica->id,
            'nombre' => 'Llamadas', 'unidad' => 'llamadas', 'periodo' => '2026-10',
            'valor' => 95.0, 'valor_anterior' => 90.0, 'meta' => 100.0, 'sentido' => 'mayor', 'semaforo' => 'amarillo',
        ], $entregas->first()->payload['datos']);
        $this->assertCount(0, $this->entregas('metrica.semaforo_rojo'));
    }

    public function test_primer_valor_no_tiene_anterior(): void
    {
        $this->suscribir();
        $this->valor($this->metrica(Cliente::factory()->create()), '2026-10-01', 120);

        $this->assertNull($this->entregas('metrica.valor_registrado')->first()->payload['datos']['valor_anterior']);
    }

    public function test_semaforo_rojo_se_emite_solo_cuando_el_valor_es_rojo(): void
    {
        $this->suscribir();
        $metrica = $this->metrica(Cliente::factory()->create());

        $this->valor($metrica, '2026-09-01', 50);
        $this->assertCount(1, $this->entregas('metrica.semaforo_rojo'));
        $this->assertSame('rojo', $this->entregas('metrica.semaforo_rojo')->first()->payload['datos']['semaforo']);

        $this->valor($metrica, '2026-10-01', 150);
        $this->assertCount(1, $this->entregas('metrica.semaforo_rojo'));
        $this->assertCount(2, $this->entregas('metrica.valor_registrado'));
    }

    public function test_actualizar_con_el_mismo_valor_no_emite_y_con_otro_si(): void
    {
        $this->suscribir();
        $metrica = $this->metrica(Cliente::factory()->create());
        $valor = $this->valor($metrica, '2026-10-01', 50);
        WebhookEntrega::query()->delete();

        // Mismo número como texto (la pantalla web manda "50.00") y solo cambia la nota.
        MetricaClienteValor::updateOrCreate(['metrica_id' => $metrica->id, 'periodo' => '2026-10-01'], ['valor' => '50.00', 'nota' => 'revisado']);
        $this->assertSame(0, WebhookEntrega::count());

        $valor->fresh()->update(['valor' => 60]);
        $this->assertCount(1, $this->entregas('metrica.valor_registrado'));
        $this->assertCount(1, $this->entregas('metrica.semaforo_rojo'));
        $this->assertEquals(60, $this->entregas('metrica.valor_registrado')->first()->payload['datos']['valor']);
    }

    public function test_borrar_un_valor_emite_eliminado_tambien_desde_la_pantalla_web(): void
    {
        $this->suscribir();
        $cliente = Cliente::factory()->create();
        $metrica = $this->metrica($cliente);
        $this->valor($metrica, '2026-10-01', 80);
        WebhookEntrega::query()->delete();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.metricas.valores.store'), ['cliente_id' => $cliente->id, 'mes' => '2026-10', 'valores' => [$metrica->id => '']])
            ->assertRedirect();

        $this->assertDatabaseCount('metrica_cliente_valores', 0);
        $this->assertCount(1, $this->entregas('metrica.valor_eliminado'));
        $this->assertCount(0, $this->entregas('metrica.valor_registrado'));
    }

    public function test_un_webhook_suscrito_a_otro_evento_no_recibe_los_nuevos(): void
    {
        $this->suscribir(['finanza.pagada']);
        $metrica = $this->metrica(Cliente::factory()->create());
        $this->valor($metrica, '2026-10-01', 10);

        $this->assertSame(0, WebhookEntrega::count());

        $this->suscribir(['metrica.semaforo_rojo']);
        $this->valor($metrica, '2026-11-01', 10);
        $this->assertSame(['metrica.semaforo_rojo'], WebhookEntrega::pluck('evento')->all());
    }

    // --- reportes programados --------------------------------------------

    private function programacion(Cliente $cliente, array $o = []): ReporteProgramacion
    {
        return ReporteProgramacion::factory()->create($o + [
            'cliente_id' => $cliente->id,
            'destinatarios' => ['a@example.com', 'b@example.com'],
            'proximo_envio_en' => now()->subMinutes(5),
        ]);
    }

    private function reporte(Cliente $cliente, string $estado = 'listo'): Reporte
    {
        return Reporte::create([
            'cliente_id' => $cliente->id, 'area' => AreaReporte::Seo->value, 'titulo' => 'Octubre',
            'periodo_inicio' => '2026-10-01', 'periodo_fin' => '2026-10-31', 'estado' => $estado, 'numero' => null,
        ]);
    }

    public function test_reporte_programado_enviado_se_emite_una_vez_desde_el_comando(): void
    {
        $this->suscribir(['reporte_programado.*']);
        $cliente = Cliente::factory()->create(['nombre' => 'Hotel Sol']);
        $reporte = $this->reporte($cliente);
        $prog = $this->programacion($cliente);

        $this->artisan('reportes:enviar-programados')->assertSuccessful();
        // Segunda corrida el mismo mes: no repite.
        $this->artisan('reportes:enviar-programados')->assertSuccessful();

        $entregas = $this->entregas('reporte_programado.enviado');
        $this->assertCount(1, $entregas);
        $datos = $entregas->first()->payload['datos'];
        $this->assertSame($prog->id, $datos['programacion_id']);
        $this->assertSame($cliente->id, $datos['cliente_id']);
        $this->assertSame('Hotel Sol', $datos['cliente']);
        $this->assertSame($reporte->id, $datos['reporte_id']);
        $this->assertSame('2026-10', $datos['periodo']);
        $this->assertSame(['a@example.com', 'b@example.com'], $datos['destinatarios']);
        $this->assertSame(2, $datos['total_destinatarios']);
        $this->assertSame($prog->fresh()->ultimo_correo_envio_id, $datos['correo_envio_id']);
        $this->assertCount(0, $this->entregas('reporte_programado.fallido'));
    }

    public function test_enviar_ahora_tambien_emite_pero_probar_no(): void
    {
        $this->suscribir(['reporte_programado.*']);
        $cliente = Cliente::factory()->create();
        $this->reporte($cliente);
        $prog = $this->programacion($cliente);
        $usuario = User::factory()->create();
        WebhookEntrega::query()->delete();

        $this->actingAs($usuario)->postJson(route('admin.reportes.programados.probar', $prog))->assertOk();
        $this->assertSame(0, WebhookEntrega::count());

        $this->actingAs($usuario)->postJson(route('admin.reportes.programados.enviar-ahora', $prog))->assertOk();
        $this->assertCount(1, $this->entregas('reporte_programado.enviado'));
    }

    public function test_fallo_real_emite_fallido_con_el_error(): void
    {
        $this->suscribir();
        $cliente = Cliente::factory()->create();
        $this->reporte($cliente);
        $this->programacion($cliente, ['plantilla_id' => null]);

        $this->artisan('reportes:enviar-programados')->assertSuccessful();

        $entregas = $this->entregas('reporte_programado.fallido');
        $this->assertCount(1, $entregas);
        $this->assertStringContainsString('plantilla', $entregas->first()->payload['datos']['error']);
        $this->assertNull($entregas->first()->payload['datos']['correo_envio_id']);
        $this->assertCount(0, $this->entregas('reporte_programado.enviado'));
    }

    public function test_reporte_no_listo_reintentado_emite_fallido_solo_la_primera_vez(): void
    {
        $this->suscribir();
        $cliente = Cliente::factory()->create();
        $reporte = $this->reporte($cliente, 'borrador');
        $this->programacion($cliente);

        foreach (range(1, 3) as $corrida) {
            $this->artisan('reportes:enviar-programados')->assertSuccessful();
        }
        $this->assertCount(1, $this->entregas('reporte_programado.fallido'));
        $this->assertNull($this->entregas('reporte_programado.fallido')->first()->payload['datos']['reporte_id']);
        $this->assertSame('2026-10', $this->entregas('reporte_programado.fallido')->first()->payload['datos']['periodo']);

        $reporte->update(['estado' => EstadoReporte::Listo->value]);
        $this->artisan('reportes:enviar-programados')->assertSuccessful();
        $this->assertCount(1, $this->entregas('reporte_programado.enviado'));
    }

    // --- rondas de keywords ----------------------------------------------

    private function lista(int $keywords = 3): array
    {
        $cliente = Cliente::factory()->create(['nombre' => 'Hotel Sol']);
        $lista = KeywordLista::create(['cliente_id' => $cliente->id, 'nombre' => 'L', 'estado' => 'en_uso']);
        $ks = [];
        foreach (range(1, $keywords) as $i) {
            $ks[] = Keyword::create(['cliente_id' => $cliente->id, 'lista_id' => $lista->id, 'keyword' => "k{$i}", 'tipo' => 'principal', 'estado' => 'en_uso']);
        }

        return [$lista, $ks];
    }

    public function test_guardar_una_ronda_emite_un_solo_resumen(): void
    {
        $this->suscribir(['keyword.ronda_registrada']);
        [$lista, $ks] = $this->lista();

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.keywords.listas.mediciones.store', $lista), [
                'fecha' => '2026-10-15',
                'mediciones' => [
                    ['keyword_id' => $ks[0]->id, 'posicion' => 4],
                    ['keyword_id' => $ks[1]->id, 'posicion' => 8],
                    ['keyword_id' => $ks[2]->id, 'posicion' => null],
                ],
            ])->assertCreated();

        $entregas = $this->entregas('keyword.ronda_registrada');
        $this->assertCount(1, $entregas);
        $this->assertEquals([
            'lista_id' => $lista->id, 'cliente_id' => $lista->cliente_id, 'cliente' => 'Hotel Sol', 'fecha' => '2026-10-15',
            'total_mediciones' => 3, 'con_posicion' => 2, 'posicion_promedio' => 6.0,
        ], $entregas->first()->payload['datos']);
    }

    public function test_el_lote_de_la_api_emite_un_resumen_por_lista_y_fecha(): void
    {
        $this->suscribir(['keyword.ronda_registrada']);
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        [$lista, $ks] = $this->lista();

        $this->postJson('/api/v1/keywords/mediciones/lote', [
            ['keyword_id' => $ks[0]->id, 'fecha' => '2026-10-15', 'posicion' => 2],
            ['keyword_id' => $ks[1]->id, 'fecha' => '2026-10-15', 'posicion' => 4],
            ['keyword_id' => $ks[0]->id, 'fecha' => '2026-10-22', 'posicion' => 1],
        ])->assertCreated()->assertJsonPath('data.procesadas', 3);

        $this->assertCount(2, $this->entregas('keyword.ronda_registrada'));
        $primera = $this->entregas('keyword.ronda_registrada')->first(fn ($e) => $e->payload['datos']['fecha'] === '2026-10-15');
        $this->assertEquals(3, $primera->payload['datos']['posicion_promedio']);
    }
}
