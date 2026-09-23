<?php

namespace Tests\Feature\Admin;

use App\Enums\EstadoReunion;
use App\Models\AgendaConfiguracion;
use App\Models\DisponibilidadBloqueo;
use App\Models\DisponibilidadHorario;
use App\Models\Reunion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panel admin de la agenda: configuración general, horario semanal,
 * bloqueos puntuales y cancelar reuniones.
 */
class AgendaTest extends TestCase
{
    use RefreshDatabase;

    private function payloadConfiguracion(array $overrides = []): array
    {
        return array_merge([
            'activa' => '1',
            'duracion_minutos' => 45,
            'anticipacion_minima_horas' => 6,
            'dias_visibles' => 20,
            'notificar_email' => 'agencia@example.com',
        ], $overrides);
    }

    private function payloadDias(): array
    {
        $dias = [];
        for ($dia = 0; $dia <= 6; $dia++) {
            $dias[] = [
                'dia_semana' => $dia,
                'hora_inicio' => '09:00',
                'hora_fin' => '18:00',
                'activo' => in_array($dia, [1, 2, 3, 4, 5]) ? '1' : '0',
            ];
        }

        return ['dias' => $dias];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.agenda.index'))->assertRedirect(route('login'));
    }

    public function test_index_responde_200_autenticado(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.agenda.index'))
            ->assertOk();
    }

    public function test_actualizar_configuracion_guarda_la_fila_unica(): void
    {
        $this->actingAs(User::factory()->create())
            ->put(route('admin.agenda.configuracion.update'), $this->payloadConfiguracion())
            ->assertRedirect(route('admin.agenda.index'));

        $config = AgendaConfiguracion::actual();
        $this->assertNotNull($config);
        $this->assertSame(1, $config->id);
        $this->assertTrue($config->activa);
        $this->assertSame(45, $config->duracion_minutos);
        $this->assertSame(6, $config->anticipacion_minima_horas);
        $this->assertSame(20, $config->dias_visibles);
        $this->assertSame('agencia@example.com', $config->notificar_email);
    }

    /** Un checkbox sin marcar no manda el campo: guardar así debe apagar, no dejar activa como estaba. */
    public function test_actualizar_configuracion_sin_activa_marcada_la_desactiva(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put(route('admin.agenda.configuracion.update'), $this->payloadConfiguracion());
        $this->assertTrue(AgendaConfiguracion::actual()->activa);

        $payload = $this->payloadConfiguracion();
        unset($payload['activa']);
        $this->actingAs($user)->put(route('admin.agenda.configuracion.update'), $payload);

        $this->assertFalse(AgendaConfiguracion::actual()->activa);
    }

    public function test_actualizar_horarios_reemplaza_las_7_filas(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.agenda.horarios.update'), $this->payloadDias())
            ->assertRedirect(route('admin.agenda.index'));

        $this->assertSame(7, DisponibilidadHorario::count());
        $this->assertSame(5, DisponibilidadHorario::where('activo', true)->count());

        // Guardar de nuevo reemplaza, no acumula.
        $this->actingAs($user)->put(route('admin.agenda.horarios.update'), $this->payloadDias());
        $this->assertSame(7, DisponibilidadHorario::count());
    }

    /**
     * Bug real reportado por el usuario en producción: el <input type="time">
     * de un día que se deja inactivo llega vacío ("", no ausente) — sin la
     * normalización a null antes de validar, "required"/"date_format" lo
     * rechazaban aunque ese día nunca fuera a usarse, y el guardado completo
     * fallaba (ni siquiera los días sí llenados se guardaban).
     */
    public function test_actualizar_horarios_permite_dejar_vacios_los_dias_inactivos(): void
    {
        $dias = [];
        for ($dia = 0; $dia <= 6; $dia++) {
            $activo = in_array($dia, [1, 2, 3, 4, 5], true);
            $dias[] = [
                'dia_semana' => $dia,
                'hora_inicio' => $activo ? '09:00' : '',
                'hora_fin' => $activo ? '18:00' : '',
                'activo' => $activo ? '1' : '0',
            ];
        }

        $this->actingAs(User::factory()->create())
            ->put(route('admin.agenda.horarios.update'), ['dias' => $dias])
            ->assertRedirect(route('admin.agenda.index'))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(7, DisponibilidadHorario::count());
        $this->assertSame(5, DisponibilidadHorario::where('activo', true)->count());

        $lunes = DisponibilidadHorario::where('dia_semana', 1)->first();
        $this->assertSame('09:00:00', $lunes->hora_inicio);
    }

