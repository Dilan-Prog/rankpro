<?php

namespace Tests\Feature\Api;

use App\Enums\EstadoBug;
use App\Enums\FaseProyecto;
use App\Models\Bug;
use App\Models\Cliente;
use App\Models\Proyecto;
use App\Models\ProyectoPlaneacion;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DesarrolloTest extends TestCase
{
    use RefreshDatabase;

    /** Mismo helper que Admin\DesarrolloProyectosTest::proyectoConPlaneacion(). */
    private function proyecto(array $overrides = []): Proyecto
    {
        $proyecto = Proyecto::create(array_merge([
            'cliente_id' => Cliente::factory()->create()->id,
            'nombre' => 'Proyecto API',
            'tipo' => 'web_nueva',
            'fase_actual' => FaseProyecto::Planeacion->value,
            'porcentaje_avance' => 0,
            'presupuesto' => 10000,
            'anticipo' => 0,
            'pagos_recibidos' => 0,
            'estado' => 'activo',
        ], $overrides));

        $proyecto->planeacion()->create([
            'checklist' => collect(array_keys(ProyectoPlaneacion::CHECKLIST))->mapWithKeys(fn ($key) => [$key => false])->all(),
        ]);

        return $proyecto->fresh();
    }

    public function test_proyectos_index_requiere_token(): void
    {
        $this->getJson('/api/v1/desarrollo/proyectos')->assertUnauthorized();
    }

    public function test_proyectos_store(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['desarrollo:escribir']);
        $cliente = Cliente::factory()->create();

        $response = $this->postJson('/api/v1/desarrollo/proyectos', [
            'cliente_id' => $cliente->id,
            'nombre' => 'Sitio nuevo',
            'tipo' => 'web_nueva',
            'presupuesto' => 20000,
        ])->assertCreated();

        $this->assertSame(FaseProyecto::Planeacion->value, $response->json('data.fase_actual'));
        $this->assertDatabaseHas('proyecto_planeacion', ['proyecto_id' => $response->json('data.id')]);
    }

    public function test_proyectos_store_sin_habilidad_de_escritura_da_403(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['desarrollo:leer']);

        $this->postJson('/api/v1/desarrollo/proyectos', [
            'cliente_id' => Cliente::factory()->create()->id,
            'nombre' => 'x',
            'tipo' => 'web_nueva',
            'presupuesto' => 100,
        ])->assertForbidden();
    }

    public function test_proyectos_show_incluye_relaciones_bajo_demanda(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $proyecto = $this->proyecto();

        $response = $this->getJson("/api/v1/desarrollo/proyectos/{$proyecto->id}?incluir=tareas")->assertOk();

        $this->assertArrayHasKey('tareas', $response->json('data'));
    }

    // --- fase ------------------------------------------------------------

    public function test_fase_ver_guardar_y_aprobar(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $proyecto = $this->proyecto();

        $this->getJson("/api/v1/desarrollo/proyectos/{$proyecto->id}/fase")
            ->assertOk()
            ->assertJsonPath('data.fase_actual', FaseProyecto::Planeacion->value);

        // Completa el checklist de Planeación para poder aprobar.
        $checklist = collect(array_keys(ProyectoPlaneacion::CHECKLIST))->mapWithKeys(fn ($k) => [$k => true])->all();
        $this->postJson("/api/v1/desarrollo/proyectos/{$proyecto->id}/fase/guardar", ['checklist' => $checklist])
            ->assertOk();

        $this->postJson("/api/v1/desarrollo/proyectos/{$proyecto->id}/fase/aprobar")
            ->assertOk()
            ->assertJsonPath('data.fase_actual', FaseProyecto::Organizacion->value);

        $this->postJson("/api/v1/desarrollo/proyectos/{$proyecto->id}/fase/retroceder")
            ->assertOk()
            ->assertJsonPath('data.fase_actual', FaseProyecto::Planeacion->value);
    }

    public function test_fase_aprobar_con_checklist_incompleto_da_422(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $proyecto = $this->proyecto();

        $this->postJson("/api/v1/desarrollo/proyectos/{$proyecto->id}/fase/aprobar")->assertStatus(422);
    }

    // --- tareas / bugs / qa / comunicaciones ------------------------------

    public function test_tareas_store_update_completar_destroy(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $proyecto = $this->proyecto();

        $store = $this->postJson("/api/v1/desarrollo/proyectos/{$proyecto->id}/tareas", [
            'titulo' => 'Maquetar home',
            'prioridad' => 'alta',
            'estado' => 'pendiente',
        ])->assertCreated();

        $id = $store->json('data.id');

        $this->postJson("/api/v1/desarrollo/tareas/{$id}/completar")
            ->assertOk()
            ->assertJsonPath('data.estado', 'completada');

        $this->deleteJson("/api/v1/desarrollo/tareas/{$id}")->assertOk();
        $this->assertDatabaseMissing('tareas', ['id' => $id]);
    }

    public function test_bugs_index_global_y_resolver(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $proyecto = $this->proyecto();
        $bug = Bug::create([
            'proyecto_id' => $proyecto->id,
            'titulo' => 'Botón roto',
            'prioridad' => 'alta',
            'estado' => EstadoBug::Abierto->value,
        ]);

        $this->getJson('/api/v1/desarrollo/bugs')->assertOk()->assertJsonCount(1, 'data');

        $this->postJson("/api/v1/desarrollo/bugs/{$bug->id}/resolver")
            ->assertOk()
            ->assertJsonPath('data.estado', EstadoBug::Resuelto->value);
    }

    public function test_qa_store_y_comunicaciones_store(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $proyecto = $this->proyecto();

        $this->postJson("/api/v1/desarrollo/proyectos/{$proyecto->id}/qa", [
            'tipo_prueba' => 'funcional',
            'resultado' => 'aprobado',
        ])->assertCreated();

        $this->postJson("/api/v1/desarrollo/proyectos/{$proyecto->id}/comunicaciones", [
            'fecha' => '2026-09-21',
            'resumen' => 'Llamada de seguimiento con el cliente.',
        ])->assertCreated();

        $this->assertDatabaseCount('proyecto_qa', 1);
        $this->assertDatabaseCount('proyecto_comunicaciones', 1);
    }
}
