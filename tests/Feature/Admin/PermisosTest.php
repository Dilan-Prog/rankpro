<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los permisos por módulo se aplican en las rutas de /admin (middleware
 * `permiso:{modulo}`) y filtran el menú. El resto de tests los apaga en
 * Tests\TestCase; aquí se encienden a propósito.
 */
class PermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['permisos.aplicar' => true]);
    }

    private function usuarioConRol(string $nombreRol, array $modulos = []): User
    {
        $rol = Role::query()->create(['name' => $nombreRol, 'label' => ucfirst($nombreRol)]);

        foreach ($modulos as $modulo) {
            $permiso = Permission::query()->firstOrCreate(
                ['name' => $modulo],
                ['label' => $modulo, 'module' => $modulo]
            );
            $rol->permissions()->attach($permiso->id);
        }

        return User::factory()->create(['role_id' => $rol->id, 'is_active' => true]);
    }

    public function test_un_rol_sin_el_permiso_recibe_403_en_el_modulo(): void
    {
        $usuario = $this->usuarioConRol('operador', ['dashboard']);

        $this->actingAs($usuario)->get(route('admin.clientes.index'))->assertForbidden();
    }

    public function test_un_rol_con_el_permiso_entra_al_modulo(): void
    {
        $usuario = $this->usuarioConRol('operador', ['clientes']);

        $this->actingAs($usuario)->get(route('admin.clientes.index'))->assertOk();
    }

    public function test_el_administrador_entra_sin_filas_en_role_permissions(): void
    {
        $usuario = $this->usuarioConRol('admin');

        $this->actingAs($usuario)->get(route('admin.clientes.index'))->assertOk();
    }

    public function test_un_usuario_sin_rol_no_entra(): void
    {
        $usuario = User::factory()->create(['role_id' => null, 'is_active' => true]);

        $this->actingAs($usuario)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_con_los_permisos_apagados_cualquier_usuario_entra(): void
    {
        config(['permisos.aplicar' => false]);
        $usuario = User::factory()->create(['role_id' => null, 'is_active' => true]);

        $this->actingAs($usuario)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_el_menu_solo_ofrece_los_modulos_permitidos(): void
    {
        $usuario = $this->usuarioConRol('operador', ['dashboard', 'clientes']);
        $this->actingAs($usuario);

        $etiquetas = collect(Navigation::groups())->flatten(1)->pluck('label')->all();

        $this->assertContains('Dashboard', $etiquetas);
        $this->assertContains('Lista', $etiquetas);
        $this->assertNotContains('Finanzas', $etiquetas);
        $this->assertNotContains('Roles', $etiquetas);
    }

    public function test_la_migracion_da_a_admin_los_permisos_nuevos(): void
    {
        $admin = Role::query()->create(['name' => 'admin', 'label' => 'Administrador']);
        $migracion = require database_path('migrations/2026_11_01_100000_agregar_permisos_de_modulos_nuevos.php');
        $migracion->up();

        $this->assertTrue($admin->permissions()->where('name', 'mi-espacio')->exists());
        $this->assertTrue(Permission::query()->where('name', 'configuracion')->exists());

        // Correrla otra vez no duplica nada.
        $migracion->up();
        $this->assertSame(1, Permission::query()->where('name', 'mi-espacio')->count());
    }

    public function test_el_selector_de_cliente_activo_guarda_en_sesion(): void
    {
        $cliente = Cliente::factory()->create();
        $usuario = $this->usuarioConRol('admin');

        $this->actingAs($usuario)
            ->post(route('admin.cliente-activo'), ['cliente_id' => $cliente->id])
            ->assertRedirect();

        $this->assertSame($cliente->id, session('cliente_activo_id'));

        $this->actingAs($usuario)->post(route('admin.cliente-activo'), ['cliente_id' => null]);
        $this->assertNull(session('cliente_activo_id'));
    }
}
