<?php

namespace Tests\Feature\Api;

use App\Enums\AreaReporte;
use App\Enums\EstadoReporte;
use App\Mail\CorreoPlantillaMail;
use App\Models\Cliente;
use App\Models\CorreoEnvio;
use App\Models\CorreoPlantilla;
use App\Models\Reporte;
use App\Models\ReporteProgramacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReporteProgramacionesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        // Hoy es 5 de noviembre: el periodo a enviar es octubre.
        Carbon::setTestNow(Carbon::create(2026, 11, 5, 10, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function como(array $habilidades, array $usuario = []): User
    {
        $user = User::factory()->create(['is_active' => true] + $usuario);
        Sanctum::actingAs($user, $habilidades);

        return $user;
    }

    private function reporteListo(Cliente $cliente): Reporte
    {
        return Reporte::create([
            'cliente_id' => $cliente->id,
            'area' => AreaReporte::Seo->value,
            'titulo' => 'Reporte de octubre',
            'periodo_inicio' => '2026-10-01',
            'periodo_fin' => '2026-10-31',
            'estado' => EstadoReporte::Listo->value,
            'numero' => null,
        ]);
    }

    private function programacion(?Cliente $cliente = null, array $extra = []): ReporteProgramacion
    {
        return ReporteProgramacion::factory()->create($extra + ['cliente_id' => ($cliente ?? Cliente::factory()->create())->id]);
    }

    /** @return array<string, mixed> */
    private function payload(array $extra = []): array
    {
        return $extra + [
            'cliente_id' => Cliente::factory()->create()->id,
            'area' => 'seo',
            'plantilla_id' => CorreoPlantilla::factory()->create()->id,
            'asunto' => 'Reporte de {{mes}}',
            'destinatarios' => ['uno@example.com', 'dos@example.com'],
            'dia_mes' => 10,
            'hora' => '08:30',
        ];
    }

    // --- auth ------------------------------------------------------------

    public function test_sin_token_es_401(): void
    {
        $p = $this->programacion();

        $this->getJson('/api/v1/reportes/programados')->assertUnauthorized();
        $this->postJson("/api/v1/reportes/programados/{$p->id}/enviar")->assertUnauthorized();
    }

    public function test_sin_habilidad_es_403(): void
    {
        $this->como(['clientes:leer']);
        $p = $this->programacion();

        $this->getJson('/api/v1/reportes/programados')->assertForbidden();
        $this->getJson("/api/v1/reportes/programados/{$p->id}")->assertForbidden();
    }

    public function test_solo_lectura_no_escribe_ni_envia(): void
    {
        Mail::fake();
        $this->como(['reportes:leer']);
        $p = $this->programacion();

        $this->getJson('/api/v1/reportes/programados')->assertOk();
        $this->postJson('/api/v1/reportes/programados', $this->payload())->assertForbidden();
        $this->putJson("/api/v1/reportes/programados/{$p->id}", ['asunto' => 'x'])->assertForbidden();
        $this->deleteJson("/api/v1/reportes/programados/{$p->id}")->assertForbidden();
        foreach (['pausar', 'activar', 'enviar', 'probar'] as $accion) {
            $this->postJson("/api/v1/reportes/programados/{$p->id}/{$accion}")->assertForbidden();
        }
        Mail::assertNothingSent();
    }

    // --- lectura ---------------------------------------------------------

    public function test_index_lista_con_estado_del_ultimo_envio_y_filtra(): void
    {
        $this->como(['reportes:leer']);
        $a = Cliente::factory()->create();
        $this->programacion($a, [
            'ultimo_ok' => false, 'ultimo_resultado' => 'SMTP caído', 'ultima_prueba' => '2026-11-01 10:00 · ok',
            'proximo_envio_en' => '2026-12-05 09:00:00',
        ]);
        $this->programacion(null, ['activo' => false]);
        $this->programacion(null);

        $r = $this->getJson('/api/v1/reportes/programados')->assertOk()->assertJsonCount(3, 'data');
        $fila = collect($r->json('data'))->firstWhere('cliente_id', $a->id);
        $this->assertFalse($fila['ultimo_ok']);
        $this->assertSame('SMTP caído', $fila['ultimo_resultado']);
        $this->assertSame('2026-12-05 09:00', $fila['proximo_envio_en']);
        $this->assertSame('2026-11-01 10:00 · ok', $fila['ultima_prueba']);

        $this->getJson("/api/v1/reportes/programados?cliente_id={$a->id}")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/reportes/programados?activo=0')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/reportes/programados?ultimo_ok=0')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/reportes/programados?per_page=2')->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3);
    }

    public function test_show_y_404(): void
    {
        $this->como(['reportes:leer']);
        $p = $this->programacion();

        $this->getJson("/api/v1/reportes/programados/{$p->id}")->assertOk()
            ->assertJsonPath('data.id', $p->id)->assertJsonPath('data.area', 'seo');
        $this->getJson('/api/v1/reportes/programados/999999')->assertNotFound();
    }

    public function test_la_ruta_literal_no_choca_con_el_comodin_de_reportes(): void
    {
        $this->como(['reportes:leer']);

        // Si 'programados' cayera en GET /reportes/{reporte}, esto sería 404.
        $this->getJson('/api/v1/reportes/programados')->assertOk()->assertJsonPath('data', []);
    }

    // --- escritura -------------------------------------------------------

    public function test_store_crea_activa_y_calcula_el_proximo_envio(): void
    {
        $user = $this->como(['reportes:escribir']);

        $this->postJson('/api/v1/reportes/programados', $this->payload(['destinatarios' => "UNO@example.com; dos@example.com"]))
            ->assertCreated()
            ->assertJsonPath('data.activo', true)
            ->assertJsonPath('data.destinatarios', ['uno@example.com', 'dos@example.com'])
            ->assertJsonPath('data.proximo_envio_en', '2026-11-10 08:30');

        $this->assertSame($user->id, ReporteProgramacion::first()->creado_por);
    }

    public function test_store_valida(): void
    {
        $this->como(['reportes:escribir']);

        $this->postJson('/api/v1/reportes/programados', $this->payload([
            'dia_mes' => 31, 'hora' => '25:99', 'destinatarios' => ['no-es-correo'], 'area' => 'otra',
        ]))->assertStatus(422)->assertJsonValidationErrors(['dia_mes', 'hora', 'destinatarios.0', 'area']);
        $this->assertSame(0, ReporteProgramacion::count());
    }

    public function test_update_es_parcial_y_recalcula_solo_si_cambia_el_calendario(): void
    {
        $this->como(['reportes:escribir']);
        $p = $this->programacion(null, ['dia_mes' => 5, 'hora' => '09:00', 'proximo_envio_en' => '2026-12-05 09:00:00']);

        $this->putJson("/api/v1/reportes/programados/{$p->id}", ['asunto' => 'Nuevo asunto'])->assertOk()
            ->assertJsonPath('data.asunto', 'Nuevo asunto')
            ->assertJsonPath('data.proximo_envio_en', '2026-12-05 09:00')
            ->assertJsonPath('data.destinatarios', ['cliente@example.com']);

        $this->putJson("/api/v1/reportes/programados/{$p->id}", ['dia_mes' => 20])->assertOk()
            ->assertJsonPath('data.proximo_envio_en', '2026-11-20 09:00');

        $this->putJson("/api/v1/reportes/programados/{$p->id}", ['dia_mes' => 40])->assertStatus(422);
        $this->putJson('/api/v1/reportes/programados/999999', ['asunto' => 'x'])->assertNotFound();
    }

    public function test_destroy(): void
    {
        $this->como(['reportes:escribir']);
        $p = $this->programacion();

        $this->deleteJson("/api/v1/reportes/programados/{$p->id}")->assertOk()->assertJsonPath('data.deleted', true);
        $this->assertSame(0, ReporteProgramacion::count());
        $this->deleteJson("/api/v1/reportes/programados/{$p->id}")->assertNotFound();
    }

    public function test_pausar_y_activar_son_idempotentes(): void
    {
        $this->como(['reportes:escribir']);
        $p = $this->programacion();

        foreach ([1, 2] as $_) {
            $this->postJson("/api/v1/reportes/programados/{$p->id}/pausar")->assertOk()->assertJsonPath('data.activo', false);
        }

        $this->postJson("/api/v1/reportes/programados/{$p->id}/activar")->assertOk()
            ->assertJsonPath('data.activo', true)->assertJsonPath('data.proximo_envio_en', '2026-12-05 09:00');
        $primero = $p->fresh()->proximo_envio_en;

        // Activar una ya activa no mueve el calendario.
        $this->postJson("/api/v1/reportes/programados/{$p->id}/activar")->assertOk();
        $this->assertEquals($primero, $p->fresh()->proximo_envio_en);
    }

    // --- enviar / probar -------------------------------------------------

    public function test_enviar_manda_a_los_destinatarios_sin_mover_el_calendario(): void
    {
        Mail::fake();
        $this->como(['reportes:escribir']);
        $cliente = Cliente::factory()->create();
        $this->reporteListo($cliente);
        $p = $this->programacion($cliente, ['destinatarios' => ['cliente@example.com'], 'proximo_envio_en' => '2026-12-05 09:00:00']);

        $this->postJson("/api/v1/reportes/programados/{$p->id}/enviar")->assertOk()
            ->assertJsonPath('data.ultimo_ok', true)->assertJsonPath('data.proximo_envio_en', '2026-12-05 09:00');

        Mail::assertSent(CorreoPlantillaMail::class, fn ($m) => $m->hasTo('cliente@example.com') && count($m->adjuntos) === 1);
    }

    public function test_enviar_sin_reporte_listo_es_422_y_deja_el_error(): void
    {
        Mail::fake();
        $this->como(['reportes:escribir']);
        $p = $this->programacion();

        $this->postJson("/api/v1/reportes/programados/{$p->id}/enviar")->assertStatus(422)
            ->assertJsonPath('data.ultimo_ok', false)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'listo'));

        Mail::assertNothingSent();
        $this->assertFalse($p->fresh()->ultimo_ok);
        $this->postJson('/api/v1/reportes/programados/999999/enviar')->assertNotFound();
    }

    public function test_enviar_con_otro_envio_en_curso_es_409_y_no_manda_nada(): void
    {
        Mail::fake();
        $this->como(['reportes:escribir']);
        $cliente = Cliente::factory()->create();
        $this->reporteListo($cliente);
        $p = $this->programacion($cliente);

        $lock = \Illuminate\Support\Facades\Cache::lock("repprog:{$p->id}", 600);
        $lock->get();

        $this->postJson("/api/v1/reportes/programados/{$p->id}/enviar")->assertStatus(409);

        $lock->release();
        Mail::assertNothingSent();
        $this->assertSame(0, CorreoEnvio::count());
    }

    public function test_probar_manda_solo_al_dueno_del_token_y_no_toca_el_ultimo_envio(): void
    {
        Mail::fake();
        $this->como(['reportes:escribir'], ['email' => 'yo@rankpro.test']);
        $cliente = Cliente::factory()->create();
        $reporte = $this->reporteListo($cliente);
        $p = $this->programacion($cliente, ['destinatarios' => ['cliente@example.com'], 'ultimo_envio_en' => null]);

        $this->postJson("/api/v1/reportes/programados/{$p->id}/probar")->assertOk();

        Mail::assertSent(CorreoPlantillaMail::class, 1);
        Mail::assertSent(CorreoPlantillaMail::class, fn ($m) => $m->hasTo('yo@rankpro.test') && count($m->adjuntos) === 1);
        Mail::assertNotSent(CorreoPlantillaMail::class, fn ($m) => $m->hasTo('cliente@example.com'));
        $this->assertSame(0, CorreoEnvio::count());
        $p->refresh();
        $this->assertNull($p->ultimo_envio_en);
        $this->assertNull($p->ultimo_ok);
        $this->assertSame(EstadoReporte::Listo, $reporte->fresh()->estado);
        $this->assertStringContainsString('yo@rankpro.test', $p->ultima_prueba);
    }

    public function test_probar_sin_reporte_es_422_y_el_error_queda_guardado(): void
    {
        Mail::fake();
        $this->como(['reportes:escribir']);
        $p = $this->programacion();

        $this->postJson("/api/v1/reportes/programados/{$p->id}/probar")->assertStatus(422)
            ->assertJsonPath('data.ultima_prueba', fn ($v) => str_contains($v, 'ERROR:'));

        Mail::assertNothingSent();
    }
}