    /** Un día activo SÍ sigue exigiendo horario: el vacío de arriba solo se permite si quedó inactivo. */
    public function test_actualizar_horarios_exige_horario_en_un_dia_activo(): void
    {
        $dias = [];
        for ($dia = 0; $dia <= 6; $dia++) {
            $dias[] = ['dia_semana' => $dia, 'hora_inicio' => '', 'hora_fin' => '', 'activo' => $dia === 1 ? '1' : '0'];
        }

        $this->actingAs(User::factory()->create())
            ->put(route('admin.agenda.horarios.update'), ['dias' => $dias])
            ->assertSessionHasErrors(['dias.1.hora_inicio', 'dias.1.hora_fin']);
    }

    public function test_store_y_destroy_bloqueo(): void
    {
        $user = User::factory()->create();
        $fecha = today()->addDays(3)->toDateString();

        $this->actingAs($user)
            ->post(route('admin.agenda.bloqueos.store'), ['fecha' => $fecha, 'motivo' => 'Vacaciones'])
            ->assertRedirect(route('admin.agenda.index'));

        $bloqueo = DisponibilidadBloqueo::where('fecha', $fecha)->firstOrFail();
        $this->assertSame('Vacaciones', $bloqueo->motivo);

        // Bloquear la misma fecha otra vez no es un error.
        $this->actingAs($user)
            ->post(route('admin.agenda.bloqueos.store'), ['fecha' => $fecha, 'motivo' => 'Otro motivo'])
            ->assertRedirect(route('admin.agenda.index'));
        $this->assertSame(1, DisponibilidadBloqueo::count());

        $this->actingAs($user)
            ->delete(route('admin.agenda.bloqueos.destroy', $bloqueo))
            ->assertRedirect(route('admin.agenda.index'));

        $this->assertDatabaseMissing('disponibilidad_bloqueos', ['id' => $bloqueo->id]);
    }

    public function test_cancelar_reunion_cambia_el_estado(): void
    {
        $reunion = Reunion::factory()->create(['estado' => EstadoReunion::Confirmada]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.agenda.reuniones.cancelar', $reunion))
            ->assertRedirect(route('admin.agenda.index'));

        $this->assertSame(EstadoReunion::Cancelada, $reunion->fresh()->estado);
    }

    /**
     * Deja la agenda lista para agendar: activa, lunes a viernes 9-18.
     *
     * forceFill()+save(), no create(): 'id' no está en $fillable, así que
     * create(['id' => 1, ...]) lo ignora en silencio y la fila cae en el
     * siguiente autoincrement — que RefreshDatabase NO resetea entre tests
     * (es una transacción que hace rollback, pero el contador de MySQL no
     * es transaccional). El segundo test en adelante nunca vuelve a crear el
     * id=1 que AgendaConfiguracion::actual() busca, y todo lo que dependa de
     * la configuración falla en silencio como si nunca se hubiera guardado.
     */
    private function activarAgenda(): void
    {
        (new AgendaConfiguracion())->forceFill([
            'id' => 1, 'activa' => true, 'duracion_minutos' => 30,
            'anticipacion_minima_horas' => 1, 'dias_visibles' => 30,
        ])->save();
        foreach ([1, 2, 3, 4, 5] as $dia) {
            DisponibilidadHorario::create(['dia_semana' => $dia, 'hora_inicio' => '09:00', 'hora_fin' => '18:00', 'activo' => true]);
        }
    }

    /** Próximo lunes a las 10:00, sea cual sea "hoy" al correr el test. */
    private function proximoLunesDiez(): \Carbon\Carbon
    {
        return now()->next(\Carbon\Carbon::MONDAY)->setTime(10, 0);
    }

    public function test_store_crea_una_cita_manual_valida(): void
    {
        $this->activarAgenda();
        $inicio = $this->proximoLunesDiez();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.agenda.reuniones.store'), [
                'nombre' => 'Llamada telefónica',
                'email' => 'telefono@example.com',
                'inicia_en' => $inicio->format('Y-m-d H:i'),
            ])
            ->assertRedirect(route('admin.agenda.index'));

