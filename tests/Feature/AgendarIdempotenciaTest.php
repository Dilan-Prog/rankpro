<?php

namespace Tests\Feature;

use App\Enums\EstadoReunion;
use App\Mail\ReunionConfirmadaMail;
use App\Mail\ReunionNuevaMail;
use App\Models\AgendaConfiguracion;
use App\Models\DisponibilidadHorario;
use App\Models\Reunion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Reglas de idempotencia de POST /agendar y de la cancelación pública: doble
 * clic o reintento no crean dos reuniones ni mandan dos veces los correos.
 */
class AgendarIdempotenciaTest extends TestCase
{
    use RefreshDatabase;

    private function prepararAgenda(): \Carbon\Carbon
    {
        Mail::fake();

        $config = new AgendaConfiguracion();
        $config->forceFill([
            'id' => 1,
            'activa' => true,
            'duracion_minutos' => 60,
            'anticipacion_minima_horas' => 0,
            'dias_visibles' => 15,
        ])->save();

        $lunes = today()->next(\Carbon\Carbon::MONDAY);

        DisponibilidadHorario::create([
            'dia_semana' => $lunes->dayOfWeek,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '13:00:00',
            'activo' => true,
        ]);

        return $lunes;
    }

    private function payload($lunes, array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Prospecto de Prueba',
            'email' => 'prospecto@example.com',
            'telefono' => '5512345678',
            'notas' => null,
            'inicia_en' => $lunes->format('Y-m-d').' 10:00',
        ], $overrides);
    }

    public function test_el_formulario_trae_una_clave_uuid_oculta_distinta_en_cada_carga(): void
    {
        $this->prepararAgenda();

        $html1 = $this->get(route('agendar.mostrar'))->getContent();
        $html2 = $this->get(route('agendar.mostrar'))->getContent();

        $patron = '/name="clave_idempotencia" value="([0-9a-f-]{36})"/';
        $this->assertSame(1, preg_match($patron, $html1, $m1));
        $this->assertSame(1, preg_match($patron, $html2, $m2));
        $this->assertTrue(Str::isUuid($m1[1]));
        $this->assertNotSame($m1[1], $m2[1]);
    }

    public function test_el_mismo_post_dos_veces_crea_una_reunion_y_un_solo_juego_de_correos(): void
    {
        $lunes = $this->prepararAgenda();
        $payload = $this->payload($lunes, ['clave_idempotencia' => (string) Str::uuid()]);

        $primera = $this->postJson(route('agendar.agendar'), $payload);
        $segunda = $this->postJson(route('agendar.agendar'), $payload);

        $primera->assertOk()->assertJson(['ok' => true]);
        $segunda->assertOk()->assertJson(['ok' => true]);
        // Misma respuesta salvo `creada`: solo la primera creó la reunión (el sitio dispara la conversión una vez).
        $this->assertTrue($primera->json('creada'));
        $this->assertFalse($segunda->json('creada'));
        $this->assertSame(
            collect($primera->json())->except('creada')->all(),
            collect($segunda->json())->except('creada')->all()
        );

        $this->assertSame(1, Reunion::count());
        Mail::assertSent(ReunionConfirmadaMail::class, 1);
        Mail::assertSent(ReunionNuevaMail::class, 1);
    }

    public function test_misma_clave_con_la_reunion_ya_creada_por_otra_peticion_no_crea_ni_manda_nada(): void
    {
        // Carrera simulada: la "otra" petición ya confirmó su INSERT con esta clave.
        $lunes = $this->prepararAgenda();
        $clave = (string) Str::uuid();

        $ganadora = Reunion::create([
            'nombre' => 'Prospecto de Prueba',
            'email' => 'otro-correo@example.com',
            'inicia_en' => $lunes->copy()->setTime(11, 0),
            'termina_en' => $lunes->copy()->setTime(12, 0),
            'estado' => EstadoReunion::Confirmada,
            'clave_idempotencia' => $clave,
        ]);

        $response = $this->postJson(route('agendar.agendar'), $this->payload($lunes, ['clave_idempotencia' => $clave]));

        $response->assertOk()->assertJson([
            'ok' => true,
            'cancelar_url' => route('agendar.cancelar', $ganadora->token),
        ]);
        $this->assertSame(1, Reunion::count());
        Mail::assertNothingSent();
    }

    public function test_la_bd_rechaza_dos_reuniones_con_la_misma_clave(): void
    {
        $lunes = $this->prepararAgenda();
        $clave = (string) Str::uuid();
        $datos = [
            'nombre' => 'A', 'email' => 'a@example.com',
            'inicia_en' => $lunes->copy()->setTime(9, 0), 'termina_en' => $lunes->copy()->setTime(10, 0),
            'clave_idempotencia' => $clave,
        ];

        Reunion::create($datos);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        Reunion::create(array_merge($datos, ['email' => 'b@example.com']));
    }

    public function test_misma_persona_y_mismo_horario_sin_clave_devuelve_la_reunion_existente(): void
    {
        $lunes = $this->prepararAgenda();

        $this->postJson(route('agendar.agendar'), $this->payload($lunes))->assertOk();
        $segunda = $this->postJson(route('agendar.agendar'), $this->payload($lunes, ['email' => 'PROSPECTO@Example.com']));

        $segunda->assertOk()->assertJson(['ok' => true]);
        $this->assertSame(1, Reunion::count());
        Mail::assertSent(ReunionConfirmadaMail::class, 1);
        Mail::assertSent(ReunionNuevaMail::class, 1);
    }

    public function test_otra_persona_en_el_mismo_horario_sigue_recibiendo_el_choque(): void
    {
        $lunes = $this->prepararAgenda();

        $this->postJson(route('agendar.agendar'), $this->payload($lunes))->assertOk();
        $this->postJson(route('agendar.agendar'), $this->payload($lunes, ['email' => 'otra@example.com']))
            ->assertStatus(422)
            ->assertJson(['message' => 'Ese horario ya no está disponible, elige otro.']);

        $this->assertSame(1, Reunion::count());
    }

    public function test_la_misma_persona_puede_reservar_de_nuevo_un_horario_que_cancelo(): void
    {
        $lunes = $this->prepararAgenda();

        $this->postJson(route('agendar.agendar'), $this->payload($lunes))->assertOk();
        $this->post(route('agendar.cancelar.confirmar', Reunion::firstOrFail()->token));

        $this->postJson(route('agendar.agendar'), $this->payload($lunes))->assertOk();

        $this->assertSame(2, Reunion::count());
        $this->assertSame(1, Reunion::where('estado', EstadoReunion::Confirmada->value)->count());
    }

    public function test_claves_distintas_en_horarios_distintos_crean_reuniones_distintas(): void
    {
        $lunes = $this->prepararAgenda();

        $this->postJson(route('agendar.agendar'), $this->payload($lunes, ['clave_idempotencia' => (string) Str::uuid()]))->assertOk();
        $this->postJson(route('agendar.agendar'), $this->payload($lunes, [
            'clave_idempotencia' => (string) Str::uuid(),
            'inicia_en' => $lunes->format('Y-m-d').' 11:00',
        ]))->assertOk();

        $this->assertSame(2, Reunion::count());
        Mail::assertSent(ReunionConfirmadaMail::class, 2);
    }

    public function test_una_clave_mal_formada_responde_422_y_no_crea_nada(): void
    {
        $lunes = $this->prepararAgenda();

        $this->postJson(route('agendar.agendar'), $this->payload($lunes, ['clave_idempotencia' => 'no-es-un-uuid']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('clave_idempotencia');

        $this->assertSame(0, Reunion::count());
        Mail::assertNothingSent();
    }

    public function test_sin_clave_se_comporta_como_antes(): void
    {
        $lunes = $this->prepararAgenda();

        $this->postJson(route('agendar.agendar'), $this->payload($lunes))->assertOk();

        $this->assertNull(Reunion::firstOrFail()->clave_idempotencia);
    }

    public function test_reintentar_con_la_clave_de_una_reunion_cancelada_no_la_resucita(): void
    {
        $lunes = $this->prepararAgenda();
        $payload = $this->payload($lunes, ['clave_idempotencia' => (string) Str::uuid()]);

        $this->postJson(route('agendar.agendar'), $payload)->assertOk();
        $this->post(route('agendar.cancelar.confirmar', Reunion::firstOrFail()->token));

        $this->postJson(route('agendar.agendar'), $payload)->assertStatus(422);

        $this->assertSame(EstadoReunion::Cancelada, Reunion::firstOrFail()->estado);
        $this->assertSame(1, Reunion::count());
    }

    public function test_cancelar_dos_veces_da_el_mismo_resultado_sin_tocar_la_reunion_ni_mandar_correos(): void
    {
        $lunes = $this->prepararAgenda();
        $this->postJson(route('agendar.agendar'), $this->payload($lunes))->assertOk();
        $reunion = Reunion::firstOrFail();
        Mail::fake(); // descarta los correos de la reserva: la cancelación no debe mandar ninguno

        $this->post(route('agendar.cancelar.confirmar', $reunion->token))
            ->assertRedirect(route('agendar.cancelar', $reunion->token));
        $canceladaEn = $reunion->fresh()->updated_at;

        $this->travel(5)->minutes();

        $this->post(route('agendar.cancelar.confirmar', $reunion->token))
            ->assertRedirect(route('agendar.cancelar', $reunion->token))
            ->assertSessionHas('status', 'Reunión cancelada.');

        $this->assertEquals($canceladaEn, $reunion->fresh()->updated_at);
        $this->assertSame(EstadoReunion::Cancelada, $reunion->fresh()->estado);
        Mail::assertNothingSent();
    }

    public function test_cancelar_con_token_inexistente_no_cambia_nada(): void
    {
        $this->post(route('agendar.cancelar.confirmar', 'token-que-no-existe'))
            ->assertRedirect(route('agendar.mostrar'))
            ->assertSessionHas('status', 'Ese enlace ya no es válido.');
    }
}
