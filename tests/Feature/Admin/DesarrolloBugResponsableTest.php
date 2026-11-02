<?php

namespace Tests\Feature\Admin;

use App\Models\Bug;
use App\Models\Cliente;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Responsable («Asignado a») de un bug: opcional, solo usuarios activos y
 * nullOnDelete al borrar al usuario.
 */
class DesarrolloBugResponsableTest extends TestCase
{
    use RefreshDatabase;

    private function proyecto(): Proyecto
    {
        return Proyecto::create([
            'cliente_id' => Cliente::factory()->create()->id,
            'nombre' => 'Proyecto de Prueba',
            'tipo' => 'web_nueva',
            'fase_actual' => 'control',
            'porcentaje_avance' => 0,
            'presupuesto' => 1000,
            'anticipo' => 0,
            'pagos_recibidos' => 0,
            'estado' => 'activo',
        ]);
    }

    private function datos(array $extra = []): array
    {
        return array_merge(['titulo' => 'Falla', 'prioridad' => 'media', 'estado' => 'abierto'], $extra);
    }

    public function test_crear_bug_con_responsable_devuelve_id_y_nombre_en_el_json(): void
    {
        $admin = User::factory()->create();
        $dev = User::factory()->create(['name' => 'Ana Dev']);
        $p = $this->proyecto();

        $res = $this->actingAs($admin)->postJson(route('admin.desarrollo.bugs.store', $p), $this->datos(['responsable_id' => $dev->id]));

        $res->assertCreated()->assertJsonPath('responsable_id', $dev->id)->assertJsonPath('responsable_nombre', 'Ana Dev');
        $this->assertSame($dev->id, Bug::first()->responsable->id);
    }

    public function test_responsable_es_opcional(): void
    {
        $p = $this->proyecto();

        $res = $this->actingAs(User::factory()->create())->postJson(route('admin.desarrollo.bugs.store', $p), $this->datos(['responsable_id' => '']));

        $res->assertCreated()->assertJsonPath('responsable_id', null)->assertJsonPath('responsable_nombre', null);
    }

    public function test_rechaza_usuario_inexistente(): void
    {
        $p = $this->proyecto();

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.desarrollo.bugs.store', $p), $this->datos(['responsable_id' => 999999]))
            ->assertStatus(422)->assertJsonValidationErrors('responsable_id');
    }

    public function test_rechaza_usuario_inactivo(): void
    {
        $inactivo = User::factory()->create(['is_active' => false]);
        $p = $this->proyecto();

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.desarrollo.bugs.store', $p), $this->datos(['responsable_id' => $inactivo->id]))
            ->assertStatus(422)->assertJsonValidationErrors('responsable_id');

        $this->assertSame(0, Bug::count());
    }

    public function test_actualizar_sin_el_campo_conserva_el_responsable_y_con_null_lo_quita(): void
    {
        $dev = User::factory()->create();
        $bug = Bug::create(['proyecto_id' => $this->proyecto()->id, 'responsable_id' => $dev->id, 'titulo' => 'X', 'prioridad' => 'media', 'estado' => 'abierto']);
        $admin = User::factory()->create();

        // El arrastre del kanban no manda responsable_id: no debe desasignar.
        $this->actingAs($admin)->putJson(route('admin.desarrollo.bugs.update', $bug), $this->datos(['estado' => 'en_progreso']))->assertOk();
        $this->assertSame($dev->id, $bug->fresh()->responsable_id);

        $this->actingAs($admin)->putJson(route('admin.desarrollo.bugs.update', $bug), $this->datos(['responsable_id' => null]))->assertOk();
        $this->assertNull($bug->fresh()->responsable_id);
    }

    public function test_borrar_al_usuario_deja_el_bug_sin_asignar(): void
    {
        $dev = User::factory()->create();
        $bug = Bug::create(['proyecto_id' => $this->proyecto()->id, 'responsable_id' => $dev->id, 'titulo' => 'X', 'prioridad' => 'media', 'estado' => 'abierto']);

        $dev->delete();

        $this->assertNotNull(Bug::find($bug->id));
        $this->assertNull($bug->fresh()->responsable_id);
    }

    public function test_indice_global_incluye_responsable(): void
    {
        $dev = User::factory()->create(['name' => 'Beto']);
        Bug::create(['proyecto_id' => $this->proyecto()->id, 'responsable_id' => $dev->id, 'titulo' => 'X', 'prioridad' => 'media', 'estado' => 'abierto']);

        $this->actingAs(User::factory()->create())->getJson(route('admin.desarrollo.bugs.index'))
            ->assertOk()->assertJsonPath('0.responsable_nombre', 'Beto');
    }

    public function test_pagina_de_desarrollo_muestra_columna_y_selector_asignado(): void
    {
        $dev = User::factory()->create(['name' => 'Carla Asignada']);
        $inactivo = User::factory()->create(['name' => 'Dario Inactivo', 'is_active' => false]);
        $p = $this->proyecto();
        Bug::create(['proyecto_id' => $p->id, 'responsable_id' => $dev->id, 'titulo' => 'X', 'prioridad' => 'media', 'estado' => 'abierto']);
        $admin = User::factory()->create();
        // La fase Control lleva su propia fila y es la unica que muestra la tarjeta de bugs.
        $p->control()->create(['checklist' => []]);

        $this->actingAs($admin)->get(route('admin.desarrollo.index'))
            ->assertOk()->assertSee('Asignado')->assertSee('gb_responsable_id', false)
            ->assertSee('Carla Asignada')->assertDontSee('Dario Inactivo');

        $this->actingAs($admin)->get(route('admin.desarrollo.show', $p))
            ->assertOk()->assertSee('b_responsable_id', false)->assertSee('Carla Asignada');
    }

    public function test_api_v1_acepta_responsable_de_forma_opcional(): void
    {
        $dev = User::factory()->create(['name' => 'Eva', 'is_active' => true]);
        $p = $this->proyecto();
        // Misma autenticacion que tests/Feature/Api/DesarrolloTest.
        Sanctum::actingAs($dev, ['*']);

        $this->postJson("/api/v1/desarrollo/proyectos/{$p->id}/bugs", $this->datos())->assertCreated();
        $this->postJson("/api/v1/desarrollo/proyectos/{$p->id}/bugs", $this->datos(['responsable_id' => $dev->id]))
            ->assertCreated()->assertJsonPath('data.responsable_nombre', 'Eva');
    }
}