        $this->assertDatabaseHas('reuniones', [
            'email' => 'telefono@example.com',
            'estado' => 'confirmada',
        ]);
    }

    public function test_store_rechaza_un_horario_ya_ocupado(): void
    {
        $this->activarAgenda();
        $inicio = $this->proximoLunesDiez();
        Reunion::factory()->create(['inicia_en' => $inicio, 'termina_en' => $inicio->copy()->addMinutes(30), 'estado' => EstadoReunion::Confirmada]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.agenda.reuniones.store'), [
                'nombre' => 'Otra persona',
                'email' => 'otra@example.com',
                'inicia_en' => $inicio->format('Y-m-d H:i'),
            ])
            ->assertSessionHasErrors('inicia_en');
    }

    public function test_reagendar_mueve_la_reunion_a_otro_horario(): void
    {
        $this->activarAgenda();
        $inicio = $this->proximoLunesDiez();
        $reunion = Reunion::factory()->create(['inicia_en' => $inicio, 'termina_en' => $inicio->copy()->addMinutes(30), 'estado' => EstadoReunion::Confirmada]);
        $nuevoInicio = $inicio->copy()->addHour();

        $this->actingAs(User::factory()->create())
            ->put(route('admin.agenda.reuniones.reagendar', $reunion), [
                'fecha' => $nuevoInicio->toDateString(),
                'hora' => $nuevoInicio->format('H:i'),
            ])
            ->assertRedirect(route('admin.agenda.index'));

        $reunion->refresh();
        $this->assertSame($nuevoInicio->format('Y-m-d H:i'), $reunion->inicia_en->format('Y-m-d H:i'));
    }

    public function test_reagendar_no_choca_contra_su_propio_horario_actual(): void
    {
        $this->activarAgenda();
        $inicio = $this->proximoLunesDiez();
        $reunion = Reunion::factory()->create(['inicia_en' => $inicio, 'termina_en' => $inicio->copy()->addMinutes(30), 'estado' => EstadoReunion::Confirmada]);

        // "Reagendar" al mismo horario que ya tiene debe seguir funcionando.
        $this->actingAs(User::factory()->create())
            ->put(route('admin.agenda.reuniones.reagendar', $reunion), [
                'fecha' => $inicio->toDateString(),
                'hora' => $inicio->format('H:i'),
            ])
            ->assertRedirect(route('admin.agenda.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('reuniones', ['id' => $reunion->id, 'estado' => 'cancelada']);
    }

    public function test_actualizar_estado_cambia_a_completada(): void
    {
        $reunion = Reunion::factory()->create(['estado' => EstadoReunion::Confirmada]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.agenda.reuniones.estado', $reunion), ['estado' => 'completada'])
            ->assertRedirect(route('admin.agenda.index'));

        $this->assertSame(EstadoReunion::Completada, $reunion->fresh()->estado);
    }

    public function test_actualizar_estado_rechaza_cancelada(): void
    {
        $reunion = Reunion::factory()->create(['estado' => EstadoReunion::Confirmada]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.agenda.reuniones.estado', $reunion), ['estado' => 'cancelada'])
            ->assertSessionHasErrors('estado');
    }
}
