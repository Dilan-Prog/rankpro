<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\CorreoDestinatario;
use App\Models\CorreoEvento;
use App\Models\EventoCalendario;
use App\Models\Finanza;
use App\Models\Permission;
use App\Models\Reunion;
use App\Models\Role;
use App\Models\Servicio;
use App\Models\ServicioEvento;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * «Inicio»: resumen del día. Cubre el saludo por hora, el filtrado por permiso de
 * cada bloque, el aislamiento de tareas por usuario y los estados vacíos.
 */
class InicioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // El manifest de Vite versionado no conoce los assets nuevos hasta compilar.
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Usuario con rol propio y solo los permisos dados (con permisos activados). */
    private function usuarioCon(array $modulos, string $nombre = 'Laura Pérez'): User
    {
        config(['permisos.aplicar' => true]);

        $rol = Role::query()->create(['name' => 'rol-'.uniqid(), 'label' => 'Rol']);
        foreach ($modulos as $modulo) {
            $permiso = Permission::query()->firstOrCreate(['name' => $modulo], ['label' => $modulo, 'module' => $modulo]);
            $rol->permissions()->attach($permiso->id);
        }

        return User::factory()->create(['role_id' => $rol->id, 'is_active' => true, 'name' => $nombre]);
    }

    private function tarea(User $user, array $extra = []): Tarea
    {
        return Tarea::create($extra + [
            'user_id' => $user->id,
            'titulo' => 'Tarea',
            'prioridad' => 'media',
            'estado' => 'pendiente',
            'orden' => 0,
        ]);
    }

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get(route('admin.inicio', ['tab' => 'resumen']))->assertRedirect(route('login'));
    }

    public function test_sin_el_permiso_inicio_responde_403(): void
    {
        $this->actingAs($this->usuarioCon(['clientes']))->get(route('admin.inicio', ['tab' => 'resumen']))->assertForbidden();
    }

    public function test_saludo_segun_la_hora_con_el_primer_nombre_y_fecha_larga(): void
    {
        $user = $this->usuarioCon(['inicio']);

        foreach (['2026-11-02 08:00' => 'Buenos días', '2026-11-02 15:30' => 'Buenas tardes', '2026-11-02 21:00' => 'Buenas noches', '2026-11-02 02:00' => 'Buenas noches'] as $hora => $saludo) {
            Carbon::setTestNow(Carbon::parse($hora));
            $this->actingAs($user)->get(route('admin.inicio', ['tab' => 'resumen']))
                ->assertOk()
                ->assertSee($saludo.', Laura', false)
                ->assertSee('Lunes, 2 de noviembre de 2026', false);
        }
    }

    public function test_los_kpis_solo_se_muestran_con_permiso_de_finanzas(): void
    {
        $this->actingAs($this->usuarioCon(['inicio']))->get(route('admin.inicio', ['tab' => 'resumen']))
            ->assertOk()
            ->assertDontSee('MRR Activo');

        $this->actingAs($this->usuarioCon(['inicio', 'finanzas']))->get(route('admin.inicio', ['tab' => 'resumen']))
            ->assertOk()
            ->assertSee('MRR Activo')
            ->assertSee('Pagos Pendientes')
            // Sin permiso de clientes ni ads, esas tarjetas no enlazan a su módulo.
            // (el panel de notificaciones del layout también enlaza a clientes: se busca la tarjeta .kpi)
            ->assertSee('<a href="'.route('admin.finanzas.index').'" class="kpi"', false)
            ->assertDontSee('<a href="'.route('admin.clientes.index').'" class="kpi"', false)
            ->assertDontSee('<a href="'.route('admin.ads.index').'" class="kpi"', false);
    }

    public function test_accesos_rapidos_se_filtran_por_permiso(): void
    {
        $this->actingAs($this->usuarioCon(['inicio', 'calendario', 'reportes']))->get(route('admin.inicio', ['tab' => 'resumen']))
            ->assertOk()
            ->assertSee(route('admin.calendario.index'), false)
            ->assertSee(route('admin.reportes.index'), false)
            ->assertDontSee(route('admin.keywords.index'), false)
            ->assertDontSee(route('admin.agenda.index'), false);

        $this->actingAs($this->usuarioCon(['inicio']))->get(route('admin.inicio', ['tab' => 'resumen']))
            ->assertDontSee('Accesos rápidos');
    }

    public function test_alertas_enlazan_al_modulo_y_se_omiten_sin_permiso(): void
    {
        $cliente = Cliente::factory()->create(['nombre' => 'Hotel Aurora', 'estado' => 'activo']);
        Finanza::create([
            'cliente_id' => $cliente->id, 'concepto' => 'Mensualidad', 'tipo' => 'ingreso',
            'monto' => 5000, 'estado' => 'vencido', 'fecha_vencimiento' => now()->subDays(3),
            'mes' => now()->month, 'anio' => now()->year,
        ]);

        $this->actingAs($this->usuarioCon(['inicio', 'finanzas']))->get(route('admin.inicio', ['tab' => 'resumen']))
            ->assertOk()
            ->assertSee('Pago vencido: Hotel Aurora')
            ->assertSee(route('admin.finanzas.index'), false);

        $this->actingAs($this->usuarioCon(['inicio', 'clientes']))->get(route('admin.inicio', ['tab' => 'resumen']))
            ->assertOk()
            ->assertDontSee('Pago vencido');
    }

    public function test_alertas_sin_nada_urgente_muestran_estado_vacio(): void
    {
        $this->actingAs($this->usuarioCon(['inicio']))->get(route('admin.inicio', ['tab' => 'resumen']))
            ->assertSee('Nada urgente por ahora.');
    }

    public function test_agenda_de_hoy_ordena_por_hora_y_filtra_por_permiso(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 07:00'));

        Reunion::factory()->create(['nombre' => 'Marta', 'inicia_en' => now()->setTime(16, 0), 'termina_en' => now()->setTime(16, 30)]);
        EventoCalendario::create(['titulo' => 'Llamada matutina', 'tipo' => 'otro', 'fecha' => '2026-11-02', 'hora' => '09:00']);
        EventoCalendario::create(['titulo' => 'Evento de mañana', 'tipo' => 'otro', 'fecha' => '2026-11-03']);

        $this->actingAs($this->usuarioCon(['inicio', 'agenda', 'calendario']))->get(route('admin.inicio', ['tab' => 'resumen']))
            ->assertOk()
            ->assertSeeInOrder(['Llamada matutina', 'Reunión con Marta'])
            ->assertDontSee('Evento de mañana');

        // Sin permiso de agenda, la reunión no aparece.
        $this->actingAs($this->usuarioCon(['inicio', 'calendario']))->get(route('admin.inicio', ['tab' => 'resumen']))
            ->assertSee('Llamada matutina')
            ->assertDontSee('Reunión con Marta');

        $this->actingAs($this->usuarioCon(['inicio']))->get(route('admin.inicio', ['tab' => 'resumen']))
            ->assertSee('No hay eventos para hoy.');
    }

    public function test_mis_tareas_solo_lista_las_del_usuario_con_progreso(): void
    {
        $yo = $this->usuarioCon(['inicio', 'mi-espacio']);
        $otro = User::factory()->create();
        $this->tarea($yo, ['titulo' => 'Pendiente mía']);
        $this->tarea($yo, ['titulo' => 'Ya hecha mía', 'estado' => 'completada']);
        $this->tarea($otro, ['titulo' => 'Tarea ajena secreta']);

        $this->actingAs($yo)->get(route('admin.inicio', ['tab' => 'resumen']))
            ->assertOk()
            ->assertSee('Pendiente mía')
            ->assertDontSee('Ya hecha mía')
            ->assertDontSee('Tarea ajena secreta')
            ->assertSee('1 de 2 hechas')
            ->assertSee(route('admin.mi-espacio.index'), false);
    }

    public function test_mis_tareas_vacio_y_oculto_sin_permiso_de_mi_espacio(): void
    {
        $this->actingAs($this->usuarioCon(['inicio', 'mi-espacio']))->get(route('admin.inicio', ['tab' => 'resumen']))
            ->assertSee('No tienes tareas pendientes.')
            ->assertSee('0 de 0 hechas');

        $this->actingAs($this->usuarioCon(['inicio']))->get(route('admin.inicio', ['tab' => 'resumen']))
            ->assertDontSee('Mis tareas');
    }

    public function test_marcar_tarea_hecha_y_desmarcarla_actualiza_el_progreso(): void
    {
        $yo = $this->usuarioCon(['inicio']);
        $a = $this->tarea($yo);
        $this->tarea($yo);

        $this->actingAs($yo)->patchJson(route('admin.inicio.tareas.completar', $a->id), ['hecha' => true])
            ->assertOk()
            ->assertJsonPath('tarea.estado', 'completada')
            ->assertJsonPath('progreso.hechas', 1)
            ->assertJsonPath('progreso.total', 2)
            ->assertJsonPath('progreso.porcentaje', 50);
        $this->assertSame('completada', $a->fresh()->estado->value);

        $this->actingAs($yo)->patchJson(route('admin.inicio.tareas.completar', $a->id), ['hecha' => false])
            ->assertOk()
            ->assertJsonPath('progreso.hechas', 0);
        $this->assertSame('pendiente', $a->fresh()->estado->value);
    }

    public function test_no_se_puede_tocar_la_tarea_de_otro_usuario(): void
    {
        $yo = $this->usuarioCon(['inicio']);
        $otro = User::factory()->create();
        $ajena = $this->tarea($otro);

        $this->actingAs($yo)->patchJson(route('admin.inicio.tareas.completar', $ajena->id), ['hecha' => true])
            ->assertNotFound();
        $this->assertSame('pendiente', $ajena->fresh()->estado->value);
    }

    public function test_completar_exige_el_campo_hecha_y_requiere_sesion(): void
    {
        $yo = $this->usuarioCon(['inicio']);
        $t = $this->tarea($yo);

        $this->actingAs($yo)->patchJson(route('admin.inicio.tareas.completar', $t->id), [])->assertStatus(422);

        auth()->logout();
        $this->patchJson(route('admin.inicio.tareas.completar', $t->id), ['hecha' => true])->assertUnauthorized();
    }

    public function test_actividad_reciente_combina_bitacoras_y_respeta_permisos(): void
    {
        $servicio = Servicio::factory()->create(['nombre' => 'Google Ads — Aurora']);
        ServicioEvento::create(['servicio_id' => $servicio->id, 'descripcion' => 'Servicio pausado', 'created_at' => now()->subHour()]);

        $destinatario = CorreoDestinatario::factory()->create(['nombre' => 'Pedro']);
        CorreoEvento::create(['destinatario_id' => $destinatario->id, 'tipo' => 'apertura', 'created_at' => now()->subMinutes(5)]);

        $this->actingAs($this->usuarioCon(['inicio', 'servicios', 'correo']))->get(route('admin.inicio', ['tab' => 'resumen']))
            ->assertOk()
            ->assertSeeInOrder(['Apertura de un correo: Pedro', 'Servicio pausado']);

        $this->actingAs($this->usuarioCon(['inicio', 'servicios']))->get(route('admin.inicio', ['tab' => 'resumen']))
            ->assertSee('Servicio pausado')
            ->assertDontSee('Apertura de un correo');

        $this->actingAs($this->usuarioCon(['inicio']))->get(route('admin.inicio', ['tab' => 'resumen']))
            ->assertSee('Aún no hay actividad registrada.');
    }
}
