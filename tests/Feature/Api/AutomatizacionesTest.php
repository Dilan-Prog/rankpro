<?php

namespace Tests\Feature\Api;

use App\Models\AutomatizacionFaseDiagnostico;
use App\Models\AutomatizacionProyecto;
use App\Models\Cliente;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AutomatizacionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requiere_habilidad_automatizaciones(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['ads:leer']);

        $this->getJson('/api/v1/automatizaciones/proyectos')->assertStatus(403);
    }

    public function test_store_crea_proyecto_con_las_4_filas_de_fase(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['automatizaciones:escribir']);
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'automatizacion']);

        $response = $this->postJson('/api/v1/automatizaciones/proyectos', [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Proyecto API',
        ])->assertStatus(201);

        $proyecto = AutomatizacionProyecto::latest('id')->first();
        $this->assertEquals(1, $proyecto->diagnosticos()->count());
        $this->assertEquals(1, $proyecto->disenos()->count());
        $this->assertEquals(1, $proyecto->implementaciones()->count());
        $this->assertEquals(1, $proyecto->reportes()->count());
        $response->assertJsonPath('data.fase_actual', 'diagnostico');
    }

    public function test_store_requiere_habilidad_escribir(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['automatizaciones:leer']);
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'automatizacion']);

        $this->postJson('/api/v1/automatizaciones/proyectos', [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => 'Proyecto API',
        ])->assertStatus(403);
    }

    public function test_update_y_destroy(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['automatizaciones:escribir']);
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'automatizacion']);
        $proyecto = AutomatizacionProyecto::create([
            'cliente_id' => $cliente->id, 'servicio_id' => $servicio->id, 'nombre' => 'P1',
            'estado' => 'activa', 'fase_actual' => 'diagnostico', 'ciclo_actual' => 1,
        ]);

        $this->putJson("/api/v1/automatizaciones/proyectos/{$proyecto->id}", [
            'cliente_id' => $cliente->id, 'servicio_id' => $servicio->id, 'nombre' => 'P1 editado', 'estado' => 'pausada',
        ])->assertOk()->assertJsonPath('data.nombre', 'P1 editado');

        $this->deleteJson("/api/v1/automatizaciones/proyectos/{$proyecto->id}")->assertOk();
        $this->assertSoftDeleted('automatizacion_proyectos', ['id' => $proyecto->id]);
    }

    public function test_flujos_crud(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['automatizaciones:escribir']);
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'automatizacion']);
        $proyecto = AutomatizacionProyecto::create([
            'cliente_id' => $cliente->id, 'servicio_id' => $servicio->id, 'nombre' => 'P1',
            'estado' => 'activa', 'fase_actual' => 'diagnostico', 'ciclo_actual' => 1,
        ]);

        $flujo = $this->postJson("/api/v1/automatizaciones/proyectos/{$proyecto->id}/flujos", [
            'nombre' => 'Flujo WhatsApp', 'tipo' => 'whatsapp', 'complejidad' => 'basico', 'estado' => 'activo',
        ])->assertStatus(201)->json('data');

        $this->putJson("/api/v1/automatizaciones/flujos/{$flujo['id']}", [
            'nombre' => 'Flujo WhatsApp editado', 'tipo' => 'whatsapp', 'complejidad' => 'basico', 'estado' => 'activo',
        ])->assertOk();

        $this->deleteJson("/api/v1/automatizaciones/flujos/{$flujo['id']}")->assertOk();
    }
}
