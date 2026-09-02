<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ClientesTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->get(route('admin.clientes.index'));

        $response->assertRedirect('/login');
    }

    public function test_store_creates_client_and_returns_row_json(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.clientes.store'), [
            'nombre' => 'Cliente de Prueba',
            'empresa' => 'Empresa SA',
            'email' => 'nuevo@example.com',
            'telefono' => '555-1234',
            'contacto_nombre' => 'Juan Perez',
            'estado' => 'activo',
            'fecha_inicio_contrato' => '2026-01-01',
            'fecha_renovacion_contrato' => '2027-01-01',
            'forma_pago' => 'mensual',
            'metodo_pago' => 'transferencia',
            'notas' => 'Notas de prueba',
        ]);

        $response->assertCreated();
        $response->assertJson([
            'nombre' => 'Cliente de Prueba',
            'estado' => 'activo',
            'empresa' => 'Empresa SA',
            'email' => 'nuevo@example.com',
        ]);
        $response->assertJsonStructure([
            'id', 'nombre', 'empresa', 'email', 'telefono', 'contacto_nombre',
            'estado', 'servicios', 'mrr', 'fecha_inicio_contrato',
            'fecha_renovacion_contrato', 'forma_pago', 'metodo_pago', 'notas',
        ]);

        $this->assertDatabaseHas('clientes', [
            'nombre' => 'Cliente de Prueba',
            'email' => 'nuevo@example.com',
            'estado' => 'activo',
        ]);
    }

    public function test_store_requires_nombre(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.clientes.store'), [
            'estado' => 'activo',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('nombre');
    }

    public function test_store_rejects_invalid_estado(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.clientes.store'), [
            'nombre' => 'Cliente Invalido',
            'estado' => 'invalido',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('estado');
    }

    public function test_store_rejects_duplicate_email(): void
    {
        $user = User::factory()->create();
        Cliente::factory()->create(['email' => 'duplicado@example.com']);

        $response = $this->actingAs($user)->postJson(route('admin.clientes.store'), [
            'nombre' => 'Otro Cliente',
            'estado' => 'activo',
            'email' => 'duplicado@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_store_allows_email_of_a_soft_deleted_client(): void
    {
        $user = User::factory()->create();
        $borrado = Cliente::factory()->create(['email' => 'reutilizable@example.com']);
        $borrado->delete();

        $response = $this->actingAs($user)->postJson(route('admin.clientes.store'), [
            'nombre' => 'Cliente Nuevo',
            'estado' => 'activo',
            'email' => 'reutilizable@example.com',
        ]);

        $response->assertCreated();
    }

    public function test_update_does_not_422_when_email_unchanged(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create(['email' => 'sin-cambios@example.com']);

        $response = $this->actingAs($user)->putJson(route('admin.clientes.update', $cliente), [
            'nombre' => 'Nombre Actualizado',
            'estado' => 'activo',
            'email' => 'sin-cambios@example.com',
        ]);

        $response->assertOk();
        $response->assertJsonMissingValidationErrors('email');
        $this->assertDatabaseHas('clientes', [
            'id' => $cliente->id,
            'nombre' => 'Nombre Actualizado',
            'email' => 'sin-cambios@example.com',
        ]);
    }

    public function test_update_rejects_email_taken_by_another_client(): void
    {
        $user = User::factory()->create();
        $clienteA = Cliente::factory()->create(['email' => 'clientea@example.com']);
        $clienteB = Cliente::factory()->create(['email' => 'clienteb@example.com']);

        $response = $this->actingAs($user)->putJson(route('admin.clientes.update', $clienteA), [
            'nombre' => $clienteA->nombre,
            'estado' => 'activo',
            'email' => 'clienteb@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_store_rejects_invalid_forma_pago_and_metodo_pago(): void
    {
        $user = User::factory()->create();

        $responseForma = $this->actingAs($user)->postJson(route('admin.clientes.store'), [
            'nombre' => 'Cliente Forma Pago',
            'estado' => 'activo',
            'forma_pago' => 'invalido',
        ]);

        $responseForma->assertStatus(422);
        $responseForma->assertJsonValidationErrors('forma_pago');

        $responseMetodo = $this->actingAs($user)->postJson(route('admin.clientes.store'), [
            'nombre' => 'Cliente Metodo Pago',
            'estado' => 'activo',
            'metodo_pago' => 'invalido',
        ]);

        $responseMetodo->assertStatus(422);
        $responseMetodo->assertJsonValidationErrors('metodo_pago');
    }

    public function test_destroy_soft_deletes_and_returns_json(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->deleteJson(route('admin.clientes.destroy', $cliente));

        $response->assertOk();
        $response->assertJson(['deleted' => true]);
        $this->assertSoftDeleted('clientes', ['id' => $cliente->id]);
    }

    public function test_old_create_and_edit_routes_no_longer_exist(): void
    {
        $this->assertFalse(Route::has('admin.clientes.create'));
        $this->assertFalse(Route::has('admin.clientes.edit'));
    }
}
