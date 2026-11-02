<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\Proyecto;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «Mi espacio»: tareas personales (tabla `tareas` con user_id y sin proyecto),
 * orden persistente del kanban y aislamiento entre usuarios.
 */
class MiEspacioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // El manifest de Vite versionado no conoce los assets nuevos hasta compilar.
        $this->withoutVite();
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
        $this->get(route('admin.mi-espacio.index'))->assertRedirect(route('login'));
        $this->patch(route('admin.mi-espacio.mover', 1), [])->assertRedirect(route('login'));
    }

    public function test_index_solo_embebe_las_tareas_del_usuario_y_acepta_cada_vista(): void
    {
        $yo = User::factory()->create();
        $otro = User::factory()->create();
        $this->tarea($yo, ['titulo' => 'Mía']);
        $this->tarea($otro, ['titulo' => 'Ajena secreta']);
        // Una tarea de Desarrollo (con proyecto, sin user_id) tampoco es de nadie aquí.
        $proyecto = Proyecto::create(['cliente_id' => Cliente::factory()->create()->id, 'nombre' => 'P', 'tipo' => 'web_nueva', 'fase_actual' => 'planeacion', 'estado' => 'activo']);
        Tarea::create(['proyecto_id' => $proyecto->id, 'titulo' => 'De proyecto', 'prioridad' => 'alta', 'estado' => 'pendiente']);

        foreach (['tarjetas', 'kanban', 'tabla', 'calendario', 'timeline', 'invalida'] as $vista) {
            $this->actingAs($yo)->get(route('admin.mi-espacio.index', ['vista' => $vista]))
                ->assertOk()
                ->assertSee('Mía')
                ->assertDontSee('Ajena secreta')
                ->assertDontSee('De proyecto');
        }

        $this->actingAs($yo)->get(route('admin.mi-espacio.index', ['vista' => 'kanban']))->assertSee('data-vista="kanban"', false);
        $this->actingAs($yo)->get(route('admin.mi-espacio.index', ['vista' => 'invalida']))->assertSee('data-vista="tarjetas"', false);
    }

    public function test_crear_tarea_queda_a_nombre_del_usuario_y_al_final_de_su_columna(): void
    {
        $yo = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $this->tarea($yo, ['orden' => 4]);

        $this->actingAs($yo)->postJson(route('admin.mi-espacio.store'), [
            'titulo' => 'Nueva',
            'prioridad' => 'alta',
            'estado' => 'pendiente',
            'fecha_inicio' => '2026-11-02',
            'fecha_limite' => '2026-11-05',
            'cliente_id' => $cliente->id,
        ])->assertCreated()->assertJsonPath('cliente', $cliente->nombre)->assertJsonPath('orden', 5);

        $this->assertDatabaseHas('tareas', ['titulo' => 'Nueva', 'user_id' => $yo->id, 'proyecto_id' => null, 'cliente_id' => $cliente->id]);
    }

    public function test_acepta_el_estado_en_revision_y_rechaza_valores_invalidos(): void
    {
        $yo = User::factory()->create();

        $this->actingAs($yo)->postJson(route('admin.mi-espacio.store'), ['titulo' => 'A', 'prioridad' => 'baja', 'estado' => 'en_revision'])
            ->assertCreated()->assertJsonPath('estado', 'en_revision');

        $this->actingAs($yo)->postJson(route('admin.mi-espacio.store'), ['titulo' => 'B', 'prioridad' => 'urgente', 'estado' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors(['prioridad', 'estado']);

        $this->actingAs($yo)->postJson(route('admin.mi-espacio.store'), ['titulo' => 'C', 'prioridad' => 'baja', 'estado' => 'pendiente', 'fecha_inicio' => '2026-11-10', 'fecha_limite' => '2026-11-01'])
            ->assertStatus(422)->assertJsonValidationErrors(['fecha_limite']);
    }

    public function test_edicion_en_linea_actualiza_un_solo_campo(): void
    {
        $yo = User::factory()->create();
        $tarea = $this->tarea($yo, ['titulo' => 'Original', 'fecha_limite' => '2026-11-20']);

        $this->actingAs($yo)->putJson(route('admin.mi-espacio.update', $tarea), ['estado' => 'completada'])
            ->assertOk()->assertJsonPath('estado', 'completada');
        $this->actingAs($yo)->putJson(route('admin.mi-espacio.update', $tarea), ['fecha_limite' => '2026-11-25'])->assertOk();

        $this->assertDatabaseHas('tareas', ['id' => $tarea->id, 'titulo' => 'Original', 'estado' => 'completada']);
        $this->assertSame('2026-11-25', $tarea->fresh()->fecha_limite->toDateString());
    }

    public function test_mover_cambia_estado_y_renumera_la_columna_destino(): void
    {
        $yo = User::factory()->create();
        $a = $this->tarea($yo, ['titulo' => 'A', 'estado' => 'en_progreso', 'orden' => 0]);
        $b = $this->tarea($yo, ['titulo' => 'B', 'estado' => 'en_progreso', 'orden' => 1]);
        $c = $this->tarea($yo, ['titulo' => 'C', 'estado' => 'pendiente', 'orden' => 0]);

        // C pasa a «en progreso» entre A y B.
        $this->actingAs($yo)->patchJson(route('admin.mi-espacio.mover', $c), ['estado' => 'en_progreso', 'orden' => 1])
            ->assertOk()->assertJsonPath('estado', 'en_progreso');

        $this->assertSame(0, $a->fresh()->orden);
        $this->assertSame(1, $c->fresh()->orden);
        $this->assertSame(2, $b->fresh()->orden);
        $this->assertSame('en_progreso', $c->fresh()->estado->value);

        // Un orden mayor que la columna la deja al final; sin huecos.
        $this->actingAs($yo)->patchJson(route('admin.mi-espacio.mover', $a), ['estado' => 'en_progreso', 'orden' => 99])->assertOk();
        $this->assertSame(['C', 'B', 'A'], Tarea::where('estado', 'en_progreso')->orderBy('orden')->pluck('titulo')->all());
        $this->assertSame([0, 1, 2], Tarea::where('estado', 'en_progreso')->orderBy('orden')->pluck('orden')->all());
    }

    public function test_mover_valida_estado_y_orden(): void
    {
        $yo = User::factory()->create();
        $t = $this->tarea($yo);

        $this->actingAs($yo)->patchJson(route('admin.mi-espacio.mover', $t), ['estado' => 'nope', 'orden' => -1])
            ->assertStatus(422)->assertJsonValidationErrors(['estado', 'orden']);
    }

    public function test_un_usuario_no_ve_ni_mueve_ni_edita_ni_borra_tareas_de_otro(): void
    {
        $yo = User::factory()->create();
        $otro = User::factory()->create();
        $ajena = $this->tarea($otro, ['titulo' => 'Ajena', 'estado' => 'pendiente']);

        $this->actingAs($yo)->patchJson(route('admin.mi-espacio.mover', $ajena), ['estado' => 'completada', 'orden' => 0])->assertNotFound();
        $this->actingAs($yo)->putJson(route('admin.mi-espacio.update', $ajena), ['titulo' => 'Hackeada'])->assertNotFound();
        $this->actingAs($yo)->deleteJson(route('admin.mi-espacio.destroy', $ajena))->assertNotFound();

        $this->assertDatabaseHas('tareas', ['id' => $ajena->id, 'titulo' => 'Ajena', 'estado' => 'pendiente']);
    }

    public function test_borrar_tarea_propia(): void
    {
        $yo = User::factory()->create();
        $t = $this->tarea($yo);

        $this->actingAs($yo)->deleteJson(route('admin.mi-espacio.destroy', $t))->assertOk()->assertJson(['deleted' => true]);
        // Borrado suave: permite deshacer.
        $this->assertSoftDeleted('tareas', ['id' => $t->id]);
    }

    public function test_deshacer_el_borrado_restaura_la_tarea_propia(): void
    {
        $yo = User::factory()->create();
        $t = $this->tarea($yo, ['titulo' => 'Recuperable']);

        $this->actingAs($yo)->deleteJson(route('admin.mi-espacio.destroy', $t))->assertOk();
        $this->assertSame(0, Tarea::query()->where('user_id', $yo->id)->count());

        $this->actingAs($yo)->patchJson(route('admin.mi-espacio.restaurar', $t))
            ->assertOk()->assertJsonPath('titulo', 'Recuperable');

        $this->assertNotSoftDeleted('tareas', ['id' => $t->id]);
        $this->actingAs($yo)->get(route('admin.mi-espacio.index'))->assertSee('Recuperable');
    }

    public function test_no_se_puede_restaurar_la_tarea_de_otro_ni_una_inexistente(): void
    {
        $yo = User::factory()->create();
        $otro = User::factory()->create();
        $ajena = $this->tarea($otro, ['titulo' => 'Ajena']);
        $ajena->delete();

        $this->actingAs($yo)->patchJson(route('admin.mi-espacio.restaurar', $ajena))->assertNotFound();
        $this->actingAs($yo)->patchJson(route('admin.mi-espacio.restaurar', 999999))->assertNotFound();
        $this->assertSoftDeleted('tareas', ['id' => $ajena->id]);
    }

    public function test_una_tarea_borrada_no_cuenta_ni_se_lista_en_ningun_lado(): void
    {
        $yo = User::factory()->create();
        $this->tarea($yo, ['titulo' => 'Viva']);
        $borrada = $this->tarea($yo, ['titulo' => 'Fantasma']);
        $borrada->delete();

        $this->actingAs($yo)->get(route('admin.mi-espacio.index'))->assertSee('Viva')->assertDontSee('Fantasma');
        $this->assertSame(1, Tarea::query()->where('user_id', $yo->id)->count());
        // Mover/editar una borrada es 404 (la ruta no la ve).
        $this->actingAs($yo)->patchJson(route('admin.mi-espacio.mover', $borrada), ['estado' => 'completada', 'orden' => 0])->assertNotFound();
    }

    public function test_mover_con_posicion_exacta_cuenta_todas_las_tareas_de_la_columna(): void
    {
        // Con un filtro activo el front solo ve algunas tarjetas, pero `orden` es un índice sobre la columna completa.
        $yo = User::factory()->create();
        $a = $this->tarea($yo, ['titulo' => 'A', 'estado' => 'en_progreso', 'orden' => 0]);
        $b = $this->tarea($yo, ['titulo' => 'B oculta', 'estado' => 'en_progreso', 'orden' => 1]);
        $c = $this->tarea($yo, ['titulo' => 'C', 'estado' => 'en_progreso', 'orden' => 2]);
        $x = $this->tarea($yo, ['titulo' => 'X', 'estado' => 'pendiente', 'orden' => 0]);

        // Soltada justo después de A (visible), antes de la oculta B: posición 1.
        $this->actingAs($yo)->patchJson(route('admin.mi-espacio.mover', $x), ['estado' => 'en_progreso', 'orden' => 1])->assertOk();

        $ordenadas = Tarea::query()->where('estado', 'en_progreso')->orderBy('orden')->pluck('titulo')->all();
        $this->assertSame(['A', 'X', 'B oculta', 'C'], $ordenadas);
    }

    public function test_la_vista_incluye_el_soporte_de_teclado_y_el_anuncio(): void
    {
        $yo = User::factory()->create();

        $this->actingAs($yo)->get(route('admin.mi-espacio.index', ['vista' => 'kanban']))
            ->assertOk()->assertSee('data-me-anuncio', false)->assertSee('aria-live="polite"', false);
    }

    public function test_borrar_al_usuario_deja_la_tarea_sin_dueno_en_vez_de_borrarla(): void
    {
        $yo = User::factory()->create();
        $t = $this->tarea($yo);

        $yo->delete();

        $this->assertNull($t->fresh()->user_id);
    }
}
