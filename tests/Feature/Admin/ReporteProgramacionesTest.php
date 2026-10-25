<?php

namespace Tests\Feature\Admin;

use App\Enums\AreaReporte;
use App\Enums\EstadoReporte;
use App\Mail\CorreoPlantillaMail;
use App\Models\Cliente;
use App\Models\CorreoAdjunto;
use App\Models\CorreoEnvio;
use App\Models\CorreoPlantilla;
use App\Models\Reporte;
use App\Models\ReporteProgramacion;
use App\Models\User;
use App\Services\Correo\EnviadorCorreo;
use App\Services\Reportes\EnviadorReporteProgramado;
use RuntimeException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Reportes programados: CRUD, cálculo del próximo envío, comando
 * `reportes:enviar-programados` y las pruebas/envíos manuales. Lo que más
 * importa: que el correo salga solo a quien corresponde, con el PDF adjunto, y
 * que cualquier fallo quede guardado en la programación y no solo en un toast.
 */
class ReporteProgramacionesTest extends TestCase
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

    // --- helpers ---------------------------------------------------------

    private function reporteListo(Cliente $cliente, array $overrides = []): Reporte
    {
        return Reporte::create(array_merge([
            'cliente_id' => $cliente->id,
            'area' => AreaReporte::Seo->value,
            'titulo' => 'Reporte de octubre',
            'periodo_inicio' => '2026-10-01',
            'periodo_fin' => '2026-10-31',
            'estado' => EstadoReporte::Listo->value,
            'numero' => null,
        ], $overrides));
    }

    private function programacion(?Cliente $cliente = null, array $overrides = []): ReporteProgramacion
    {
        return ReporteProgramacion::factory()->create(array_merge([
            'cliente_id' => ($cliente ?? Cliente::factory()->create())->id,
        ], $overrides));
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'cliente_id' => Cliente::factory()->create()->id,
            'area' => 'seo',
            'plantilla_id' => CorreoPlantilla::factory()->create()->id,
            'asunto' => 'Reporte de {{mes}}',
            'destinatarios' => "uno@example.com, dos@example.com\ntres@example.com",
            'dia_mes' => 10,
            'hora' => '08:30',
        ], $overrides);
    }

    // --- auth ------------------------------------------------------------

    public function test_invitado_es_redirigido_al_login_en_todas_las_rutas(): void
    {
        $prog = $this->programacion();

        $this->get(route('admin.reportes.programados.index'))->assertRedirect(route('login'));
        $this->post(route('admin.reportes.programados.store'))->assertRedirect(route('login'));
        $this->put(route('admin.reportes.programados.update', $prog))->assertRedirect(route('login'));
        $this->delete(route('admin.reportes.programados.destroy', $prog))->assertRedirect(route('login'));
        $this->post(route('admin.reportes.programados.alternar', $prog))->assertRedirect(route('login'));
        $this->post(route('admin.reportes.programados.probar', $prog))->assertRedirect(route('login'));
        $this->post(route('admin.reportes.programados.enviar-ahora', $prog))->assertRedirect(route('login'));
    }

    // --- CRUD ------------------------------------------------------------

    public function test_el_listado_muestra_el_error_guardado_de_la_ultima_ejecucion(): void
    {
        $this->withoutVite();
        $prog = $this->programacion(Cliente::factory()->create(['empresa' => 'Hotel Visible']), [
            'ultimo_ok' => false,
            'ultimo_resultado' => 'Connection could not be established with host smtp.ejemplo',
            'ultimo_intento_en' => now(),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.reportes.programados.index'))
            ->assertOk()
            ->assertSee('Hotel Visible')
            ->assertSee('Connection could not be established with host smtp.ejemplo')
            ->assertSee('badge--danger', false);
    }

    public function test_la_pantalla_de_reportes_enlaza_a_los_programados(): void
    {
        $this->withoutVite();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.reportes.index'))
            ->assertOk()
            ->assertSee(route('admin.reportes.programados.index'), false);
    }

    public function test_crear_normaliza_destinatarios_y_calcula_el_proximo_envio(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->postJson(route('admin.reportes.programados.store'), $this->payload());

        $response->assertCreated();

        $prog = ReporteProgramacion::firstOrFail();
        $this->assertSame(['uno@example.com', 'dos@example.com', 'tres@example.com'], $prog->destinatarios);
        $this->assertTrue($prog->activo);
        // Hoy 5 nov 10:00 y el día elegido es el 10: este mismo mes.
        $this->assertSame('2026-11-10 08:30', $prog->proximo_envio_en->format('Y-m-d H:i'));
    }

    public function test_crear_valida_dia_hora_y_correos(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.reportes.programados.store'), $this->payload([
                'dia_mes' => 29,
                'hora' => '25:99',
                'destinatarios' => 'esto-no-es-un-correo',
                'plantilla_id' => 99999,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['dia_mes', 'hora', 'destinatarios.0', 'plantilla_id']);

        $this->assertSame(0, ReporteProgramacion::count());
    }

    public function test_editar_recalcula_el_proximo_envio_solo_si_cambia_el_calendario(): void
    {
        $prog = $this->programacion(null, ['dia_mes' => 10, 'hora' => '08:30', 'proximo_envio_en' => '2026-11-10 08:30:00']);
        $usuario = User::factory()->create();

        // Mismo calendario, otro asunto: el próximo envío no se mueve.
        $this->actingAs($usuario)->putJson(route('admin.reportes.programados.update', $prog), $this->payload([
            'cliente_id' => $prog->cliente_id, 'dia_mes' => 10, 'hora' => '08:30', 'asunto' => 'Nuevo asunto',
        ]))->assertOk();
        $prog->refresh();
        $this->assertSame('Nuevo asunto', $prog->asunto);
        $this->assertSame('2026-11-10 08:30', $prog->proximo_envio_en->format('Y-m-d H:i'));

        // Día 3: ya pasó este mes, salta a diciembre.
        $this->actingAs($usuario)->putJson(route('admin.reportes.programados.update', $prog), $this->payload([
            'cliente_id' => $prog->cliente_id, 'dia_mes' => 3, 'hora' => '08:30',
        ]))->assertOk();
        $this->assertSame('2026-12-03 08:30', $prog->fresh()->proximo_envio_en->format('Y-m-d H:i'));
    }

    public function test_pausar_y_reactivar_recalcula_desde_hoy(): void
    {
        $prog = $this->programacion(null, ['dia_mes' => 20, 'hora' => '09:00', 'proximo_envio_en' => '2026-03-20 09:00:00']);
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->postJson(route('admin.reportes.programados.alternar', $prog))->assertOk();
        $this->assertFalse($prog->fresh()->activo);

        $this->actingAs($usuario)->postJson(route('admin.reportes.programados.alternar', $prog))->assertOk();
        $prog->refresh();
        $this->assertTrue($prog->activo);
        // Sin esto, la fecha vieja de marzo dispararía un envío de golpe.
        $this->assertSame('2026-11-20 09:00', $prog->proximo_envio_en->format('Y-m-d H:i'));
    }

    public function test_eliminar_borra_la_programacion(): void
    {
        $prog = $this->programacion();

        $this->actingAs(User::factory()->create())
            ->deleteJson(route('admin.reportes.programados.destroy', $prog))
            ->assertOk();

        $this->assertDatabaseMissing('reporte_programaciones', ['id' => $prog->id]);
    }

    // --- cálculo del próximo envío ----------------------------------------

    public function test_calcular_proximo_cubre_fin_de_mes_dia_28_y_cambio_de_anio(): void
    {
        $f = fn (string $desde, int $dia, string $hora = '09:00') => ReporteProgramacion::calcularProximo($dia, $hora, Carbon::parse($desde))->format('Y-m-d H:i');

        // Desde el 31 de enero con día 28: febrero tiene 28 días y existe.
        $this->assertSame('2026-02-28 09:00', $f('2026-01-31 10:00', 28));
        // Aún no llega el día: este mismo mes.
        $this->assertSame('2026-01-28 09:00', $f('2026-01-10 10:00', 28));
        // Mismo día pero la hora ya pasó: el mes siguiente.
        $this->assertSame('2026-02-05 09:00', $f('2026-01-05 09:00', 5));
        $this->assertSame('2026-01-05 09:00', $f('2026-01-05 08:59', 5));
        // Cambio de año.
        $this->assertSame('2027-01-05 09:00', $f('2026-12-30 10:00', 5));
        // Día 1 desde el último día del mes.
        $this->assertSame('2026-03-01 07:15', $f('2026-02-28 23:00', 1, '07:15'));
    }

    // --- comando -----------------------------------------------------------

    public function test_el_comando_envia_el_pdf_adjunto_solo_a_los_destinatarios_de_la_programacion(): void
    {
        Mail::fake();
        $cliente = Cliente::factory()->create();
        $otro = Cliente::factory()->create(['email' => 'ajeno@example.com']);
        $reporte = $this->reporteListo($cliente);
        $this->reporteListo($otro, ['titulo' => 'De otro cliente']);
        $prog = $this->programacion($cliente, [
            'destinatarios' => ['Cliente@Example.com', 'cliente@example.com', 'segundo@example.com'],
            'proximo_envio_en' => now()->subMinutes(5),
            'dia_mes' => 5,
            'hora' => '09:00',
        ]);

        $this->artisan('reportes:enviar-programados')->assertSuccessful();

        Mail::assertSent(CorreoPlantillaMail::class, 2);
        Mail::assertSent(CorreoPlantillaMail::class, fn ($m) => $m->hasTo('cliente@example.com') && count($m->adjuntos) === 1);
        Mail::assertSent(CorreoPlantillaMail::class, fn ($m) => $m->hasTo('segundo@example.com'));
        Mail::assertNotSent(CorreoPlantillaMail::class, fn ($m) => $m->hasTo('ajeno@example.com'));

        $envio = CorreoEnvio::firstOrFail();
        $this->assertSame(2, $envio->destinatarios()->count());
        $adjunto = CorreoAdjunto::where('envio_id', $envio->id)->firstOrFail();
        $this->assertSame('application/pdf', $adjunto->mime);
        Storage::disk('local')->assertExists($adjunto->ruta);
        $this->assertStringContainsString('REP-SEO-2026-', $adjunto->nombre);

        $prog->refresh();
        $this->assertTrue($prog->ultimo_ok);
        $this->assertSame('ok', $prog->ultimo_resultado);
        $this->assertNotNull($prog->ultimo_envio_en);
        $this->assertSame($envio->id, $prog->ultimo_correo_envio_id);
        $this->assertSame($reporte->id, $prog->ultimo_reporte_id);
        $this->assertSame('2026-12-05 09:00', $prog->proximo_envio_en->format('Y-m-d H:i'));
        $this->assertSame(EstadoReporte::Entregado, $reporte->fresh()->estado);
    }

    public function test_el_asunto_resuelve_las_variables_del_mes_y_del_cliente(): void
    {
        Mail::fake();
        $cliente = Cliente::factory()->create(['empresa' => 'Hotel Prueba']);
        $this->reporteListo($cliente);
        $this->programacion($cliente, ['asunto' => 'Reporte de {{mes}} — {{cliente}}']);

        $this->artisan('reportes:enviar-programados')->assertSuccessful();

        Mail::assertSent(CorreoPlantillaMail::class, fn ($m) => $m->asunto === 'Reporte de octubre 2026 — Hotel Prueba');
    }

    public function test_una_programacion_inactiva_o_futura_no_se_procesa(): void
    {
        Mail::fake();
        $cliente = Cliente::factory()->create();
        $this->reporteListo($cliente);
        $inactiva = $this->programacion($cliente, ['activo' => false]);
        $futura = $this->programacion($cliente, ['proximo_envio_en' => now()->addDay()]);

        $this->artisan('reportes:enviar-programados')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertSame(0, CorreoEnvio::count());
        $this->assertNull($inactiva->fresh()->ultimo_ok);
        $this->assertNull($futura->fresh()->ultimo_ok);
    }

    public function test_el_fallo_de_un_cliente_no_frena_al_siguiente_y_queda_guardado(): void
    {
        Mail::fake();
        Log::spy();

        $roto = Cliente::factory()->create();
        $sano = Cliente::factory()->create();
        $this->reporteListo($roto);
        $this->reporteListo($sano);

        // Plantilla borrada: falla de verdad (no por falta de reporte) y va primero en la cola.
        $conFallo = $this->programacion($roto, ['plantilla_id' => null, 'proximo_envio_en' => now()->subHours(2)]);
        $conExito = $this->programacion($sano, ['destinatarios' => ['sano@example.com'], 'proximo_envio_en' => now()->subHour()]);

        $this->artisan('reportes:enviar-programados')->assertSuccessful();

        $conFallo->refresh();
        $this->assertFalse($conFallo->ultimo_ok);
        $this->assertStringContainsString('plantilla', $conFallo->ultimo_resultado);
        $this->assertNull($conFallo->ultimo_envio_en);
        // Un fallo no repetible no se reintenta cada 15 minutos: el calendario avanza.
        $this->assertTrue($conFallo->proximo_envio_en->isFuture());

        $this->assertTrue($conExito->fresh()->ultimo_ok);
        Mail::assertSent(CorreoPlantillaMail::class, fn ($m) => $m->hasTo('sano@example.com'));
        Mail::assertNotSent(CorreoPlantillaMail::class, fn ($m) => $m->hasTo('cliente@example.com'));
        Log::shouldHaveReceived('error')->atLeast()->once();
    }

    public function test_sin_reporte_listo_queda_el_aviso_y_se_reintenta_sin_avanzar_el_calendario(): void
    {
        Mail::fake();
        $cliente = Cliente::factory()->create();
        // Existe, pero en borrador: no se manda con datos a medias.
        $reporte = $this->reporteListo($cliente, ['estado' => EstadoReporte::Borrador->value]);
        $prog = $this->programacion($cliente, ['proximo_envio_en' => '2026-11-05 09:00:00']);

        $this->artisan('reportes:enviar-programados')->assertSuccessful();

        $prog->refresh();
        $this->assertFalse($prog->ultimo_ok);
        $this->assertStringContainsString('listo', $prog->ultimo_resultado);
        $this->assertStringContainsString('octubre 2026', $prog->ultimo_resultado);
        $this->assertSame('2026-11-05 09:00', $prog->proximo_envio_en->format('Y-m-d H:i'));
        Mail::assertNothingSent();

        // El equipo lo termina: la siguiente corrida lo manda sola.
        $reporte->update(['estado' => EstadoReporte::Listo->value]);
        $this->artisan('reportes:enviar-programados')->assertSuccessful();

        $prog->refresh();
        $this->assertTrue($prog->ultimo_ok);
        $this->assertSame('2026-12-05 09:00', $prog->proximo_envio_en->format('Y-m-d H:i'));
        Mail::assertSent(CorreoPlantillaMail::class, 1);
    }

    public function test_correr_el_comando_dos_veces_no_duplica_el_envio(): void
    {
        Mail::fake();
        $cliente = Cliente::factory()->create();
        $this->reporteListo($cliente);
        $prog = $this->programacion($cliente, ['destinatarios' => ['uno@example.com']]);

        $this->artisan('reportes:enviar-programados')->assertSuccessful();
        // Simula un calendario que volvió a vencer (p. ej. edición del día/hora).
        $prog->refresh()->forceFill(['proximo_envio_en' => now()->subMinute()])->save();
        $this->artisan('reportes:enviar-programados')->assertSuccessful();

        Mail::assertSent(CorreoPlantillaMail::class, 1);
        $this->assertSame(1, CorreoEnvio::count());
    }

    // --- prueba y envío manual ----------------------------------------------

    public function test_probar_manda_solo_al_usuario_en_sesion_y_no_toca_el_ultimo_envio(): void
    {
        Mail::fake();
        $cliente = Cliente::factory()->create();
        $reporte = $this->reporteListo($cliente);
        $prog = $this->programacion($cliente, ['destinatarios' => ['cliente@example.com'], 'ultimo_envio_en' => null]);
        $usuario = User::factory()->create(['email' => 'yo@rankpro.test']);

        $this->actingAs($usuario)
            ->postJson(route('admin.reportes.programados.probar', $prog))
            ->assertOk();

        Mail::assertSent(CorreoPlantillaMail::class, 1);
        Mail::assertSent(CorreoPlantillaMail::class, fn ($m) => $m->hasTo('yo@rankpro.test') && count($m->adjuntos) === 1 && str_starts_with($m->asunto, '[Prueba]'));
        Mail::assertNotSent(CorreoPlantillaMail::class, fn ($m) => $m->hasTo('cliente@example.com'));

        $this->assertSame(0, CorreoEnvio::count());
        $prog->refresh();
        $this->assertNull($prog->ultimo_envio_en);
        $this->assertNull($prog->ultimo_ok);
        $this->assertSame(EstadoReporte::Listo, $reporte->fresh()->estado);
        $this->assertStringContainsString('yo@rankpro.test', $prog->ultima_prueba);
    }

    public function test_si_la_prueba_falla_el_error_queda_guardado_en_la_programacion(): void
    {
        Mail::fake();
        $prog = $this->programacion(); // sin reporte del periodo

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.reportes.programados.probar', $prog))
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'listo'));

        Mail::assertNothingSent();
        $this->assertStringContainsString('ERROR:', $prog->fresh()->ultima_prueba);
    }

    public function test_enviar_ahora_manda_a_los_destinatarios_sin_mover_el_calendario(): void
    {
        Mail::fake();
        $cliente = Cliente::factory()->create();
        $this->reporteListo($cliente);
        $prog = $this->programacion($cliente, [
            'destinatarios' => ['cliente@example.com'],
            'proximo_envio_en' => '2026-12-05 09:00:00',
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.reportes.programados.enviar-ahora', $prog))
            ->assertOk();

        Mail::assertSent(CorreoPlantillaMail::class, fn ($m) => $m->hasTo('cliente@example.com') && count($m->adjuntos) === 1);
        $prog->refresh();
        $this->assertTrue($prog->ultimo_ok);
        $this->assertSame('2026-12-05 09:00', $prog->proximo_envio_en->format('Y-m-d H:i'));
    }

    public function test_enviar_ahora_sin_reporte_responde_422_y_deja_el_error(): void
    {
        Mail::fake();
        $prog = $this->programacion();

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.reportes.programados.enviar-ahora', $prog))
            ->assertStatus(422);

        $prog->refresh();
        $this->assertFalse($prog->ultimo_ok);
        $this->assertNotEmpty($prog->ultimo_resultado);
    }

    // --- concurrencia, estado del cliente y saneado ----------------------

    public function test_con_el_lock_tomado_por_otro_ejecutar_no_envia_y_al_liberarlo_sale_un_solo_correo(): void
    {
        Mail::fake();
        $cliente = Cliente::factory()->create();
        $this->reporteListo($cliente);
        $prog = $this->programacion($cliente, ['destinatarios' => ['uno@example.com']]);
        $enviador = app(EnviadorReporteProgramado::class);

        $otro = Cache::lock("repprog:{$prog->id}", 600);
        $this->assertTrue($otro->get());

        $this->assertFalse($enviador->ejecutar($prog->fresh(), manual: true));
        $this->assertFalse($enviador->ejecutar($prog->fresh(), manual: true));
        $this->assertSame(0, CorreoEnvio::count());

        $otro->release();
        $this->assertTrue($enviador->ejecutar($prog->fresh()));
        // Segundo intento del mismo mes: el duplicado se omite.
        $enviador->ejecutar($prog->fresh());

        $this->assertSame(1, CorreoEnvio::count());
        Mail::assertSent(CorreoPlantillaMail::class, 1);
    }

    public function test_enviar_ahora_con_el_comando_en_curso_responde_409_y_no_envia(): void
    {
        Mail::fake();
        $cliente = Cliente::factory()->create();
        $this->reporteListo($cliente);
        $prog = $this->programacion($cliente);

        // Simula el comando a mitad de envío tomando el mismo lock.
        $comando = Cache::lock("repprog:{$prog->id}", 600);
        $comando->get();

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.reportes.programados.enviar-ahora', $prog))
            ->assertStatus(409)
            ->assertJsonPath('message', EnviadorReporteProgramado::MENSAJE_EN_CURSO);

        $comando->release();
        $this->assertSame(0, CorreoEnvio::count());
        Mail::assertNothingSent();
    }

    public function test_el_comando_omite_una_programacion_con_el_lock_tomado(): void
    {
        Mail::fake();
        $cliente = Cliente::factory()->create();
        $this->reporteListo($cliente);
        $prog = $this->programacion($cliente);
        $original = $prog->proximo_envio_en->format('Y-m-d H:i');

        $lock = Cache::lock("repprog:{$prog->id}", 600);
        $lock->get();

        $this->artisan('reportes:enviar-programados')->assertSuccessful()->expectsOutputToContain('omitida');

        $lock->release();
        $this->assertSame(0, CorreoEnvio::count());
        $this->assertSame($original, $prog->fresh()->proximo_envio_en->format('Y-m-d H:i'));
    }

    public function test_si_el_calendario_cambio_entre_la_lectura_y_el_reclamo_se_omite_el_envio(): void
    {
        Mail::fake();
        $cliente = Cliente::factory()->create();
        $this->reporteListo($cliente);
        $prog = $this->programacion($cliente, ['proximo_envio_en' => '2026-11-05 09:00:00']);

        // Otro proceso ya reclamó la fila después de que esta copia se leyó.
        ReporteProgramacion::whereKey($prog->id)->update(['proximo_envio_en' => '2026-12-05 09:00:00']);

        $this->assertFalse(app(EnviadorReporteProgramado::class)->ejecutar($prog));

        $this->assertSame(0, CorreoEnvio::count());
        Mail::assertNothingSent();
        $this->assertSame('2026-12-05 09:00', $prog->fresh()->proximo_envio_en->format('Y-m-d H:i'));
    }

    public function test_el_calendario_se_adelanta_antes_de_enviar_y_el_no_listo_no_lo_mueve(): void
    {
        Mail::fake();
        $cliente = Cliente::factory()->create();
        $prog = $this->programacion($cliente, ['proximo_envio_en' => '2026-11-05 09:00:00']);

        // Sin reporte listo no hay reclamo: el calendario queda intacto.
        app(EnviadorReporteProgramado::class)->ejecutar($prog->fresh());
        $this->assertSame('2026-11-05 09:00', $prog->fresh()->proximo_envio_en->format('Y-m-d H:i'));
        $this->assertFalse($prog->fresh()->ultimo_ok);

        // Con el SMTP muriendo a mitad de envío, el calendario ya estaba adelantado.
        $this->reporteListo($cliente);
        $this->mock(EnviadorCorreo::class)->shouldReceive('enviar')->once()->andReturnUsing(function () use ($prog) {
            $this->assertSame('2026-12-05 09:00', $prog->fresh()->proximo_envio_en->format('Y-m-d H:i'));
            throw new RuntimeException('muere el SMTP');
        });
        app(EnviadorReporteProgramado::class)->ejecutar($prog->fresh());

        $this->assertSame('2026-12-05 09:00', $prog->fresh()->proximo_envio_en->format('Y-m-d H:i'));
    }

    public function test_un_cliente_pausado_cancelado_o_eliminado_no_se_procesa(): void
    {
        Mail::fake();
        $pausado = Cliente::factory()->create(['estado' => 'pausado']);
        $cancelado = Cliente::factory()->create(['estado' => 'cancelado']);
        $eliminado = Cliente::factory()->create();
        $activo = Cliente::factory()->create();
        foreach ([$pausado, $cancelado, $eliminado, $activo] as $c) {
            $this->reporteListo($c);
        }
        $progs = collect([$pausado, $cancelado, $eliminado])->map(fn ($c) => $this->programacion($c));
        $this->programacion($activo, ['destinatarios' => ['activo@example.com']]);
        $eliminado->delete();

        $this->artisan('reportes:enviar-programados')->assertSuccessful();

        $this->assertSame(1, CorreoEnvio::count());
        Mail::assertSent(CorreoPlantillaMail::class, 1);
        Mail::assertSent(CorreoPlantillaMail::class, fn ($m) => $m->hasTo('activo@example.com'));
        $progs->each(fn ($p) => $this->assertNull($p->fresh()->ultimo_ok));

        // "Enviar ahora" tampoco envía: error claro.
        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.reportes.programados.enviar-ahora', $progs[0]))
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'no está activo'));
        $this->postJson(route('admin.reportes.programados.enviar-ahora', $progs[2]))->assertStatus(422);
        $this->assertSame(1, CorreoEnvio::count());
    }

    public function test_el_error_guardado_va_saneado_sin_rutas_ni_sql_y_conserva_el_motivo(): void
    {
        $ruta = base_path('app/Services/Reportes/X.php');
        $crudo = "Connection could not be established with host mailpit:1025 in {$ruta} on line 12 "
            .'at /var/www/html/vendor/x/y.php and C:\\laragon\\www\\z.php (SQL: insert into clientes values (\'secreto\'))';

        $limpio = EnviadorReporteProgramado::sanear($crudo);

        $this->assertStringContainsString('Connection could not be established with host mailpit:1025', $limpio);
        $this->assertStringNotContainsString(base_path(), $limpio);
        $this->assertStringNotContainsString('/var/www', $limpio);
        $this->assertStringNotContainsString('laragon', $limpio);
        $this->assertStringNotContainsString('secreto', $limpio);
        $this->assertStringNotContainsString('SQL', $limpio);
        $this->assertLessThanOrEqual(300, mb_strlen(EnviadorReporteProgramado::sanear(str_repeat('a ', 500))));

        // De punta a punta: lo que queda en la fila (y sale por la API) ya va saneado.
        $cliente = Cliente::factory()->create();
        $this->reporteListo($cliente);
        $prog = $this->programacion($cliente);
        $this->mock(EnviadorCorreo::class)->shouldReceive('enviar')->andThrow(new RuntimeException($crudo));

        app(EnviadorReporteProgramado::class)->ejecutar($prog);

        $resultado = $prog->fresh()->ultimo_resultado;
        $this->assertStringContainsString('mailpit:1025', $resultado);
        $this->assertStringNotContainsString(base_path(), $resultado);
        $this->assertStringNotContainsString('secreto', $resultado);
    }
}
