<?php

namespace Tests\Feature;

use App\Enums\EstadoReunion;
use App\Models\AgendaConfiguracion;
use App\Models\DisponibilidadHorario;
use App\Models\Reunion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Endurecimiento de /agendar tras la auditoría: fuga del token de cancelación,
 * límite de peticiones, honeypot, validación de servidor y rejilla/ventana de
 * horarios, más las revisiones equivalentes del panel de agenda.
 */
class AgendarSeguridadTest extends TestCase
{
    use RefreshDatabase;

    private function prepararAgenda(int $diasVisibles = 15): Carbon
    {
        Mail::fake();

        $config = new AgendaConfiguracion();
        $config->forceFill([
            'id' => 1, 'activa' => true, 'duracion_minutos' => 60,
            'anticipacion_minima_horas' => 0, 'dias_visibles' => $diasVisibles,
        ])->save();

        // Todos los días activos de 09 a 13: así las pruebas no dependen del día de la semana.
        for ($dia = 0; $dia <= 6; $dia++) {
            DisponibilidadHorario::create(['dia_semana' => $dia, 'hora_inicio' => '09:00:00', 'hora_fin' => '13:00:00', 'activo' => true]);
        }

        return today()->addDays(2);
    }

    private function payload(Carbon $dia, array $extra = []): array
    {
        return array_merge([
            'nombre' => 'Prospecto', 'email' => 'prospecto@example.com', 'telefono' => '5512345678',
            'inicia_en' => $dia->format('Y-m-d').' 10:00',
        ], $extra);
    }

    public function test_la_regla_natural_no_entrega_el_enlace_de_cancelacion(): void
    {
        $dia = $this->prepararAgenda();
        $this->postJson(route('agendar.agendar'), $this->payload($dia, ['clave_idempotencia' => (string) Str::uuid()]))->assertOk();

        // Un tercero que solo conoce correo + horario (sin la clave).
        $r = $this->postJson(route('agendar.agendar'), $this->payload($dia));

        $r->assertOk()->assertJson(['ok' => true]);
        $this->assertArrayNotHasKey('cancelar_url', $r->json());
        $this->assertStringNotContainsString(Reunion::firstOrFail()->token, $r->getContent());
        $this->assertSame(1, Reunion::count());
    }

    public function test_con_la_clave_correcta_si_se_devuelve_el_enlace(): void
    {
        $dia = $this->prepararAgenda();
        $payload = $this->payload($dia, ['clave_idempotencia' => (string) Str::uuid()]);

        $this->postJson(route('agendar.agendar'), $payload)->assertOk()->assertJsonStructure(['cancelar_url']);
        $this->postJson(route('agendar.agendar'), $payload)->assertOk()->assertJsonStructure(['cancelar_url']);
    }

    public function test_el_post_se_limita_a_6_por_minuto_por_ip(): void
    {
        $dia = $this->prepararAgenda();

        for ($i = 0; $i < 6; $i++) {
            $this->postJson(route('agendar.agendar'), $this->payload($dia, ['sitio_alterno' => 'x']))->assertOk();
        }
        $this->postJson(route('agendar.agendar'), $this->payload($dia, ['sitio_alterno' => 'x']))->assertStatus(429);
    }

    public function test_consultar_disponibilidad_conserva_el_limite_holgado(): void
    {
        $dia = $this->prepararAgenda();

        for ($i = 0; $i < 10; $i++) {
            $this->getJson(route('agendar.disponibilidad', ['fecha' => $dia->format('Y-m-d')]))->assertOk();
        }
    }

    public function test_el_honeypot_lleno_responde_exito_falso_sin_crear_ni_mandar_nada(): void
    {
        $dia = $this->prepararAgenda();

        $r = $this->postJson(route('agendar.agendar'), $this->payload($dia, ['sitio_alterno' => 'http://spam.example']));

        $r->assertOk()->assertJson(['ok' => true]);
        $this->assertArrayNotHasKey('cancelar_url', $r->json());
        $this->assertSame(0, Reunion::count());
        Mail::assertNothingSent();
    }

    public function test_el_telefono_es_obligatorio_y_pide_8_digitos(): void
    {
        $dia = $this->prepararAgenda();

        $this->postJson(route('agendar.agendar'), $this->payload($dia, ['telefono' => null]))->assertStatus(422)->assertJsonValidationErrors('telefono');
        $this->postJson(route('agendar.agendar'), $this->payload($dia, ['telefono' => '12-34']))->assertStatus(422)->assertJsonValidationErrors('telefono');
        $this->postJson(route('agendar.agendar'), $this->payload($dia, ['telefono' => '+52 55 1234 5678']))->assertOk();

        $this->assertSame(1, Reunion::count());
    }

