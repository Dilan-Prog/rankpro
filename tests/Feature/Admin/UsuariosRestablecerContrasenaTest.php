<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** «Restablecer contraseña» de la ficha de Usuarios: envía el enlace del broker, nunca fija ni muestra una contraseña. */
class UsuariosRestablecerContrasenaTest extends TestCase
{
    use RefreshDatabase;

    private function url(User $u): string
    {
        return route('admin.usuarios.restablecer-contrasena', ['user' => $u->id]);
    }

    public function test_envia_el_enlace_solo_al_usuario_indicado(): void
    {
        Notification::fake();
        $admin = User::factory()->create();
        $objetivo = User::factory()->create(['is_active' => true]);
        $otro = User::factory()->create(['is_active' => true]);
        $claveAntes = $objetivo->password;

        $this->actingAs($admin)->postJson($this->url($objetivo))
            ->assertOk()
            ->assertJsonPath('message', "Enlace de restablecimiento enviado a {$objetivo->email}.");

        Notification::assertSentTo($objetivo, ResetPassword::class);
        Notification::assertNotSentTo($otro, ResetPassword::class);
        Notification::assertNotSentTo($admin, ResetPassword::class);
        $this->assertSame($claveAntes, $objetivo->fresh()->password);
    }

    public function test_rechaza_a_un_usuario_inactivo(): void
    {
        Notification::fake();
        $inactivo = User::factory()->create(['is_active' => false]);

        $this->actingAs(User::factory()->create())->postJson($this->url($inactivo))->assertStatus(422);

        Notification::assertNothingSent();
    }

    public function test_el_throttle_del_broker_evita_reenvios_seguidos(): void
    {
        Notification::fake();
        $objetivo = User::factory()->create(['is_active' => true]);
        $admin = User::factory()->create();

        $this->actingAs($admin)->postJson($this->url($objetivo))->assertOk();
        $this->actingAs($admin)->postJson($this->url($objetivo))->assertStatus(429);

        Notification::assertSentToTimes($objetivo, ResetPassword::class, 1);
    }

    public function test_requiere_el_permiso_de_usuarios_cuando_los_permisos_estan_encendidos(): void
    {
        config(['permisos.aplicar' => true]);
        Notification::fake();
        $objetivo = User::factory()->create(['is_active' => true]);

        $rolSin = Role::query()->create(['name' => 'operador', 'label' => 'Operador']);
        $sin = User::factory()->create(['role_id' => $rolSin->id, 'is_active' => true]);
        $this->actingAs($sin)->postJson($this->url($objetivo))->assertForbidden();
        Notification::assertNothingSent();

        $rolCon = Role::query()->create(['name' => 'gestor', 'label' => 'Gestor']);
        $permiso = Permission::query()->firstOrCreate(['name' => 'usuarios'], ['label' => 'usuarios', 'module' => 'usuarios']);
        $rolCon->permissions()->attach($permiso->id);
        $con = User::factory()->create(['role_id' => $rolCon->id, 'is_active' => true]);

        $this->actingAs($con)->postJson($this->url($objetivo))->assertOk();
        Notification::assertSentTo($objetivo, ResetPassword::class);
    }
}
