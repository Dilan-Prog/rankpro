<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\Servicio;
use App\Models\ServicioEvento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ServiciosTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->get(route('admin.servicios.index'));

        $response->assertRedirect('/login');
    }

    public function test_store_creates_servicio_and_returns_row_json(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.servicios.store'), [
            'cliente_id' => $cliente->id,
            'tipo' => 'seo',
            'nombre' => 'SEO — Plan Base',
            'descripcion' => 'Servicio de prueba',
            'precio_mensual' => 8000,
            'estado' => 'activo',
            'fecha_inicio' => '2026-01-01',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'id', 'cliente_id', 'cliente', 'responsable_id', 'responsable_nombre',
            'nombre', 'descripcion', 'tipo', 'estado', 'fecha_inicio', 'fecha_fin',
            'precio_mensual', 'anualizado', 'meses_activos', 'ingreso_acumulado',
            'eventos',
        ]);
        $response->assertJson([
            'cliente_id' => $cliente->id,
            'tipo' => 'seo',
            'estado' => 'activo',
            'precio_mensual' => 8000,
        ]);

        $this->assertNotEmpty($response->json('eventos'));

        $this->assertDatabaseHas('servicios', [
            'cliente_id' => $cliente->id,
            'nombre' => 'SEO — Plan Base',
            'tipo' => 'seo',
            'estado' => 'activo',
        ]);
    }

    public function test_store_requires_cliente_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.servicios.store'), [
            'tipo' => 'seo',
            'nombre' => 'Servicio sin cliente',
            'precio_mensual' => 1000,
            'estado' => 'activo',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('cliente_id');
    }

    public function test_store_rejects_invalid_tipo(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.servicios.store'), [
            'cliente_id' => $cliente->id,
            'tipo' => 'invalido',
            'nombre' => 'Servicio tipo invalido',
            'precio_mensual' => 1000,
            'estado' => 'activo',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('tipo');
    }

    public function test_store_rejects_invalid_estado(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.servicios.store'), [
            'cliente_id' => $cliente->id,
            'tipo' => 'seo',
            'nombre' => 'Servicio estado invalido',
            'precio_mensual' => 1000,
            'estado' => 'invalido',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('estado');
    }

    public function test_store_rejects_fecha_fin_without_fecha_inicio(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.servicios.store'), [
            'cliente_id' => $cliente->id,
            'tipo' => 'seo',
            'nombre' => 'Servicio sin fecha inicio',
            'precio_mensual' => 1000,
            'estado' => 'activo',
            'fecha_fin' => '2026-06-01',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('fecha_inicio');
    }

    public function test_store_accepts_valid_responsable_id(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $responsable = User::factory()->create(['name' => 'Responsable de Prueba']);

        $response = $this->actingAs($user)->postJson(route('admin.servicios.store'), [
            'cliente_id' => $cliente->id,
            'responsable_id' => $responsable->id,
            'tipo' => 'seo',
            'nombre' => 'Servicio con responsable',
            'precio_mensual' => 1000,
            'estado' => 'activo',
        ]);

        $response->assertCreated();
        $response->assertJson([
            'responsable_id' => $responsable->id,
            'responsable_nombre' => 'Responsable de Prueba',
        ]);
    }

    public function test_store_rejects_invalid_responsable_id(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.servicios.store'), [
            'cliente_id' => $cliente->id,
            'responsable_id' => 99999,
            'tipo' => 'seo',
            'nombre' => 'Servicio responsable invalido',
            'precio_mensual' => 1000,
            'estado' => 'activo',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('responsable_id');
    }

    public function test_store_logs_creation_event(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.servicios.store'), [
            'cliente_id' => $cliente->id,
            'tipo' => 'seo',
            'nombre' => 'Servicio con evento',
            'precio_mensual' => 1000,
            'estado' => 'activo',
        ]);

        $response->assertCreated();
        $servicioId = $response->json('id');

        $this->assertDatabaseHas('servicio_eventos', [
            'servicio_id' => $servicioId,
        ]);

        $evento = ServicioEvento::where('servicio_id', $servicioId)->first();
        $this->assertNotNull($evento);
        $this->assertStringContainsString('creado', $evento->descripcion);
    }

    public function test_update_logs_event_on_estado_change(): void
    {
        $user = User::factory()->create();
        $servicio = Servicio::factory()->create(['estado' => 'activo']);

        $response = $this->actingAs($user)->putJson(route('admin.servicios.update', $servicio), [
            'cliente_id' => $servicio->cliente_id,
            'tipo' => $servicio->tipo->value,
            'nombre' => $servicio->nombre,
            'precio_mensual' => $servicio->precio_mensual,
            'estado' => 'pausado',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('servicio_eventos', [
            'servicio_id' => $servicio->id,
            'descripcion' => 'Estado cambiado a pausado.',
        ]);
    }

    public function test_update_logs_event_on_precio_change(): void
    {
        $user = User::factory()->create();
        $servicio = Servicio::factory()->create(['precio_mensual' => 1000]);

        $response = $this->actingAs($user)->putJson(route('admin.servicios.update', $servicio), [
            'cliente_id' => $servicio->cliente_id,
            'tipo' => $servicio->tipo->value,
            'nombre' => $servicio->nombre,
            'precio_mensual' => 2500,
            'estado' => $servicio->estado->value,
        ]);

        $response->assertOk();

        $evento = ServicioEvento::where('servicio_id', $servicio->id)
            ->where('descripcion', 'like', '%Precio mensual actualizado%')
            ->first();

        $this->assertNotNull($evento);
    }

    public function test_update_does_not_log_event_when_nothing_relevant_changed(): void
    {
        $user = User::factory()->create();
        $servicio = Servicio::factory()->create([
            'estado' => 'activo',
            'precio_mensual' => 1000,
            'nombre' => 'Nombre original',
        ]);

        // Original "Servicio creado." event exists from the factory-backed create.
        $this->assertSame(0, ServicioEvento::where('servicio_id', $servicio->id)->count());

        $response = $this->actingAs($user)->putJson(route('admin.servicios.update', $servicio), [
            'cliente_id' => $servicio->cliente_id,
            'tipo' => $servicio->tipo->value,
            'nombre' => 'Nombre actualizado',
            'precio_mensual' => $servicio->precio_mensual,
            'estado' => $servicio->estado->value,
        ]);

        $response->assertOk();

        $this->assertSame(0, ServicioEvento::where('servicio_id', $servicio->id)->count());
    }

    public function test_destroy_soft_deletes_and_returns_json(): void
    {
        $user = User::factory()->create();
        $servicio = Servicio::factory()->create();

        $response = $this->actingAs($user)->deleteJson(route('admin.servicios.destroy', $servicio));

        $response->assertOk();
        $response->assertJson(['deleted' => true]);
        $this->assertSoftDeleted('servicios', ['id' => $servicio->id]);
    }

    public function test_old_create_edit_show_routes_no_longer_exist(): void
    {
        $this->assertFalse(Route::has('admin.servicios.create'));
        $this->assertFalse(Route::has('admin.servicios.edit'));
        $this->assertFalse(Route::has('admin.servicios.show'));
    }
}
