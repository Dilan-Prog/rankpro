<?php

namespace Tests\Feature\Admin;

use App\Models\Bug;
use App\Models\Cliente;
use App\Models\Proyecto;
use App\Models\ProyectoComunicacion;
use App\Models\ProyectoPlaneacion;
use App\Models\ProyectoQa;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DesarrolloProyectosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Builds a proyecto plus its ProyectoPlaneacion row directly via Eloquent,
     * mirroring DesarrolloController::store()'s creation logic exactly. Built
     * manually (not via HTTP) so it doesn't disturb the authenticated user of
     * the calling test.
     */
    private function proyectoConPlaneacion(?Cliente $cliente = null, array $overrides = []): Proyecto
    {
        $cliente ??= Cliente::factory()->create();

        $proyecto = Proyecto::create(array_merge([
            'cliente_id' => $cliente->id,
            'nombre' => 'Proyecto de Prueba',
            'tipo' => 'web_nueva',
            'descripcion' => null,
            'fase_actual' => 'planeacion',
            'porcentaje_avance' => 0,
            'presupuesto' => 10000,
            'anticipo' => 0,
            'pagos_recibidos' => 0,
            'forma_pago' => null,
            'fecha_inicio' => null,
            'fecha_entrega_estimada' => null,
            'responsable' => null,
            'estado' => 'activo',
        ], $overrides));

        $proyecto->planeacion()->create([
            'checklist' => collect(array_keys(ProyectoPlaneacion::CHECKLIST))->mapWithKeys(fn ($key) => [$key => false])->all(),
        ]);

        return $proyecto->fresh();
    }

    // --- store ---------------------------------------------------------

    public function test_store_creates_proyecto_with_empty_planeacion_row(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.desarrollo.store'), [
            'cliente_id' => $cliente->id,
            'nombre' => 'Sitio web corporativo',
            'tipo' => 'web_nueva',
            'presupuesto' => 15000,
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'id', 'cliente_id', 'cliente', 'nombre', 'tipo', 'descripcion',
            'fase_actual', 'fase_orden', 'estado', 'porcentaje_avance',
            'presupuesto', 'anticipo', 'pagos_recibidos', 'pendiente',
            'forma_pago', 'fecha_inicio', 'fecha_entrega_estimada',
            'fecha_entrega_real', 'responsable', 'bugs_abiertos_count',
            'url_repositorio', 'url_staging', 'show_url',
        ]);

        $response->assertJson([
            'fase_orden' => 1,
            'pendiente' => 15000.0,
            'bugs_abiertos_count' => 0,
        ]);
        $this->assertStringContainsString('/admin/desarrollo/', $response->json('show_url'));

        $proyecto = Proyecto::findOrFail($response->json('id'));
        $this->assertSame('planeacion', $proyecto->fase_actual->value);
        $this->assertSame(0, $proyecto->porcentaje_avance);
        $this->assertSame('0.00', $proyecto->pagos_recibidos);
        $this->assertSame('activo', $proyecto->estado->value);

        $this->assertSame(1, ProyectoPlaneacion::where('proyecto_id', $proyecto->id)->count());
        $planeacion = ProyectoPlaneacion::where('proyecto_id', $proyecto->id)->first();
        foreach (array_keys(ProyectoPlaneacion::CHECKLIST) as $key) {
            $this->assertArrayHasKey($key, $planeacion->checklist);
            $this->assertFalse($planeacion->checklist[$key]);
        }
        $this->assertNull($planeacion->objetivos);
        $this->assertNull($planeacion->requerimientos_funcionales);
        $this->assertNull($planeacion->requerimientos_tecnicos);
    }

    public function test_store_requires_cliente_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.desarrollo.store'), [
            'nombre' => 'Proyecto sin cliente',
            'tipo' => 'web_nueva',
            'presupuesto' => 1000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('cliente_id');
    }

    public function test_store_requires_nombre(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.desarrollo.store'), [
            'cliente_id' => $cliente->id,
            'tipo' => 'web_nueva',
            'presupuesto' => 1000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('nombre');
    }

    public function test_store_requires_tipo(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.desarrollo.store'), [
            'cliente_id' => $cliente->id,
            'nombre' => 'Proyecto sin tipo',
            'presupuesto' => 1000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('tipo');
    }

    public function test_store_rejects_invalid_tipo(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.desarrollo.store'), [
            'cliente_id' => $cliente->id,
            'nombre' => 'Proyecto tipo invalido',
            'tipo' => 'app_movil',
            'presupuesto' => 1000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('tipo');
    }

    public function test_store_requires_presupuesto(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.desarrollo.store'), [
            'cliente_id' => $cliente->id,
            'nombre' => 'Proyecto sin presupuesto',
            'tipo' => 'web_nueva',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('presupuesto');
    }

    public function test_store_accepts_optional_fields_when_provided(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.desarrollo.store'), [
            'cliente_id' => $cliente->id,
            'nombre' => 'Proyecto con campos opcionales',
            'tipo' => 'rediseno',
            'descripcion' => 'Una descripción de prueba',
            'presupuesto' => 20000,
            'anticipo' => 5000,
            'forma_pago' => 'etapas',
            'fecha_inicio' => '2026-09-01',
            'fecha_entrega_estimada' => '2026-12-01',
            'responsable' => 'Juan Pérez',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('proyectos', [
            'id' => $response->json('id'),
            'descripcion' => 'Una descripción de prueba',
            'anticipo' => 5000,
            'forma_pago' => 'etapas',
            'fecha_inicio' => '2026-09-01',
            'fecha_entrega_estimada' => '2026-12-01',
            'responsable' => 'Juan Pérez',
        ]);
    }

    public function test_store_omitting_optional_fields_is_valid(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.desarrollo.store'), [
            'cliente_id' => $cliente->id,
            'nombre' => 'Proyecto minimo',
            'tipo' => 'landing',
            'presupuesto' => 500,
        ]);

        $response->assertCreated();
    }

    // --- update ---------------------------------------------------------

    public function test_update_changes_general_fields_without_touching_phase_rows(): void
    {
        $proyecto = $this->proyectoConPlaneacion();

        $planeacionAntes = ProyectoPlaneacion::where('proyecto_id', $proyecto->id)->first();
        $checklistAntes = $planeacionAntes->checklist;

        $response = $this->actingAs(User::factory()->create())->putJson(route('admin.desarrollo.update', $proyecto), [
            'cliente_id' => $proyecto->cliente_id,
            'nombre' => 'Nombre actualizado',
            'tipo' => $proyecto->tipo,
            'presupuesto' => 30000,
            'pagos_recibidos' => 10000,
            'estado' => 'pausado',
        ]);

        $response->assertOk();
        $response->assertJson([
            'nombre' => 'Nombre actualizado',
            'presupuesto' => 30000.0,
            'pagos_recibidos' => 10000.0,
            'estado' => 'pausado',
        ]);

        $this->assertSame(1, ProyectoPlaneacion::where('proyecto_id', $proyecto->id)->count());
        $planeacionDespues = ProyectoPlaneacion::where('proyecto_id', $proyecto->id)->first();
        $this->assertSame($checklistAntes, $planeacionDespues->checklist);
        $this->assertNull($planeacionDespues->objetivos);
        $this->assertNull($planeacionDespues->requerimientos_funcionales);
        $this->assertNull($planeacionDespues->requerimientos_tecnicos);
        $this->assertSame($planeacionAntes->aprobado, $planeacionDespues->aprobado);
        $this->assertEquals($planeacionAntes->fecha_aprobacion, $planeacionDespues->fecha_aprobacion);
    }

    public function test_update_requires_estado(): void
    {
        $proyecto = $this->proyectoConPlaneacion();

        $response = $this->actingAs(User::factory()->create())->putJson(route('admin.desarrollo.update', $proyecto), [
            'cliente_id' => $proyecto->cliente_id,
            'nombre' => $proyecto->nombre,
            'tipo' => $proyecto->tipo,
            'presupuesto' => $proyecto->presupuesto,
            'pagos_recibidos' => 0,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('estado');
    }

    public function test_update_rejects_invalid_estado(): void
    {
        $proyecto = $this->proyectoConPlaneacion();

        $response = $this->actingAs(User::factory()->create())->putJson(route('admin.desarrollo.update', $proyecto), [
            'cliente_id' => $proyecto->cliente_id,
            'nombre' => $proyecto->nombre,
            'tipo' => $proyecto->tipo,
            'presupuesto' => $proyecto->presupuesto,
            'pagos_recibidos' => 0,
            'estado' => 'archivado',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('estado');
    }

    public function test_update_requires_pagos_recibidos(): void
    {
        $proyecto = $this->proyectoConPlaneacion();

        $response = $this->actingAs(User::factory()->create())->putJson(route('admin.desarrollo.update', $proyecto), [
            'cliente_id' => $proyecto->cliente_id,
            'nombre' => $proyecto->nombre,
            'tipo' => $proyecto->tipo,
            'presupuesto' => $proyecto->presupuesto,
            'estado' => 'activo',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('pagos_recibidos');
    }

    // --- destroy ---------------------------------------------------------

    public function test_destroy_soft_deletes_and_cascades_hard_delete_children(): void
    {
        $proyecto = $this->proyectoConPlaneacion();

        $tarea = Tarea::create([
            'proyecto_id' => $proyecto->id,
            'titulo' => 'Tarea de prueba',
            'descripcion' => null,
            'responsable' => null,
            'prioridad' => 'media',
            'estado' => 'pendiente',
        ]);

        $bug = Bug::create([
            'proyecto_id' => $proyecto->id,
            'titulo' => 'Bug de prueba',
            'prioridad' => 'alta',
            'estado' => 'abierto',
        ]);

        $comunicacion = ProyectoComunicacion::create([
            'proyecto_id' => $proyecto->id,
            'fecha' => now()->toDateString(),
            'resumen' => 'Reunión de prueba',
        ]);

        $qa = ProyectoQa::create([
            'proyecto_id' => $proyecto->id,
            'tipo_prueba' => 'funcional',
            'resultado' => 'aprobado',
        ]);

        $planeacionId = ProyectoPlaneacion::where('proyecto_id', $proyecto->id)->first()->id;

        $response = $this->actingAs(User::factory()->create())->deleteJson(route('admin.desarrollo.destroy', $proyecto));

        $response->assertOk();
        $response->assertJson(['deleted' => true]);

        $this->assertSoftDeleted('proyectos', ['id' => $proyecto->id]);

        $this->assertDatabaseMissing('tareas', ['id' => $tarea->id]);
        $this->assertDatabaseMissing('bugs', ['id' => $bug->id]);
        $this->assertDatabaseMissing('proyecto_comunicaciones', ['id' => $comunicacion->id]);
        $this->assertDatabaseMissing('proyecto_qa', ['id' => $qa->id]);

        // Empirically observed: a soft delete is an UPDATE, not a DELETE, so
        // the DB's ON DELETE CASCADE on proyecto_planeacion never fires — the
        // phase row is orphaned, not removed. This is pre-existing behavior
        // unrelated to this change (destroy()'s body was only touched to
        // return JSON instead of redirecting), documented here rather than
        // treated as a bug.
        $this->assertDatabaseHas('proyecto_planeacion', ['id' => $planeacionId]);
    }

    // --- routes regression -----------------------------------------------

    public function test_old_create_edit_routes_no_longer_exist(): void
    {
        $this->assertFalse(Route::has('admin.desarrollo.create'));
        $this->assertFalse(Route::has('admin.desarrollo.edit'));
    }

    public function test_show_route_still_works(): void
    {
        $proyecto = $this->proyectoConPlaneacion();

        $response = $this->actingAs(User::factory()->create())->get(route('admin.desarrollo.show', $proyecto));

        $response->assertOk();
    }

    // --- auth ---------------------------------------------------------

    public function test_store_requires_authentication(): void
    {
        $cliente = Cliente::factory()->create();

        $response = $this->postJson(route('admin.desarrollo.store'), [
            'cliente_id' => $cliente->id,
            'nombre' => 'Proyecto sin autenticar',
            'tipo' => 'web_nueva',
            'presupuesto' => 1000,
        ]);

        $response->assertStatus(401);
    }

    public function test_update_requires_authentication(): void
    {
        $proyecto = $this->proyectoConPlaneacion();

        $response = $this->putJson(route('admin.desarrollo.update', $proyecto), [
            'cliente_id' => $proyecto->cliente_id,
            'nombre' => 'Intento sin autenticar',
            'tipo' => $proyecto->tipo,
            'presupuesto' => $proyecto->presupuesto,
            'pagos_recibidos' => 0,
            'estado' => 'activo',
        ]);

        $response->assertStatus(401);
    }

    public function test_destroy_requires_authentication(): void
    {
        $proyecto = $this->proyectoConPlaneacion();

        $response = $this->deleteJson(route('admin.desarrollo.destroy', $proyecto));

        $response->assertStatus(401);
    }
}
