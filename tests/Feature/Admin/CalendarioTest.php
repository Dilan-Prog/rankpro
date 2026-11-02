<?php

namespace Tests\Feature\Admin;

use App\Enums\EstadoReunion;
use App\Models\Cliente;
use App\Models\EventoCalendario;
use App\Models\Finanza;
use App\Models\Proyecto;
use App\Models\Reunion;
use App\Models\Tarea;
use App\Models\User;
use App\Support\Calendario\AgregadorEventos;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Calendario: eventos manuales (alta/baja) y agregador de solo lectura de
 * Agenda, tareas, proyectos y finanzas.
 */
class CalendarioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // El manifest de Vite versionado no conoce los assets nuevos hasta compilar.
        $this->withoutVite();
    }

    private function proyecto(Cliente $cliente, array $extra = []): Proyecto
    {
        return Proyecto::create($extra + ['cliente_id' => $cliente->id, 'nombre' => 'Sitio', 'tipo' => 'web_nueva', 'fase_actual' => 'planeacion', 'estado' => 'activo']);
    }

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get(route('admin.calendario.index'))->assertRedirect(route('login'));
        $this->postJson(route('admin.calendario.store'), [])->assertUnauthorized();
    }

    public function test_index_y_endpoint_de_eventos_responden(): void
    {
        $user = User::factory()->create();
        EventoCalendario::create(['titulo' => 'Lanzamiento', 'tipo' => 'campana', 'fecha' => '2026-11-10']);

        $this->actingAs($user)->get(route('admin.calendario.index', ['mes' => '2026-11']))
            ->assertOk()->assertSee('Lanzamiento')->assertSee('data-mes="2026-11"', false);

        $this->actingAs($user)->getJson(route('admin.calendario.eventos', ['desde' => '2026-10-26', 'hasta' => '2026-12-06']))
            ->assertOk()->assertJsonPath('eventos.0.titulo', 'Lanzamiento')->assertJsonPath('eventos.0.editable', true);
    }

    public function test_el_rango_se_valida_y_se_limita(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(route('admin.calendario.eventos', ['desde' => 'ayer', 'hasta' => '2026-12-06']))->assertStatus(422);
        $this->actingAs($user)->getJson(route('admin.calendario.eventos', ['desde' => '2026-12-06', 'hasta' => '2026-11-01']))->assertStatus(422);
        $this->actingAs($user)->getJson(route('admin.calendario.eventos', ['desde' => '2026-01-01', 'hasta' => '2026-12-31']))->assertStatus(422);
    }

    public function test_crear_y_borrar_evento_manual(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $id = $this->actingAs($user)->postJson(route('admin.calendario.store'), [
            'titulo' => 'Revisión de campaña',
            'tipo' => 'campana',
            'fecha' => '2026-11-12',
            'hora' => '09:30',
            'cliente_id' => $cliente->id,
        ])->assertCreated()
            ->assertJsonPath('hora', '09:30')
            ->assertJsonPath('cliente', $cliente->nombre)
            ->json('evento_id');

        $this->assertDatabaseHas('eventos_calendario', ['id' => $id, 'user_id' => $user->id, 'tipo' => 'campana']);

        $this->actingAs($user)->deleteJson(route('admin.calendario.destroy', $id))->assertOk();
        $this->assertDatabaseMissing('eventos_calendario', ['id' => $id]);
    }

    public function test_alta_rechaza_tipo_y_fecha_invalidos(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('admin.calendario.store'), ['titulo' => 'X', 'tipo' => 'fiesta', 'fecha' => '12/11/2026', 'hora' => '25:00'])
            ->assertStatus(422)->assertJsonValidationErrors(['tipo', 'fecha', 'hora']);
        $this->assertDatabaseCount('eventos_calendario', 0);
    }

    public function test_los_eventos_agregados_no_se_pueden_borrar_desde_el_calendario(): void
    {
        $user = User::factory()->create();
        $reunion = Reunion::factory()->create();

        // `eventos/{evento}` solo conoce la tabla manual: un id de reunión no la toca.
        $this->actingAs($user)->deleteJson(route('admin.calendario.destroy', 999999))->assertNotFound();
        $this->assertDatabaseHas('reuniones', ['id' => $reunion->id]);
    }

    public function test_agregador_junta_reuniones_tareas_proyectos_y_cobros_de_solo_lectura(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $proyecto = $this->proyecto($cliente, ['fecha_entrega_estimada' => '2026-11-20']);

        Reunion::factory()->create(['nombre' => 'Ana', 'inicia_en' => '2026-11-10 16:00:00', 'termina_en' => '2026-11-10 16:30:00']);
        Reunion::factory()->create(['nombre' => 'Cancelada', 'inicia_en' => '2026-11-10 12:00:00', 'termina_en' => '2026-11-10 12:30:00', 'estado' => EstadoReunion::Cancelada]);
        Tarea::create(['proyecto_id' => $proyecto->id, 'titulo' => 'Maquetar', 'prioridad' => 'alta', 'estado' => 'pendiente', 'fecha_limite' => '2026-11-15']);
        Tarea::create(['proyecto_id' => $proyecto->id, 'titulo' => 'Ya hecha', 'prioridad' => 'alta', 'estado' => 'completada', 'fecha_limite' => '2026-11-15']);
        Finanza::create(['cliente_id' => $cliente->id, 'concepto' => 'Minuta noviembre', 'tipo' => 'ingreso', 'monto' => 100, 'estado' => 'pendiente', 'fecha_vencimiento' => '2026-11-25', 'mes' => 11, 'anio' => 2026]);
        Finanza::create(['cliente_id' => $cliente->id, 'concepto' => 'Ya cobrada', 'tipo' => 'ingreso', 'monto' => 100, 'estado' => 'pagado', 'fecha_vencimiento' => '2026-11-25', 'mes' => 11, 'anio' => 2026]);

        $eventos = app(AgregadorEventos::class)->entre(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'), $user->id);

        $this->assertEqualsCanonicalizing(
            ['agenda', 'tarea', 'proyecto', 'finanza'],
            $eventos->pluck('origen')->all()
        );
        $this->assertTrue($eventos->every(fn ($e) => $e['editable'] === false && ! empty($e['url'])));

        $reunionEv = $eventos->firstWhere('origen', 'agenda');
        $this->assertSame('reunion', $reunionEv['tipo']);
        $this->assertSame('2026-11-10', $reunionEv['fecha']);
        $this->assertSame('16:00', $reunionEv['hora']);
        $this->assertSame('pago', $eventos->firstWhere('origen', 'finanza')['tipo']);
        $this->assertSame('2026-11-20', $eventos->firstWhere('origen', 'proyecto')['fecha']);
        $this->assertSame('Vence: Maquetar', $eventos->firstWhere('origen', 'tarea')['titulo']);
    }

    public function test_agregador_respeta_el_rango_incluyendo_el_ultimo_dia(): void
    {
        $user = User::factory()->create();
        Reunion::factory()->create(['inicia_en' => '2026-11-30 23:00:00', 'termina_en' => '2026-11-30 23:30:00']);
        Reunion::factory()->create(['inicia_en' => '2026-12-01 00:30:00', 'termina_en' => '2026-12-01 01:00:00']);

        $eventos = app(AgregadorEventos::class)->entre(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'), $user->id);

        $this->assertCount(1, $eventos);
        $this->assertSame('2026-11-30', $eventos->first()['fecha']);
    }

    public function test_agregador_incluye_tareas_personales_solo_del_usuario_que_mira(): void
    {
        $yo = User::factory()->create();
        $otro = User::factory()->create();
        Tarea::create(['user_id' => $yo->id, 'titulo' => 'Mía', 'prioridad' => 'media', 'estado' => 'pendiente', 'fecha_limite' => '2026-11-15']);
        Tarea::create(['user_id' => $otro->id, 'titulo' => 'Ajena', 'prioridad' => 'media', 'estado' => 'pendiente', 'fecha_limite' => '2026-11-15']);

        $titulos = app(AgregadorEventos::class)->entre(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'), $yo->id)->pluck('titulo')->all();

        $this->assertSame(['Vence: Mía'], $titulos);
    }

    public function test_la_respuesta_mezcla_manuales_y_agregados_ordenados_por_hora(): void
    {
        $user = User::factory()->create();
        Reunion::factory()->create(['inicia_en' => '2026-11-10 16:00:00', 'termina_en' => '2026-11-10 16:30:00']);
        EventoCalendario::create(['titulo' => 'Temprano', 'tipo' => 'otro', 'fecha' => '2026-11-10', 'hora' => '08:00']);
        EventoCalendario::create(['titulo' => 'Todo el día', 'tipo' => 'otro', 'fecha' => '2026-11-10']);

        $json = $this->actingAs($user)->getJson(route('admin.calendario.eventos', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))->assertOk()->json('eventos');

        $this->assertSame(['Todo el día', 'Temprano'], array_slice(array_column($json, 'titulo'), 0, 2));
        $this->assertSame('agenda', $json[2]['origen']);
    }
}
