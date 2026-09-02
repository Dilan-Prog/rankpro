<?php

namespace Tests\Feature\Admin;

use App\Models\Bug;
use App\Models\Cliente;
use App\Models\Proyecto;
use App\Models\ProyectoPlaneacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesarrolloBugsIndexTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Builds a proyecto plus its ProyectoPlaneacion row directly via Eloquent,
     * mirroring DesarrolloController::store()'s creation logic. Built manually
     * (not via HTTP) so it doesn't disturb the authenticated user of the
     * calling test.
     */
    private function proyectoConPlaneacion(?Cliente $cliente = null, array $overrides = []): Proyecto
    {
        $cliente ??= Cliente::factory()->create();

        $proyecto = Proyecto::create(array_merge([
            'cliente_id' => $cliente->id,
            'nombre' => 'Proyecto de Prueba',
            'tipo' => 'web_nueva',
            'fase_actual' => 'planeacion',
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

    private function bug(Proyecto $proyecto, array $overrides = []): Bug
    {
        return Bug::create(array_merge([
            'proyecto_id' => $proyecto->id,
            'titulo' => 'Bug de prueba',
            'descripcion' => null,
            'prioridad' => 'media',
            'estado' => 'abierto',
        ], $overrides));
    }

    public function test_bugs_index_returns_bugs_from_multiple_projects(): void
    {
        $user = User::factory()->create();
        $proyectoA = $this->proyectoConPlaneacion(null, ['nombre' => 'Proyecto A']);
        $proyectoB = $this->proyectoConPlaneacion(null, ['nombre' => 'Proyecto B']);

        $bugA1 = $this->bug($proyectoA, ['titulo' => 'Bug A1']);
        $bugA2 = $this->bug($proyectoA, ['titulo' => 'Bug A2']);
        $bugB1 = $this->bug($proyectoB, ['titulo' => 'Bug B1']);

        $response = $this->actingAs($user)->getJson(route('admin.desarrollo.bugs.index'));

        $response->assertOk();
        $ids = collect($response->json())->pluck('id')->all();
        $this->assertContains($bugA1->id, $ids);
        $this->assertContains($bugA2->id, $ids);
        $this->assertContains($bugB1->id, $ids);

        $rows = collect($response->json())->keyBy('id');
        $this->assertSame('Proyecto A', $rows[$bugA1->id]['proyecto_nombre']);
        $this->assertSame('Proyecto A', $rows[$bugA2->id]['proyecto_nombre']);
        $this->assertSame('Proyecto B', $rows[$bugB1->id]['proyecto_nombre']);
    }

    public function test_bugs_index_filters_by_proyecto_id(): void
    {
        $user = User::factory()->create();
        $proyectoA = $this->proyectoConPlaneacion(null, ['nombre' => 'Proyecto A']);
        $proyectoB = $this->proyectoConPlaneacion(null, ['nombre' => 'Proyecto B']);

        $bugA = $this->bug($proyectoA);
        $bugB = $this->bug($proyectoB);

        $response = $this->actingAs($user)->getJson(route('admin.desarrollo.bugs.index', ['proyecto_id' => $proyectoA->id]));

        $response->assertOk();
        $ids = collect($response->json())->pluck('id')->all();
        $this->assertContains($bugA->id, $ids);
        $this->assertNotContains($bugB->id, $ids);
    }

    public function test_bugs_index_filters_by_prioridad(): void
    {
        $user = User::factory()->create();
        $proyecto = $this->proyectoConPlaneacion();

        $bugAlta = $this->bug($proyecto, ['titulo' => 'Bug alta', 'prioridad' => 'alta']);
        $bugBaja = $this->bug($proyecto, ['titulo' => 'Bug baja', 'prioridad' => 'baja']);

        $response = $this->actingAs($user)->getJson(route('admin.desarrollo.bugs.index', ['prioridad' => 'alta']));

        $response->assertOk();
        $ids = collect($response->json())->pluck('id')->all();
        $this->assertContains($bugAlta->id, $ids);
        $this->assertNotContains($bugBaja->id, $ids);
    }

    public function test_bugs_index_filters_by_estado(): void
    {
        $user = User::factory()->create();
        $proyecto = $this->proyectoConPlaneacion();

        $bugResuelto = $this->bug($proyecto, ['titulo' => 'Bug resuelto', 'estado' => 'resuelto', 'fecha_resolucion' => now()->toDateString()]);
        $bugAbierto = $this->bug($proyecto, ['titulo' => 'Bug abierto', 'estado' => 'abierto']);

        $response = $this->actingAs($user)->getJson(route('admin.desarrollo.bugs.index', ['estado' => 'resuelto']));

        $response->assertOk();
        $ids = collect($response->json())->pluck('id')->all();
        $this->assertContains($bugResuelto->id, $ids);
        $this->assertNotContains($bugAbierto->id, $ids);

        $rows = collect($response->json())->keyBy('id');
        $this->assertNull($rows[$bugResuelto->id]['dias_abierto']);
    }

    public function test_bugs_index_excludes_bugs_of_soft_deleted_proyecto(): void
    {
        $user = User::factory()->create();
        $proyecto = $this->proyectoConPlaneacion();
        $bug = $this->bug($proyecto);

        $proyecto->delete();

        $response = $this->actingAs($user)->getJson(route('admin.desarrollo.bugs.index'));

        $response->assertOk();
        $ids = collect($response->json())->pluck('id')->all();
        $this->assertNotContains($bug->id, $ids);
    }

    public function test_bugs_index_requires_authentication(): void
    {
        $response = $this->getJson(route('admin.desarrollo.bugs.index'));

        $response->assertStatus(401);
    }

    public function test_bug_store_and_update_responses_include_proyecto_nombre(): void
    {
        $user = User::factory()->create();
        $proyecto = $this->proyectoConPlaneacion(null, ['nombre' => 'Proyecto con bugs']);

        $storeResponse = $this->actingAs($user)->postJson(route('admin.desarrollo.bugs.store', $proyecto), [
            'titulo' => 'Bug nuevo',
            'prioridad' => 'alta',
            'estado' => 'abierto',
        ]);

        $storeResponse->assertCreated();
        $storeResponse->assertJson(['proyecto_nombre' => 'Proyecto con bugs']);

        $bugId = $storeResponse->json('id');

        $updateResponse = $this->actingAs($user)->putJson(route('admin.desarrollo.bugs.update', $bugId), [
            'titulo' => 'Bug actualizado',
            'prioridad' => 'media',
            'estado' => 'resuelto',
            'fecha_resolucion' => now()->toDateString(),
        ]);

        $updateResponse->assertOk();
        $updateResponse->assertJson(['proyecto_nombre' => 'Proyecto con bugs']);
    }
}
