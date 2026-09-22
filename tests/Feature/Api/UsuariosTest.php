<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UsuariosTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_no_expone_password(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        User::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/usuarios')->assertOk();
        $response->assertJsonMissingPath('data.0.password');
        $response->assertJsonMissingPath('data.1.password');
    }

    public function test_show_no_expone_password(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $user = User::factory()->create();

        $this->getJson("/api/v1/usuarios/{$user->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.password');
    }

    public function test_store_sin_password_genera_una_temporal_y_la_devuelve_una_vez(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);

        $response = $this->postJson('/api/v1/usuarios', [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo.api@example.com',
        ]);

        $response->assertCreated();
        $temporal = $response->json('data.password_temporal');
        $this->assertNotEmpty($temporal);

        $creado = User::where('email', 'nuevo.api@example.com')->first();
        $this->assertTrue(Hash::check($temporal, $creado->password));
    }

    public function test_store_con_password_no_genera_temporal(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);

        $response = $this->postJson('/api/v1/usuarios', [
            'name' => 'Con Password',
            'email' => 'conpass.api@example.com',
            'password' => 'ClaveSegura123!',
        ]);

        $response->assertCreated();
        $this->assertArrayNotHasKey('password_temporal', $response->json('data'));
    }

    public function test_update_no_permite_cambiar_password(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $user = User::factory()->create();
        $hashOriginal = $user->password;

        $this->putJson("/api/v1/usuarios/{$user->id}", [
            'name' => 'Nombre Actualizado',
            'email' => $user->email,
            'password' => 'IntentoDeCambio123!',
        ])->assertOk()->assertJsonPath('data.name', 'Nombre Actualizado');

        $user->refresh();
        $this->assertSame($hashOriginal, $user->password);
    }

    public function test_password_cambia_la_contrasena(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $user = User::factory()->create();

        $this->postJson("/api/v1/usuarios/{$user->id}/password", [
            'password' => 'NuevaClaveSegura123!',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('NuevaClaveSegura123!', $user->password));
    }

    public function test_desactivar_no_permite_autodesactivarse(): void
    {
        $actor = User::factory()->create(['is_active' => true]);
        Sanctum::actingAs($actor, ['*']);

        $this->postJson("/api/v1/usuarios/{$actor->id}/desactivar")->assertStatus(422);
    }

    public function test_desactivar_revoca_tokens_de_sanctum(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $target = User::factory()->create(['is_active' => true]);
        $target->createToken('n8n');
        $this->assertSame(1, $target->tokens()->count());

        $this->postJson("/api/v1/usuarios/{$target->id}/desactivar")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertSame(0, $target->tokens()->count());
    }

    public function test_escritura_prohibida_con_habilidad_de_solo_lectura(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['usuarios:leer']);

        $this->postJson('/api/v1/usuarios', [
            'name' => 'X',
            'email' => 'x@example.com',
        ])->assertForbidden();
    }
}
