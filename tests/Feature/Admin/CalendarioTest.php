<?php

namespace Tests\Feature\Admin;

use App\Enums\EstadoReunion;
use App\Models\AdsCampana;
use App\Models\AdsLanzamiento;
use App\Models\Cliente;
use App\Models\EventoCalendario;
use App\Models\Finanza;
use App\Models\Proyecto;
use App\Models\Permission;
use App\Models\Reunion;
use App\Models\Role;
use App\Models\SeoCampana;
use App\Models\Servicio;
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
            'tipo' => 'entrega',
            'fecha' => '2026-11-12',
            'hora' => '09:30',
            'cliente_id' => $cliente->id,
        ])->assertCreated()
            ->assertJsonPath('hora', '09:30')
            ->assertJsonPath('cliente', $cliente->nombre)
            ->json('evento_id');

        $this->assertDatabaseHas('eventos_calendario', ['id' => $id, 'user_id' => $user->id, 'tipo' => 'entrega']);

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

    /** Usuario con rol propio y solo los permisos dados (con permisos activados). */
    private function usuarioCon(array $modulos, string $rol = 'rol-prueba'): User
    {
        config(['permisos.aplicar' => true]);

        $role = Role::query()->firstOrCreate(['name' => $rol], ['label' => 'Rol']);
        foreach ($modulos as $modulo) {
            $permiso = Permission::query()->firstOrCreate(['name' => $modulo], ['label' => $modulo, 'module' => $modulo]);
            $role->permissions()->syncWithoutDetaching([$permiso->id]);
        }

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function evento(User $dueno, array $extra = []): EventoCalendario
    {
        return EventoCalendario::create($extra + ['titulo' => 'Evento', 'tipo' => 'otro', 'fecha' => '2026-11-12', 'user_id' => $dueno->id]);
    }

    public function test_editar_evento_manual_propio(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $e = $this->evento($user);

        $this->actingAs($user)->putJson(route('admin.calendario.update', $e), [
            'titulo' => 'Renombrado',
            'tipo' => 'reunion',
            'fecha' => '2026-11-14',
            'hora' => '10:15',
            'cliente_id' => $cliente->id,
        ])->assertOk()
            ->assertJsonPath('titulo', 'Renombrado')
            ->assertJsonPath('fecha', '2026-11-14')
            ->assertJsonPath('hora', '10:15')
            ->assertJsonPath('cliente_id', $cliente->id)
            ->assertJsonPath('editable', true);

        $this->assertDatabaseHas('eventos_calendario', ['id' => $e->id, 'titulo' => 'Renombrado', 'tipo' => 'reunion', 'user_id' => $user->id]);
    }

    public function test_editar_valida_los_datos(): void
    {
        $user = User::factory()->create();
        $e = $this->evento($user);

        $this->actingAs($user)->putJson(route('admin.calendario.update', $e), ['titulo' => '', 'tipo' => 'fiesta', 'fecha' => '12/11/2026', 'hora' => '25:00'])
            ->assertStatus(422)->assertJsonValidationErrors(['titulo', 'tipo', 'fecha', 'hora']);
        $this->assertDatabaseHas('eventos_calendario', ['id' => $e->id, 'titulo' => 'Evento']);
    }

    public function test_el_tipo_campana_no_se_crea_ni_se_asigna_a_mano(): void
    {
        $user = User::factory()->create();
        $e = $this->evento($user);

        $this->actingAs($user)->postJson(route('admin.calendario.store'), ['titulo' => 'X', 'tipo' => 'campana', 'fecha' => '2026-11-12'])
            ->assertStatus(422)->assertJsonValidationErrors(['tipo']);
        $this->actingAs($user)->putJson(route('admin.calendario.update', $e), ['titulo' => 'X', 'tipo' => 'campana', 'fecha' => '2026-11-12'])
            ->assertStatus(422)->assertJsonValidationErrors(['tipo']);

        // Un evento heredado de tipo campaña sí puede seguir editándose conservando su tipo.
        $viejo = $this->evento($user, ['tipo' => 'campana']);
        $this->actingAs($user)->putJson(route('admin.calendario.update', $viejo), ['titulo' => 'Sigue', 'tipo' => 'campana', 'fecha' => '2026-11-12'])->assertOk();
    }

    public function test_un_usuario_no_edita_ni_borra_eventos_de_otro_pero_un_admin_si(): void
    {
        $dueno = User::factory()->create();
        $otro = $this->usuarioCon(['calendario'], 'rol-normal');
        $admin = $this->usuarioCon(['calendario'], config('permisos.rol_admin'));
        $e = $this->evento($dueno, ['titulo' => 'Del dueño']);
        $cuerpo = ['titulo' => 'Cambiado', 'tipo' => 'otro', 'fecha' => '2026-11-12'];

        $this->actingAs($otro)->putJson(route('admin.calendario.update', $e), $cuerpo)->assertForbidden();
        $this->actingAs($otro)->deleteJson(route('admin.calendario.destroy', $e))->assertForbidden();
        $this->assertDatabaseHas('eventos_calendario', ['id' => $e->id, 'titulo' => 'Del dueño']);

        // El listado marca como no editable lo ajeno, para que el front no ofrezca el botón.
        $json = $this->actingAs($otro)->getJson(route('admin.calendario.eventos', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))->json('eventos');
        $this->assertFalse($json[0]['editable']);

        $this->actingAs($admin)->putJson(route('admin.calendario.update', $e), $cuerpo)->assertOk();
        $this->assertDatabaseHas('eventos_calendario', ['id' => $e->id, 'titulo' => 'Cambiado', 'user_id' => $dueno->id]);
    }

    public function test_editar_un_evento_inexistente_es_404_y_sin_login_401(): void
    {
        $user = User::factory()->create();
        $e = $this->evento($user);

        $this->actingAs($user)->putJson(route('admin.calendario.update', 999999), ['titulo' => 'X', 'tipo' => 'otro', 'fecha' => '2026-11-12'])->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->putJson(route('admin.calendario.update', $e), [])->assertUnauthorized();
    }

    private function campanaAds(Cliente $cliente, array $extra = []): AdsCampana
    {
        return AdsCampana::factory()->create($extra + ['cliente_id' => $cliente->id, 'nombre' => 'Ads Verano', 'fecha_inicio' => '2026-11-05']);
    }

    public function test_agregador_alimenta_el_tipo_campana_desde_ads_y_seo(): void
    {
        $user = User::factory()->create(); // permisos apagados: ve todo
        $cliente = Cliente::factory()->create(['nombre' => 'Acme']);

        $ads = $this->campanaAds($cliente);
        AdsLanzamiento::create(['ads_campana_id' => $ads->id, 'ciclo' => 1, 'fecha_lanzamiento' => '2026-11-08']);
        SeoCampana::create(['cliente_id' => $cliente->id, 'servicio_id' => Servicio::factory()->create()->id, 'nombre' => 'SEO Local', 'estado' => 'activa', 'fecha_inicio' => '2026-11-20']);
        // Fuera de rango o sin fecha: no aparecen.
        $this->campanaAds($cliente, ['nombre' => 'Vieja', 'fecha_inicio' => '2026-01-01']);
        $this->campanaAds($cliente, ['nombre' => 'Sin fecha', 'fecha_inicio' => null]);

        $eventos = app(AgregadorEventos::class)->entre(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'), $user->id);

        $this->assertCount(3, $eventos);
        $this->assertTrue($eventos->every(fn ($e) => $e['tipo'] === 'campana' && $e['editable'] === false));

        $inicioAds = $eventos->firstWhere('id', 'ads-inicio-'.$ads->id);
        $this->assertSame('2026-11-05', $inicioAds['fecha']);
        $this->assertSame('Acme', $inicioAds['cliente']);
        $this->assertSame(route('admin.ads.show', $ads->id), $inicioAds['url']);
        $this->assertSame('ads', $inicioAds['origen']);

        $this->assertSame('2026-11-08', $eventos->firstWhere('titulo', 'Lanzamiento Ads: Ads Verano')['fecha']);

        $seo = $eventos->firstWhere('origen', 'seo');
        $this->assertSame('2026-11-20', $seo['fecha']);
        $this->assertSame(route('admin.seo.show', SeoCampana::first()->id), $seo['url']);
    }

    public function test_cada_origen_de_campana_exige_el_permiso_de_su_modulo(): void
    {
        $cliente = Cliente::factory()->create();
        $ads = $this->campanaAds($cliente);
        AdsLanzamiento::create(['ads_campana_id' => $ads->id, 'ciclo' => 1, 'fecha_lanzamiento' => '2026-11-08']);
        SeoCampana::create(['cliente_id' => $cliente->id, 'servicio_id' => Servicio::factory()->create()->id, 'nombre' => 'SEO Local', 'estado' => 'activa', 'fecha_inicio' => '2026-11-20']);

        $rango = [Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30')];
        $origenes = fn (User $u) => app(AgregadorEventos::class)->entre($rango[0], $rango[1], $u->id)->pluck('origen')->unique()->sort()->values()->all();

        $this->assertSame([], $origenes($this->usuarioCon(['calendario'], 'sin-nada')));
        $this->assertSame(['ads'], $origenes($this->usuarioCon(['calendario', 'ads'], 'solo-ads')));
        $this->assertSame(['seo'], $origenes($this->usuarioCon(['calendario', 'seo'], 'solo-seo')));
        $this->assertSame(['ads', 'seo'], $origenes($this->usuarioCon(['calendario', 'ads', 'seo'], 'ambos')));

        // Sin usuario no se sabe qué puede ver: no se agrega nada.
        $this->assertCount(0, app(AgregadorEventos::class)->entre($rango[0], $rango[1], null));
    }

    public function test_campanas_borradas_no_aparecen_y_el_endpoint_las_entrega(): void
    {
        $cliente = Cliente::factory()->create();
        $viva = $this->campanaAds($cliente, ['nombre' => 'Viva']);
        $borrada = $this->campanaAds($cliente, ['nombre' => 'Borrada']);
        AdsLanzamiento::create(['ads_campana_id' => $borrada->id, 'ciclo' => 1, 'fecha_lanzamiento' => '2026-11-08']);
        $borrada->delete();

        $user = $this->usuarioCon(['calendario', 'ads']);
        $json = $this->actingAs($user)->getJson(route('admin.calendario.eventos', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))->assertOk()->json('eventos');

        $this->assertSame(['Inicio de campaña Ads: Viva'], array_column($json, 'titulo'));
        $this->assertSame($viva->id, (int) substr($json[0]['id'], strlen('ads-inicio-')));
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

    public function test_un_rol_solo_con_calendario_no_ve_cobros_reuniones_ni_entregas(): void
    {
        $cliente = Cliente::factory()->create();
        $proyecto = $this->proyecto($cliente, ['fecha_entrega_estimada' => '2026-11-20']);
        Reunion::factory()->create(['nombre' => 'Ana', 'inicia_en' => '2026-11-10 16:00:00', 'termina_en' => '2026-11-10 16:30:00']);
        Tarea::create(['proyecto_id' => $proyecto->id, 'titulo' => 'Maquetar', 'prioridad' => 'alta', 'estado' => 'pendiente', 'fecha_limite' => '2026-11-15']);
        Finanza::create(['cliente_id' => $cliente->id, 'concepto' => 'Minuta noviembre', 'tipo' => 'ingreso', 'monto' => 100, 'estado' => 'pendiente', 'fecha_vencimiento' => '2026-11-25', 'mes' => 11, 'anio' => 2026]);

        $solo = $this->usuarioCon(['calendario'], 'solo-calendario');
        $respuesta = $this->actingAs($solo)->getJson(route('admin.calendario.eventos', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))->assertOk();
        $this->assertSame([], $respuesta->json('eventos'));

        $rango = [Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30')];
        $origenes = fn (User $u) => app(AgregadorEventos::class)->entre($rango[0], $rango[1], $u->id)->pluck('origen')->unique()->sort()->values()->all();

        $this->assertSame(['agenda'], $origenes($this->usuarioCon(['calendario', 'agenda'], 'con-agenda')));
        $this->assertSame(['finanza'], $origenes($this->usuarioCon(['calendario', 'finanzas'], 'con-finanzas')));
        $this->assertSame(['proyecto', 'tarea'], $origenes($this->usuarioCon(['calendario', 'desarrollo'], 'con-desarrollo')));
    }
}
