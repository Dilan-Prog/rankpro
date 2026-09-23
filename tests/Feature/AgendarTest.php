<?php

namespace Tests\Feature;

use App\Enums\EstadoReunion;
use App\Mail\ReunionConfirmadaMail;
use App\Mail\ReunionNuevaMail;
use App\Models\AgendaConfiguracion;
use App\Models\Cliente;
use App\Models\DisponibilidadHorario;
use App\Models\Reunion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Flujo público de /agendar: mostrar, disponibilidad, agendar y cancelar,
 * sin sesión. 'id' no está en $fillable de AgendaConfiguracion (igual que
 * ConfiguracionSmtp), así que la fila única se crea con forceFill() para
 * garantizar id=1 (ver CalculadorDisponibilidadTest para el detalle de por
 * qué create(['id' => 1, ...]) no basta con RefreshDatabase).
 */
class AgendarTest extends TestCase
{
    use RefreshDatabase;

    private function activarAgenda(array $overrides = []): AgendaConfiguracion
    {
        $config = new AgendaConfiguracion();
        $config->forceFill(array_merge([
            'id' => 1,
            'activa' => true,
            'duracion_minutos' => 60,
            'anticipacion_minima_horas' => 0,
            'dias_visibles' => 15,
        ], $overrides))->save();

        return $config;
    }

    /** Lunes futuro fijo, sea cual sea el día en que corra el test. */
    private function proximoLunes()
    {
        return today()->next(\Carbon\Carbon::MONDAY);
    }

    public function test_mostrar_responde_200(): void
    {
        $this->get(route('agendar.mostrar'))->assertOk();
    }

    public function test_disponibilidad_devuelve_json_con_horarios(): void
    {
        $this->activarAgenda();
        $lunes = $this->proximoLunes();

        DisponibilidadHorario::create([
            'dia_semana' => $lunes->dayOfWeek,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '13:00:00',
            'activo' => true,
        ]);

        $response = $this->getJson(route('agendar.disponibilidad', ['fecha' => $lunes->toDateString()]));

        $response->assertOk();
        $response->assertJson(['horarios' => ['09:00', '10:00', '11:00', '12:00']]);
    }

    private function payloadAgenda(array $overrides = []): array
    {
        $lunes = $this->proximoLunes();

        return array_merge([
            'nombre' => 'Prospecto de Prueba',
            'email' => 'prospecto@example.com',
            'telefono' => '5512345678',
            'notas' => 'Quiero saber más de SEO.',
            'inicia_en' => $lunes->format('Y-m-d').' 10:00',
        ], $overrides);
    }

    public function test_agendar_crea_una_reunion_y_manda_los_dos_correos(): void
    {
        Mail::fake();
        $this->activarAgenda();
        $lunes = $this->proximoLunes();

        DisponibilidadHorario::create([
            'dia_semana' => $lunes->dayOfWeek,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '13:00:00',
            'activo' => true,
        ]);

        $response = $this->postJson(route('agendar.agendar'), $this->payloadAgenda());

        $response->assertOk();
        $response->assertJson(['ok' => true]);

        $this->assertDatabaseHas('reuniones', [
            'email' => 'prospecto@example.com',
            'estado' => EstadoReunion::Confirmada->value,
        ]);

        Mail::assertSent(ReunionConfirmadaMail::class);
        Mail::assertSent(ReunionNuevaMail::class);
    }

    public function test_agendar_vincula_cliente_id_si_el_email_coincide(): void
    {
        Mail::fake();
        $this->activarAgenda();
        $lunes = $this->proximoLunes();

        DisponibilidadHorario::create([
            'dia_semana' => $lunes->dayOfWeek,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '13:00:00',
            'activo' => true,
        ]);

        $cliente = Cliente::factory()->create(['email' => 'cliente-existente@example.com']);

        $this->postJson(route('agendar.agendar'), $this->payloadAgenda(['email' => 'cliente-existente@example.com']))
            ->assertOk();

        $this->assertDatabaseHas('reuniones', [
            'email' => 'cliente-existente@example.com',
            'cliente_id' => $cliente->id,
        ]);
    }

    public function test_agendar_el_mismo_horario_dos_veces_responde_422_la_segunda(): void
    {
        Mail::fake();
        $this->activarAgenda();
        $lunes = $this->proximoLunes();

        DisponibilidadHorario::create([
            'dia_semana' => $lunes->dayOfWeek,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '13:00:00',
            'activo' => true,
        ]);

        $payload = $this->payloadAgenda();

        $this->postJson(route('agendar.agendar'), $payload)->assertOk();

        $response = $this->postJson(route('agendar.agendar'), array_merge($payload, ['email' => 'otro@example.com']));

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Ese horario ya no está disponible, elige otro.']);
    }

    public function test_cancelar_y_confirmar_cancelacion_liberan_el_horario(): void
    {
        Mail::fake();
        $this->activarAgenda();
        $lunes = $this->proximoLunes();

        DisponibilidadHorario::create([
            'dia_semana' => $lunes->dayOfWeek,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '13:00:00',
            'activo' => true,
        ]);

        $payload = $this->payloadAgenda();
        $this->postJson(route('agendar.agendar'), $payload)->assertOk();

        $reunion = Reunion::firstOrFail();

        $this->get(route('agendar.cancelar', $reunion->token))->assertOk();

        $this->post(route('agendar.cancelar.confirmar', $reunion->token))
            ->assertRedirect(route('agendar.cancelar', $reunion->token));

        $this->assertSame(EstadoReunion::Cancelada, $reunion->fresh()->estado);

        // El mismo hueco vuelve a estar libre tras la cancelación.
        $response = $this->postJson(route('agendar.agendar'), array_merge($payload, ['email' => 'otro-prospecto@example.com']));
        $response->assertOk();

        $this->assertDatabaseHas('reuniones', [
            'email' => 'otro-prospecto@example.com',
            'estado' => EstadoReunion::Confirmada->value,
        ]);
    }

    public function test_cancelar_con_token_invalido_redirige_con_mensaje(): void
    {
        $response = $this->get(route('agendar.cancelar', 'token-que-no-existe'));

        $response->assertRedirect(route('agendar.mostrar'));
        $response->assertSessionHas('status', 'Ese enlace ya no es válido.');
    }
}
