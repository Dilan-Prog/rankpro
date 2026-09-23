<?php

namespace Tests\Unit\Support;

use App\Enums\EstadoReunion;
use App\Models\AgendaConfiguracion;
use App\Models\DisponibilidadBloqueo;
use App\Models\DisponibilidadHorario;
use App\Models\Reunion;
use App\Support\Agenda\CalculadorDisponibilidad;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifica CalculadorDisponibilidad slot a slot: horario semanal, bloqueos,
 * anticipación mínima, choques con reuniones ya confirmadas y el límite de
 * días visibles.
 */
class CalculadorDisponibilidadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 'id' no está en $fillable (igual que ConfiguracionSmtp), así que create()
     * lo ignora silenciosamente; y como el auto_increment de MySQL no se
     * reinicia entre tests (RefreshDatabase hace rollback, no TRUNCATE), cada
     * test recibiría una fila con id distinto de 1 y AgendaConfiguracion::actual()
     * (que busca id=1) no la encontraría. forceFill() sí permite fijar el id.
     */
    private function config(array $overrides = []): AgendaConfiguracion
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

    /** Un lunes cualquiera, para no depender de qué día es "hoy" al correr los tests. */
    private function proximoLunes(): CarbonImmutable
    {
        return CarbonImmutable::parse('next monday')->startOfDay();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_no_slots_when_day_has_no_active_schedule(): void
    {
        $this->config();

        $slots = app(CalculadorDisponibilidad::class)->disponiblesEn($this->proximoLunes());

        $this->assertSame([], $slots);
    }

    public function test_no_slots_on_a_blocked_date(): void
    {
        $this->config();
        $lunes = $this->proximoLunes();

        DisponibilidadHorario::create([
            'dia_semana' => $lunes->dayOfWeek,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '13:00:00',
            'activo' => true,
        ]);
        DisponibilidadBloqueo::create(['fecha' => $lunes->toDateString(), 'motivo' => 'Feriado']);

        $slots = app(CalculadorDisponibilidad::class)->disponiblesEn($lunes);

        $this->assertSame([], $slots);
    }

    public function test_returns_hourly_slots_within_the_schedule(): void
    {
        $this->config();
        $lunes = $this->proximoLunes();

        DisponibilidadHorario::create([
            'dia_semana' => $lunes->dayOfWeek,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '13:00:00',
            'activo' => true,
        ]);

        $slots = app(CalculadorDisponibilidad::class)->disponiblesEn($lunes);

        $this->assertCount(4, $slots);
        $this->assertSame(['09:00', '10:00', '11:00', '12:00'], array_map(fn ($s) => $s->format('H:i'), $slots));
    }

    public function test_an_existing_confirmed_meeting_removes_its_slot(): void
    {
        $this->config();
        $lunes = $this->proximoLunes();

        DisponibilidadHorario::create([
            'dia_semana' => $lunes->dayOfWeek,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '13:00:00',
            'activo' => true,
        ]);

        Reunion::factory()->create([
            'inicia_en' => $lunes->toMutable()->setTime(10, 0),
            'termina_en' => $lunes->toMutable()->setTime(11, 0),
            'estado' => EstadoReunion::Confirmada,
        ]);

        $slots = app(CalculadorDisponibilidad::class)->disponiblesEn($lunes);

        $this->assertSame(['09:00', '11:00', '12:00'], array_map(fn ($s) => $s->format('H:i'), $slots));
    }

    public function test_anticipacion_minima_filters_out_slots_too_close_to_now(): void
    {
        $lunes = $this->proximoLunes();
        Carbon::setTestNow($lunes->toMutable()->setTime(8, 30));

        $this->config(['anticipacion_minima_horas' => 3]);

        DisponibilidadHorario::create([
            'dia_semana' => $lunes->dayOfWeek,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '13:00:00',
            'activo' => true,
        ]);

        $slots = app(CalculadorDisponibilidad::class)->disponiblesEn($lunes);

        // Ahora + 3h = 11:30, así que 09:00, 10:00 y 11:00 quedan filtrados.
        $this->assertSame(['12:00'], array_map(fn ($s) => $s->format('H:i'), $slots));
    }

    public function test_no_slots_beyond_dias_visibles(): void
    {
        $this->config(['dias_visibles' => 5]);
        $fecha = today()->addDays(10)->toImmutable();

        DisponibilidadHorario::create([
            'dia_semana' => $fecha->dayOfWeek,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '13:00:00',
            'activo' => true,
        ]);

        $slots = app(CalculadorDisponibilidad::class)->disponiblesEn($fecha);

        $this->assertSame([], $slots);
    }

    public function test_esta_libre_true_and_false_scenarios(): void
    {
        $lunes = $this->proximoLunes();
        Carbon::setTestNow($lunes->toMutable()->setTime(6, 0));

        $this->config(['anticipacion_minima_horas' => 2]);

        DisponibilidadHorario::create([
            'dia_semana' => $lunes->dayOfWeek,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '13:00:00',
            'activo' => true,
        ]);

        $calculador = app(CalculadorDisponibilidad::class);

        // Libre: dentro de horario, con suficiente anticipación, sin choques.
        $this->assertTrue($calculador->estaLibre($lunes->toMutable()->setTime(10, 0), $lunes->toMutable()->setTime(11, 0)));

        // No libre: fuera del rango horario.
        $this->assertFalse($calculador->estaLibre($lunes->toMutable()->setTime(13, 0), $lunes->toMutable()->setTime(14, 0)));

        Reunion::factory()->create([
            'inicia_en' => $lunes->toMutable()->setTime(10, 0),
            'termina_en' => $lunes->toMutable()->setTime(11, 0),
            'estado' => EstadoReunion::Confirmada,
        ]);

        // No libre: choca con la reunión ya confirmada.
        $this->assertFalse($calculador->estaLibre($lunes->toMutable()->setTime(10, 30), $lunes->toMutable()->setTime(11, 30)));
    }
}