    public function test_la_empresa_se_valida_y_se_guarda(): void
    {
        $dia = $this->prepararAgenda();

        $this->postJson(route('agendar.agendar'), $this->payload($dia, ['empresa' => str_repeat('a', 256)]))
            ->assertStatus(422)->assertJsonValidationErrors('empresa');

        $this->postJson(route('agendar.agendar'), $this->payload($dia, ['empresa' => 'miempresa.com']))->assertOk();
        $this->assertSame('miempresa.com', Reunion::firstOrFail()->empresa);
    }

    public function test_el_formulario_trae_autocomplete_y_el_honeypot(): void
    {
        $this->prepararAgenda();

        $this->get(route('agendar.mostrar'))->assertOk()
            ->assertSee('autocomplete="name"', false)
            ->assertSee('autocomplete="email"', false)
            ->assertSee('autocomplete="tel"', false)
            ->assertSee('autocomplete="organization"', false)
            ->assertSee('name="sitio_alterno"', false);
    }

    public function test_rechaza_un_horario_fuera_de_la_rejilla(): void
    {
        $dia = $this->prepararAgenda();

        $this->postJson(route('agendar.agendar'), $this->payload($dia, ['inicia_en' => $dia->format('Y-m-d').' 10:07']))
            ->assertStatus(422)->assertJson(['message' => 'Ese horario ya no está disponible, elige otro.']);

        $this->assertSame(0, Reunion::count());
    }

    public function test_rechaza_una_fecha_mas_alla_de_dias_visibles(): void
    {
        $this->prepararAgenda(diasVisibles: 15);

        $this->postJson(route('agendar.agendar'), $this->payload(today()->addDays(365)))->assertStatus(422);
        $this->postJson(route('agendar.agendar'), $this->payload(today()->addDays(15)))->assertOk();

        $this->assertSame(1, Reunion::count());
    }

    public function test_reservar_toma_un_bloqueo_de_fila_para_serializar_reservas(): void
    {
        $dia = $this->prepararAgenda();
        DB::enableQueryLog();

        $this->postJson(route('agendar.agendar'), $this->payload($dia))->assertOk();

        $consultas = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        $this->assertStringContainsStringIgnoringCase('for update', $consultas);
    }

    public function test_dos_prospectos_distintos_no_toman_el_mismo_hueco(): void
    {
        $dia = $this->prepararAgenda();

        $this->postJson(route('agendar.agendar'), $this->payload($dia))->assertOk();
        $this->postJson(route('agendar.agendar'), $this->payload($dia, ['email' => 'otro@example.com']))->assertStatus(422);

        $this->assertSame(1, Reunion::count());
    }

    // --- panel admin ---------------------------------------------------------

    public function test_admin_no_reactiva_una_cita_cancelada_si_otra_ocupo_su_horario(): void
    {
        $dia = $this->prepararAgenda();
        $base = ['nombre' => 'X', 'inicia_en' => $dia->copy()->setTime(10, 0), 'termina_en' => $dia->copy()->setTime(11, 0)];
        $cancelada = Reunion::create($base + ['email' => 'a@example.com', 'estado' => EstadoReunion::Cancelada]);
        Reunion::create($base + ['email' => 'b@example.com', 'estado' => EstadoReunion::Confirmada]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.agenda.reuniones.estado', $cancelada), ['estado' => 'confirmada'])
            ->assertSessionHasErrors('estado');

        $this->assertSame(EstadoReunion::Cancelada, $cancelada->fresh()->estado);
    }

    public function test_admin_reactiva_una_cita_cancelada_si_el_horario_sigue_libre(): void
    {
        $dia = $this->prepararAgenda();
        $cancelada = Reunion::create([
            'nombre' => 'X', 'email' => 'a@example.com', 'estado' => EstadoReunion::Cancelada,
            'inicia_en' => $dia->copy()->setTime(10, 0), 'termina_en' => $dia->copy()->setTime(11, 0),
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.agenda.reuniones.estado', $cancelada), ['estado' => 'confirmada'])
            ->assertSessionHasNoErrors();

        $this->assertSame(EstadoReunion::Confirmada, $cancelada->fresh()->estado);
    }

    public function test_admin_rechaza_dias_de_semana_repetidos_en_el_horario(): void
    {
        $dias = [];
        for ($i = 0; $i < 7; $i++) {
            $dias[] = ['dia_semana' => $i === 6 ? 0 : $i, 'hora_inicio' => '09:00', 'hora_fin' => '18:00', 'activo' => '1'];
        }

        $this->actingAs(User::factory()->create())
            ->put(route('admin.agenda.horarios.update'), ['dias' => $dias])
            ->assertSessionHasErrors();
    }
}
