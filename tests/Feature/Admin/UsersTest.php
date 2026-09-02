<?php

namespace Tests\Feature\Admin;

use App\Enums\AreaUsuario;
use App\Models\Cliente;
use App\Models\Role;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Tests for the new UsersController: "Usuarios" (system user/team-member
 * accounts) used to be an inline-editable tab inside RolesController's
 * "Roles y Usuarios" page (no dedicated index, account creation via a
 * full-page form, no delete/deactivate action at all). It's now a standalone
 * module with its own index() view and AJAX store()/update()/deactivate()
 * endpoints. Two new real profile columns (area, telefono) were added,
 * area cast to the new AreaUsuario enum, and a new User::servicios()
 * relation drives a computed "cuentas asignadas" list in the row shape.
 * This app never hard-deletes a user — only is_active=false via
 * deactivate() — and self-deactivation is blocked both via update()
 * submitting is_active=false for your own account and via deactivate()
 * on your own account.
 */
class UsersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * admin.usuarios.index's Blade view references
     * @vite('resources/css/admin/usuarios.css') / @vite('resources/js/usuarios.js'),
     * but vite.config.js was never updated to list those as entry points
     * for this "just-built" module (confirmed by reading vite.config.js —
     * every other admin module's css/js pair is registered there, these
     * two are absent), so public/build/manifest.json has no chunk for
     * them and any real request to the index route throws a
     * ViteManifestNotFoundException-adjacent "Unable to locate file in
     * Vite manifest" 500. That's a real gap in the module (outside
     * UsersController/User/AreaUsuario, so not "fixed" here per the task's
     * instructions not to touch controller/model/route/migration/enum/view/
     * JS files), worked around test-side with the framework's own
     * withoutVite() helper so the view itself can still be exercised.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Creates and authenticates a specific User, returning it so the test
     * keeps a handle on exactly "who is currently logged in" — needed
     * throughout this file to test the self-deactivation guard and
     * is_self, unlike sibling suites that just act as a disposable
     * User::factory()->create().
     */
    private function actingAsUser(array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $this->actingAs($user);

        return $user;
    }

    private function rowShapeKeys(): array
    {
        return [
            'id', 'name', 'email', 'role_id', 'role_label', 'area',
            'area_label', 'telefono', 'is_active', 'last_login_at',
            'cuentas_asignadas', 'is_self',
        ];
    }

    // --- index ---------------------------------------------------------

    public function test_index_requires_authentication(): void
    {
        $response = $this->get(route('admin.usuarios.index'));

        $response->assertRedirect('/login');
    }

    public function test_index_returns_ok_and_correct_view(): void
    {
        $this->actingAsUser();

        $response = $this->get(route('admin.usuarios.index'));

        $response->assertOk();
        $response->assertViewIs('admin.usuarios.index');
    }

    public function test_row_shape_has_exact_keys(): void
    {
        $viewer = $this->actingAsUser();

        $response = $this->get(route('admin.usuarios.index'));

        $row = $response->viewData('usuarios')->firstWhere('id', $viewer->id);

        $this->assertSame($this->rowShapeKeys(), array_keys($row));
    }

    public function test_role_label_is_null_when_role_id_is_null(): void
    {
        $viewer = $this->actingAsUser(['role_id' => null]);

        $response = $this->get(route('admin.usuarios.index'));

        $row = $response->viewData('usuarios')->firstWhere('id', $viewer->id);

        $this->assertNull($row['role_id']);
        $this->assertNull($row['role_label']);
    }

    public function test_role_label_reflects_real_role_label_when_present(): void
    {
        $role = Role::create(['name' => 'editor', 'label' => 'Editor']);
        $viewer = $this->actingAsUser(['role_id' => $role->id]);

        $response = $this->get(route('admin.usuarios.index'));

        $row = $response->viewData('usuarios')->firstWhere('id', $viewer->id);

        $this->assertSame($role->id, $row['role_id']);
        $this->assertSame('Editor', $row['role_label']);
    }

    public function test_area_label_translates_real_area_values(): void
    {
        $this->actingAsUser();
        $seo = User::factory()->create(['area' => 'seo']);
        $admin = User::factory()->create(['area' => 'administracion']);
        $sinArea = User::factory()->create(['area' => null]);

        $response = $this->get(route('admin.usuarios.index'));
        $usuarios = $response->viewData('usuarios');

        $this->assertSame('seo', $usuarios->firstWhere('id', $seo->id)['area']);
        $this->assertSame('SEO', $usuarios->firstWhere('id', $seo->id)['area_label']);
        $this->assertSame('administracion', $usuarios->firstWhere('id', $admin->id)['area']);
        $this->assertSame('Administración', $usuarios->firstWhere('id', $admin->id)['area_label']);
        $this->assertNull($usuarios->firstWhere('id', $sinArea->id)['area']);
        $this->assertNull($usuarios->firstWhere('id', $sinArea->id)['area_label']);
    }

    public function test_is_self_is_true_only_for_the_authenticated_user(): void
    {
        $viewer = $this->actingAsUser();
        $other = User::factory()->create();

        $response = $this->get(route('admin.usuarios.index'));
        $usuarios = $response->viewData('usuarios');

        $this->assertTrue($usuarios->firstWhere('id', $viewer->id)['is_self']);
        $this->assertFalse($usuarios->firstWhere('id', $other->id)['is_self']);
    }

    public function test_cuentas_asignadas_lists_distinct_client_names_for_the_responsable(): void
    {
        $this->actingAsUser();
        $responsable = User::factory()->create();
        $cliente = Cliente::factory()->create(['nombre' => 'Cliente Asignado']);
        Servicio::factory()->create(['responsable_id' => $responsable->id, 'cliente_id' => $cliente->id]);
        // Second servicio, same responsable + same client — must not duplicate.
        Servicio::factory()->create(['responsable_id' => $responsable->id, 'cliente_id' => $cliente->id]);

        $response = $this->get(route('admin.usuarios.index'));
        $row = $response->viewData('usuarios')->firstWhere('id', $responsable->id);

        $this->assertSame(['Cliente Asignado'], $row['cuentas_asignadas']);
    }

    public function test_cuentas_asignadas_is_empty_array_for_user_with_no_servicios(): void
    {
        $viewer = $this->actingAsUser();

        $response = $this->get(route('admin.usuarios.index'));
        $row = $response->viewData('usuarios')->firstWhere('id', $viewer->id);

        $this->assertSame([], $row['cuentas_asignadas']);
        $this->assertNotNull($row['cuentas_asignadas']);
    }

    public function test_kpi_counts_match_seeded_mix(): void
    {
        $roleA = Role::create(['name' => 'role_a', 'label' => 'Role A']);
        $roleB = Role::create(['name' => 'role_b', 'label' => 'Role B']);

        // area: null, is_active: true, role_id: null.
        $viewer = $this->actingAsUser(['area' => null, 'is_active' => true, 'role_id' => null]);
        // area: externo, is_active: true, role_id: roleA (excluded from "internos").
        User::factory()->create(['area' => 'externo', 'is_active' => true, 'role_id' => $roleA->id]);
        // area: seo, is_active: false, role_id: roleA (same role as previous — dedup in rolesEnUso).
        User::factory()->create(['area' => 'seo', 'is_active' => false, 'role_id' => $roleA->id]);
        // area: null, is_active: true, role_id: roleB.
        User::factory()->create(['area' => null, 'is_active' => true, 'role_id' => $roleB->id]);

        $response = $this->get(route('admin.usuarios.index'));

        $this->assertSame(4, $response->viewData('usuariosTotal'));
        $this->assertSame(3, $response->viewData('usuariosActivos'));
        // Internos = area !== 'externo': viewer(null) + seo-user + roleB-user = 3.
        $this->assertSame(3, $response->viewData('usuariosInternos'));
        // Distinct non-null role_ids in use: roleA, roleB = 2 (viewer's null role_id excluded).
        $this->assertSame(2, $response->viewData('rolesEnUso'));
    }

    /**
     * toRow()'s usuariosInternos check is $u['area'] !== 'externo' — a user
     * with area === null therefore counts as interno (it is simply not
     * 'externo'), rather than being excluded from either bucket. Verified
     * empirically here rather than assumed, since a plausible alternative
     * implementation would only count users with a real non-null,
     * non-externo area as "interno".
     */
    public function test_null_area_user_counts_as_interno(): void
    {
        $viewer = $this->actingAsUser(['area' => null]);

        $response = $this->get(route('admin.usuarios.index'));

        $this->assertSame(1, $response->viewData('usuariosInternos'));
        $this->assertSame(1, $response->viewData('usuariosTotal'));
    }

    public function test_roles_view_data_contains_real_roles_with_id_and_label(): void
    {
        $this->actingAsUser();
        $role = Role::create(['name' => 'gestor', 'label' => 'Gestor']);

        $response = $this->get(route('admin.usuarios.index'));
        $roles = $response->viewData('roles');

        $match = $roles->firstWhere('id', $role->id);
        $this->assertNotNull($match);
        $this->assertSame('Gestor', $match->label);
    }

    public function test_areas_view_data_contains_all_seven_cases_with_correct_labels(): void
    {
        $this->actingAsUser();

        $response = $this->get(route('admin.usuarios.index'));
        $areas = $response->viewData('areas');

        $this->assertCount(7, $areas);
        $this->assertEquals([
            ['value' => 'direccion', 'label' => 'Dirección'],
            ['value' => 'seo', 'label' => 'SEO'],
            ['value' => 'ads', 'label' => 'Ads'],
            ['value' => 'social', 'label' => 'Social'],
            ['value' => 'desarrollo', 'label' => 'Desarrollo'],
            ['value' => 'administracion', 'label' => 'Administración'],
            ['value' => 'externo', 'label' => 'Externo'],
        ], $areas->toArray());
    }

    // --- store -----------------------------------------------------------

    private function validStorePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo.usuario@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ], $overrides);
    }

    public function test_store_requires_name(): void
    {
        $this->actingAsUser();

        $response = $this->postJson(route('admin.usuarios.store'), $this->validStorePayload(['name' => '']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_store_requires_email(): void
    {
        $this->actingAsUser();

        $response = $this->postJson(route('admin.usuarios.store'), $this->validStorePayload(['email' => '']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_store_rejects_invalid_email(): void
    {
        $this->actingAsUser();

        $response = $this->postJson(route('admin.usuarios.store'), $this->validStorePayload(['email' => 'not-an-email']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_store_rejects_duplicate_email(): void
    {
        $this->actingAsUser();
        $existing = User::factory()->create(['email' => 'existente@example.com']);

        $response = $this->postJson(route('admin.usuarios.store'), $this->validStorePayload(['email' => $existing->email]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_store_requires_password(): void
    {
        $this->actingAsUser();

        $payload = $this->validStorePayload();
        unset($payload['password'], $payload['password_confirmation']);

        $response = $this->postJson(route('admin.usuarios.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    public function test_store_requires_password_confirmation(): void
    {
        $this->actingAsUser();

        $payload = $this->validStorePayload();
        unset($payload['password_confirmation']);

        $response = $this->postJson(route('admin.usuarios.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    public function test_store_rejects_mismatched_password_confirmation(): void
    {
        $this->actingAsUser();

        $response = $this->postJson(route('admin.usuarios.store'), $this->validStorePayload([
            'password_confirmation' => 'OtraClave123!',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    public function test_store_rejects_nonexistent_role_id(): void
    {
        $this->actingAsUser();

        $response = $this->postJson(route('admin.usuarios.store'), $this->validStorePayload(['role_id' => 999999]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('role_id');
    }

    public function test_store_accepts_null_role_id(): void
    {
        $this->actingAsUser();

        $response = $this->postJson(route('admin.usuarios.store'), $this->validStorePayload(['role_id' => null]));

        $response->assertCreated();
    }

    public function test_store_rejects_invalid_area(): void
    {
        $this->actingAsUser();

        $response = $this->postJson(route('admin.usuarios.store'), $this->validStorePayload(['area' => 'not_a_real_area']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('area');
    }

    public function test_store_accepts_every_real_area_value(): void
    {
        $this->actingAsUser();

        foreach (AreaUsuario::cases() as $area) {
            $response = $this->postJson(route('admin.usuarios.store'), $this->validStorePayload([
                'email' => "usuario.{$area->value}@example.com",
                'area' => $area->value,
            ]));

            $response->assertCreated();
            $this->assertSame($area->value, $response->json('area'));
        }
    }

    public function test_store_valid_payload_succeeds_with_row_shape_response(): void
    {
        $this->actingAsUser();

        $response = $this->postJson(route('admin.usuarios.store'), $this->validStorePayload());

        $response->assertCreated();
        $response->assertJsonStructure($this->rowShapeKeys());
    }

    public function test_store_hashes_the_password(): void
    {
        $this->actingAsUser();

        $response = $this->postJson(route('admin.usuarios.store'), $this->validStorePayload([
            'password' => 'ClavePlano123!',
            'password_confirmation' => 'ClavePlano123!',
        ]));

        $user = User::findOrFail($response->json('id'));

        $this->assertNotSame('ClavePlano123!', $user->password);
        $this->assertTrue(Hash::check('ClavePlano123!', $user->password));
    }

    public function test_store_defaults_is_active_to_true_when_omitted(): void
    {
        $this->actingAsUser();

        $response = $this->postJson(route('admin.usuarios.store'), $this->validStorePayload());

        $response->assertCreated();
        $this->assertTrue($response->json('is_active'));
        $this->assertDatabaseHas('users', ['id' => $response->json('id'), 'is_active' => true]);
    }

    public function test_store_respects_explicit_is_active_false(): void
    {
        $this->actingAsUser();

        $response = $this->postJson(route('admin.usuarios.store'), $this->validStorePayload(['is_active' => false]));

        $response->assertCreated();
        $this->assertFalse($response->json('is_active'));
        $this->assertDatabaseHas('users', ['id' => $response->json('id'), 'is_active' => false]);
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson(route('admin.usuarios.store'), $this->validStorePayload());

        $response->assertStatus(401);
    }

    // --- update ------------------------------------------------------------

    private function validUpdatePayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => true,
        ], $overrides);
    }

    public function test_update_requires_name(): void
    {
        $this->actingAsUser();
        $target = User::factory()->create();

        $response = $this->putJson(route('admin.usuarios.update', $target), $this->validUpdatePayload($target, ['name' => '']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_update_rejects_invalid_email(): void
    {
        $this->actingAsUser();
        $target = User::factory()->create();

        $response = $this->putJson(route('admin.usuarios.update', $target), $this->validUpdatePayload($target, ['email' => 'invalido']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_update_rejects_email_already_taken_by_another_user(): void
    {
        $this->actingAsUser();
        $target = User::factory()->create();
        $other = User::factory()->create(['email' => 'ocupado@example.com']);

        $response = $this->putJson(route('admin.usuarios.update', $target), $this->validUpdatePayload($target, ['email' => $other->email]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_update_allows_submitting_users_own_unchanged_email(): void
    {
        $this->actingAsUser();
        $target = User::factory()->create(['email' => 'sincambios@example.com']);

        $response = $this->putJson(route('admin.usuarios.update', $target), $this->validUpdatePayload($target, ['email' => 'sincambios@example.com']));

        $response->assertOk();
    }

    public function test_update_rejects_nonexistent_role_id(): void
    {
        $this->actingAsUser();
        $target = User::factory()->create();

        $response = $this->putJson(route('admin.usuarios.update', $target), $this->validUpdatePayload($target, ['role_id' => 999999]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('role_id');
    }

    public function test_update_rejects_invalid_area(): void
    {
        $this->actingAsUser();
        $target = User::factory()->create();

        $response = $this->putJson(route('admin.usuarios.update', $target), $this->validUpdatePayload($target, ['area' => 'invalida']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('area');
    }

    public function test_update_does_not_accept_or_require_a_password_field(): void
    {
        $this->actingAsUser();
        $target = User::factory()->create();
        $originalHash = $target->password;

        $response = $this->putJson(route('admin.usuarios.update', $target), $this->validUpdatePayload($target, [
            'password' => 'CualquierClave123!',
        ]));

        $response->assertOk();
        $target->refresh();
        $this->assertSame($originalHash, $target->password);
    }

    public function test_update_success_returns_row_shape_and_persists_changes(): void
    {
        $this->actingAsUser();
        $role = Role::create(['name' => 'nuevo_rol', 'label' => 'Nuevo Rol']);
        $target = User::factory()->create(['name' => 'Nombre Viejo', 'area' => 'seo', 'role_id' => null]);

        $response = $this->putJson(route('admin.usuarios.update', $target), $this->validUpdatePayload($target, [
            'name' => 'Nombre Actualizado',
            'area' => 'ads',
            'role_id' => $role->id,
        ]));

        $response->assertOk();
        $response->assertJsonStructure($this->rowShapeKeys());

        $target->refresh();
        $this->assertSame('Nombre Actualizado', $target->name);
        $this->assertSame('ads', $target->area->value);
        $this->assertSame($role->id, $target->role_id);
    }

    public function test_update_self_deactivation_is_blocked_when_is_active_omitted(): void
    {
        $viewer = $this->actingAsUser();

        $payload = $this->validUpdatePayload($viewer);
        unset($payload['is_active']);

        $response = $this->putJson(route('admin.usuarios.update', $viewer), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('is_active');

        $viewer->refresh();
        $this->assertTrue($viewer->is_active);
    }

    public function test_update_self_deactivation_is_blocked_when_is_active_explicitly_false(): void
    {
        $viewer = $this->actingAsUser();

        $response = $this->putJson(route('admin.usuarios.update', $viewer), $this->validUpdatePayload($viewer, ['is_active' => false]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('is_active');

        $viewer->refresh();
        $this->assertTrue($viewer->is_active);
    }

    public function test_update_allows_self_edit_when_is_active_stays_true(): void
    {
        $viewer = $this->actingAsUser();

        $response = $this->putJson(route('admin.usuarios.update', $viewer), $this->validUpdatePayload($viewer, [
            'is_active' => true,
            'telefono' => '555-1234',
        ]));

        $response->assertOk();
        $viewer->refresh();
        $this->assertSame('555-1234', $viewer->telefono);
        $this->assertTrue($viewer->is_active);
    }

    public function test_update_can_deactivate_a_different_user(): void
    {
        $this->actingAsUser();
        $target = User::factory()->create(['is_active' => true]);

        $response = $this->putJson(route('admin.usuarios.update', $target), $this->validUpdatePayload($target, ['is_active' => false]));

        $response->assertOk();
        $this->assertFalse($response->json('is_active'));
        $target->refresh();
        $this->assertFalse($target->is_active);
    }

    public function test_update_requires_authentication(): void
    {
        $target = User::factory()->create();

        $response = $this->putJson(route('admin.usuarios.update', $target), $this->validUpdatePayload($target));

        $response->assertStatus(401);
    }

    // --- deactivate ----------------------------------------------------------

    public function test_deactivate_returns_ok_with_row_shape_is_active_false(): void
    {
        $this->actingAsUser();
        $target = User::factory()->create(['is_active' => true]);

        $response = $this->postJson(route('admin.usuarios.deactivate', $target));

        $response->assertOk();
        $response->assertJsonStructure($this->rowShapeKeys());
        $this->assertFalse($response->json('is_active'));
    }

    public function test_deactivate_persists_is_active_false_without_deleting_the_row(): void
    {
        $this->actingAsUser();
        $target = User::factory()->create(['is_active' => true]);

        $this->postJson(route('admin.usuarios.deactivate', $target));

        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => false]);
    }

    public function test_deactivate_blocks_self_deactivation(): void
    {
        $viewer = $this->actingAsUser(['is_active' => true]);

        $response = $this->postJson(route('admin.usuarios.deactivate', $viewer));

        $response->assertStatus(422);

        $viewer->refresh();
        $this->assertTrue($viewer->is_active);
    }

    public function test_deactivate_succeeds_for_a_different_non_self_user(): void
    {
        $this->actingAsUser();
        $target = User::factory()->create(['is_active' => true]);

        $response = $this->postJson(route('admin.usuarios.deactivate', $target));

        $response->assertOk();
        $target->refresh();
        $this->assertFalse($target->is_active);
    }

    public function test_deactivate_requires_authentication(): void
    {
        $target = User::factory()->create();

        $response = $this->postJson(route('admin.usuarios.deactivate', $target));

        $response->assertStatus(401);
    }

    // --- old routes regression ----------------------------------------------

    public function test_old_create_route_no_longer_exists(): void
    {
        $this->assertFalse(Route::has('admin.usuarios.create'));
    }

    /**
     * The usuarios group has no GET /{user} route at all (index() is the
     * only GET), but it DOES have PUT /admin/usuarios/{user} — a
     * single-segment pattern that matches the URI shape of
     * "admin/usuarios/nuevo" too. Laravel's router therefore doesn't 404
     * with "no route matched" for this GET request: it finds that URI
     * pattern registered for a *different* HTTP method (PUT) and throws
     * MethodNotAllowedHttpException, rendered as 405 — verified empirically
     * against the real route list (`php artisan route:list --path=usuarios`)
     * rather than assumed, the same way SeoCampanasTest verified its
     * analogous old-route case against the real router behavior instead of
     * guessing 404.
     */
    public function test_get_usuarios_nuevo_405s_by_colliding_with_the_update_route_shape(): void
    {
        $this->actingAsUser();

        $response = $this->get('/admin/usuarios/nuevo');

        $response->assertStatus(405);
    }

    // --- RolesController regression (light bonus check) ---------------------

    /**
     * Not this file's main concern, but RolesController::index() was
     * trimmed to stop fetching/passing a `usuarios` variable to its view
     * now that Usuarios has its own page — this is a light regression check
     * that admin.roles.index still renders fine without it.
     */
    public function test_roles_index_still_returns_ok_without_usuarios_view_data(): void
    {
        $this->actingAsUser();

        $response = $this->get(route('admin.roles.index'));

        $response->assertOk();
    }
}
