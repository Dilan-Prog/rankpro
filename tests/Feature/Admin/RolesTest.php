<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests for the rewritten RolesController: CRUD already worked before this
 * task (including the real guard blocking deletion of a role with users
 * still assigned) — this task converted store()/update()/destroy() from
 * full-page-reload forms to AJAX/JSON, and removed the create()/edit() page
 * methods and routes entirely. A new 12th Permission ('usuarios') was also
 * added via PermissionSeeder/RoleSeeder, closing a gap left when the
 * Usuarios module was split out of Roles without ever getting its own
 * permission entry (admin/manager now get it, viewer deliberately doesn't).
 */
class RolesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * admin.roles.index's Blade view references @vite('resources/js/roles.js'),
     * but vite.config.js's input list registers 'resources/css/admin/roles.css'
     * only — 'resources/js/roles.js' is absent from it (confirmed by reading
     * vite.config.js in full: every other admin module registers a css/js
     * pair, roles.css has no matching js entry), so public/build/manifest.json
     * has no chunk for it and any real request to the index route throws a
     * "Unable to locate file in Vite manifest: resources/js/roles.js." 500
     * (confirmed empirically by dumping the real response body). That's a
     * real gap outside RolesController/Role/Permission — not "fixed" here
     * per the task's instructions not to touch controller/model/route/view/
     * JS/build-config files — worked around test-side the same way
     * UsersTest works around its analogous usuarios.css/js gap, via the
     * framework's own withoutVite() helper, so the view itself can still be
     * exercised.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function actingAsUser(array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $this->actingAs($user);

        return $user;
    }

    /**
     * No RoleFactory exists in database/factories/ (confirmed), so this
     * hand-builds a Role, mirroring UsersTest's Role::create() convention
     * for models without a factory.
     */
    private function role(array $overrides = []): Role
    {
        return Role::create(array_merge([
            // Str::random() mixes upper/lowercase — lowercased here since a
            // Role's name must satisfy ^[a-z0-9_]+$ (tests below submit a
            // role's own unchanged name back through update()).
            'name' => 'rol_'.Str::lower(Str::random(8)),
            'label' => 'Rol de Prueba',
            'description' => null,
        ], $overrides));
    }

    /**
     * No PermissionFactory exists either — same hand-rolled convention.
     */
    private function permission(array $overrides = []): Permission
    {
        return Permission::create(array_merge([
            'name' => 'perm_'.Str::random(8),
            'label' => 'Permiso de Prueba',
            'module' => 'modulo_'.Str::random(6),
        ], $overrides));
    }

    private function rowShapeKeys(): array
    {
        return [
            'id', 'name', 'label', 'description', 'users_count',
            'permission_ids', 'permission_modules',
        ];
    }

    private function validStorePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'rol_nuevo',
            'label' => 'Rol Nuevo',
            'description' => 'Una descripción cualquiera.',
            'permissions' => [],
        ], $overrides);
    }

    // --- index ---------------------------------------------------------

    public function test_index_requires_authentication(): void
    {
        $response = $this->get(route('admin.roles.index'));

        $response->assertRedirect('/login');
    }

    public function test_index_returns_ok_and_correct_view(): void
    {
        $this->actingAsUser();

        $response = $this->get(route('admin.roles.index'));

        $response->assertOk();
        $response->assertViewIs('admin.roles.index');
    }

    public function test_roles_row_shape_has_exact_keys(): void
    {
        $this->actingAsUser();
        $role = $this->role();

        $response = $this->get(route('admin.roles.index'));

        $row = $response->viewData('roles')->firstWhere('id', $role->id);

        $this->assertSame($this->rowShapeKeys(), array_keys($row));
    }

    public function test_roles_users_count_reflects_real_assigned_users(): void
    {
        $this->actingAsUser();
        $roleWithUsers = $this->role(['name' => 'rol_con_usuarios']);
        $roleWithoutUsers = $this->role(['name' => 'rol_sin_usuarios']);
        User::factory()->count(2)->create(['role_id' => $roleWithUsers->id]);

        $response = $this->get(route('admin.roles.index'));
        $roles = $response->viewData('roles');

        $this->assertSame(2, $roles->firstWhere('id', $roleWithUsers->id)['users_count']);
        $this->assertSame(0, $roles->firstWhere('id', $roleWithoutUsers->id)['users_count']);
    }

    public function test_roles_permission_ids_and_modules_reflect_only_that_roles_granted_permissions(): void
    {
        $this->actingAsUser();
        $permA = $this->permission(['name' => 'perm_a', 'module' => 'modulo_a']);
        $permB = $this->permission(['name' => 'perm_b', 'module' => 'modulo_b']);
        $permC = $this->permission(['name' => 'perm_c', 'module' => 'modulo_c']);
        $role = $this->role();
        $role->permissions()->sync([$permA->id, $permB->id]);

        $response = $this->get(route('admin.roles.index'));
        $row = $response->viewData('roles')->firstWhere('id', $role->id);

        $this->assertEqualsCanonicalizing([$permA->id, $permB->id], $row['permission_ids']);
        $this->assertEqualsCanonicalizing(['modulo_a', 'modulo_b'], $row['permission_modules']);
        $this->assertNotContains($permC->id, $row['permission_ids']);
    }

    /**
     * Runs the real PermissionSeeder to prove the new 'usuarios' permission
     * (12th, closing the gap left when Usuarios was split out of Roles)
     * actually shows up in the view data, rather than asserting a
     * hardcoded count of 12.
     */
    public function test_permisos_view_data_contains_all_real_permissions_including_usuarios(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->actingAsUser();

        $response = $this->get(route('admin.roles.index'));
        $permisos = $response->viewData('permisos');

        $this->assertSame(Permission::count(), $permisos->count());
        $usuarios = $permisos->firstWhere('module', 'usuarios');
        $this->assertNotNull($usuarios);
        $this->assertSame('usuarios', $usuarios->name);
    }

    /**
     * Confirms the RoleSeeder update actually grants 'usuarios' to admin
     * and manager but deliberately withholds it from viewer.
     */
    public function test_role_seeder_grants_usuarios_to_admin_and_manager_but_not_viewer(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $admin = Role::where('name', 'admin')->firstOrFail();
        $manager = Role::where('name', 'manager')->firstOrFail();
        $viewer = Role::where('name', 'viewer')->firstOrFail();

        $this->assertTrue($admin->permissions->pluck('name')->contains('usuarios'));
        $this->assertTrue($manager->permissions->pluck('name')->contains('usuarios'));
        $this->assertFalse($viewer->permissions->pluck('name')->contains('usuarios'));
    }

    // --- store -----------------------------------------------------------

    public function test_store_requires_name(): void
    {
        $this->actingAsUser();

        $response = $this->postJson(route('admin.roles.store'), $this->validStorePayload(['name' => '']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_store_rejects_name_with_uppercase_spaces_or_hyphens(): void
    {
        $this->actingAsUser();

        foreach (['Nombre Rol', 'nombre-rol', 'NombreRol'] as $invalidName) {
            $response = $this->postJson(route('admin.roles.store'), $this->validStorePayload(['name' => $invalidName]));

            $response->assertStatus(422);
            $response->assertJsonValidationErrors('name');
        }
    }

    public function test_store_accepts_name_with_only_lowercase_digits_and_underscore(): void
    {
        $this->actingAsUser();

        $response = $this->postJson(route('admin.roles.store'), $this->validStorePayload(['name' => 'rol_valido_123']));

        $response->assertCreated();
    }

    public function test_store_rejects_duplicate_name(): void
    {
        $this->actingAsUser();
        $this->role(['name' => 'rol_existente']);

        $response = $this->postJson(route('admin.roles.store'), $this->validStorePayload(['name' => 'rol_existente']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_store_requires_label(): void
    {
        $this->actingAsUser();

        $response = $this->postJson(route('admin.roles.store'), $this->validStorePayload(['label' => '']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('label');
    }

    public function test_store_description_is_nullable(): void
    {
        $this->actingAsUser();

        $response = $this->postJson(route('admin.roles.store'), $this->validStorePayload(['description' => null]));

        $response->assertCreated();
        $this->assertNull($response->json('description'));
    }

    public function test_store_rejects_nonexistent_permission_id(): void
    {
        $this->actingAsUser();

        $response = $this->postJson(route('admin.roles.store'), $this->validStorePayload(['permissions' => [999999]]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('permissions.0');
    }

    public function test_store_success_returns_201_with_row_shape(): void
    {
        $this->actingAsUser();

        $response = $this->postJson(route('admin.roles.store'), $this->validStorePayload());

        $response->assertCreated();
        $response->assertJsonStructure($this->rowShapeKeys());
    }

    public function test_store_syncs_permissions_correctly(): void
    {
        $this->actingAsUser();
        $permA = $this->permission();
        $permB = $this->permission();
        $permC = $this->permission();

        $response = $this->postJson(route('admin.roles.store'), $this->validStorePayload([
            'permissions' => [$permA->id, $permB->id, $permC->id],
        ]));

        $response->assertCreated();
        $role = Role::findOrFail($response->json('id'));
        $this->assertSame(
            collect([$permA->id, $permB->id, $permC->id])->sort()->values()->all(),
            $role->fresh('permissions')->permissions->pluck('id')->sort()->values()->all()
        );
    }

    public function test_store_with_empty_permissions_array_succeeds_with_zero_permissions(): void
    {
        $this->actingAsUser();

        $response = $this->postJson(route('admin.roles.store'), $this->validStorePayload(['permissions' => []]));

        $response->assertCreated();
        $this->assertSame([], $response->json('permission_ids'));
        $role = Role::findOrFail($response->json('id'));
        $this->assertCount(0, $role->permissions);
    }

    public function test_store_with_permissions_key_omitted_succeeds_with_zero_permissions(): void
    {
        $this->actingAsUser();
        $payload = $this->validStorePayload();
        unset($payload['permissions']);

        $response = $this->postJson(route('admin.roles.store'), $payload);

        $response->assertCreated();
        $this->assertSame([], $response->json('permission_ids'));
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson(route('admin.roles.store'), $this->validStorePayload());

        $response->assertStatus(401);
    }

    // --- update ------------------------------------------------------------

    private function validUpdatePayload(Role $role, array $overrides = []): array
    {
        return array_merge([
            'name' => $role->name,
            'label' => $role->label,
            'description' => $role->description,
            'permissions' => [],
        ], $overrides);
    }

    public function test_update_requires_name(): void
    {
        $this->actingAsUser();
        $role = $this->role();

        $response = $this->putJson(route('admin.roles.update', $role), $this->validUpdatePayload($role, ['name' => '']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_update_rejects_name_with_invalid_characters(): void
    {
        $this->actingAsUser();
        $role = $this->role();

        $response = $this->putJson(route('admin.roles.update', $role), $this->validUpdatePayload($role, ['name' => 'Nombre Invalido']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_update_allows_submitting_roles_own_unchanged_name(): void
    {
        $this->actingAsUser();
        $role = $this->role(['name' => 'rol_sin_cambios']);

        $response = $this->putJson(route('admin.roles.update', $role), $this->validUpdatePayload($role, ['name' => 'rol_sin_cambios']));

        $response->assertOk();
    }

    public function test_update_rejects_name_already_taken_by_another_role(): void
    {
        $this->actingAsUser();
        $roleA = $this->role(['name' => 'rol_a']);
        $roleB = $this->role(['name' => 'rol_b']);

        $response = $this->putJson(route('admin.roles.update', $roleB), $this->validUpdatePayload($roleB, ['name' => 'rol_a']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_update_requires_label(): void
    {
        $this->actingAsUser();
        $role = $this->role();

        $response = $this->putJson(route('admin.roles.update', $role), $this->validUpdatePayload($role, ['label' => '']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('label');
    }

    public function test_update_rejects_nonexistent_permission_id(): void
    {
        $this->actingAsUser();
        $role = $this->role();

        $response = $this->putJson(route('admin.roles.update', $role), $this->validUpdatePayload($role, ['permissions' => [999999]]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('permissions.0');
    }

    public function test_update_success_returns_200_with_row_shape_and_persists_changes(): void
    {
        $this->actingAsUser();
        $role = $this->role(['name' => 'rol_original', 'label' => 'Label Original']);

        $response = $this->putJson(route('admin.roles.update', $role), $this->validUpdatePayload($role, [
            'label' => 'Label Actualizado',
            'description' => 'Descripción actualizada.',
        ]));

        $response->assertOk();
        $response->assertJsonStructure($this->rowShapeKeys());

        $role->refresh();
        $this->assertSame('Label Actualizado', $role->label);
        $this->assertSame('Descripción actualizada.', $role->description);
    }

    /**
     * sync() replaces the pivot set rather than merging: a role that starts
     * with [A, B] and is updated with only [C] must end up with ONLY [C].
     * Verified empirically here rather than assumed.
     */
    public function test_update_resyncs_permissions_replacing_not_merging(): void
    {
        $this->actingAsUser();
        $permA = $this->permission();
        $permB = $this->permission();
        $permC = $this->permission();
        $role = $this->role();
        $role->permissions()->sync([$permA->id, $permB->id]);

        $response = $this->putJson(route('admin.roles.update', $role), $this->validUpdatePayload($role, [
            'permissions' => [$permC->id],
        ]));

        $response->assertOk();
        $role->refresh();
        $this->assertSame([$permC->id], $role->permissions->pluck('id')->sort()->values()->all());
        $this->assertSame([$permC->id], $response->json('permission_ids'));
    }

    public function test_update_requires_authentication(): void
    {
        $role = $this->role();

        $response = $this->putJson(route('admin.roles.update', $role), $this->validUpdatePayload($role));

        $response->assertStatus(401);
    }

    // --- destroy -----------------------------------------------------------

    /**
     * Real, pre-existing guard: a role with >=1 user assigned cannot be
     * deleted. Asserts the exact controller message (not just "a 422"),
     * that the role survives, and that the user's role_id is untouched.
     */
    public function test_destroy_blocks_deletion_when_role_has_users_assigned(): void
    {
        $this->actingAsUser();
        $role = $this->role(['label' => 'Rol Con Usuarios']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $response = $this->deleteJson(route('admin.roles.destroy', $role));

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'No puedes eliminar el rol "Rol Con Usuarios" porque tiene usuarios asignados. Reasígnalos primero.',
        ]);

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
        $user->refresh();
        $this->assertSame($role->id, $user->role_id);
    }

    public function test_destroy_succeeds_when_role_has_zero_users(): void
    {
        $this->actingAsUser();
        $role = $this->role();

        $response = $this->deleteJson(route('admin.roles.destroy', $role));

        $response->assertOk();
        $response->assertJson(['deleted' => true]);
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    /**
     * role_permissions.role_id has an FK with ->onDelete('cascade')
     * (confirmed in the 2026_07_12_003505_create_role_permissions_table
     * migration) and the test DB is real MySQL/InnoDB (phpunit.xml), so
     * pivot cleanup happens automatically at the DB level — no model event
     * involved. Verified empirically here rather than assumed.
     */
    public function test_destroy_cleans_up_role_permissions_pivot_rows(): void
    {
        $this->actingAsUser();
        $role = $this->role();
        $perm = $this->permission();
        $role->permissions()->sync([$perm->id]);

        $this->deleteJson(route('admin.roles.destroy', $role));

        $this->assertDatabaseMissing('role_permissions', ['role_id' => $role->id]);
    }

    public function test_destroy_requires_authentication(): void
    {
        $role = $this->role();

        $response = $this->deleteJson(route('admin.roles.destroy', $role));

        $response->assertStatus(401);
    }

    // --- old routes regression ----------------------------------------------

    public function test_old_create_and_edit_routes_no_longer_exist(): void
    {
        $this->assertFalse(Route::has('admin.roles.create'));
        $this->assertFalse(Route::has('admin.roles.edit'));
    }

    /**
     * GET /admin/roles/nuevo doesn't 404 with "no route matched": the roles
     * group registers PUT and DELETE admin/roles/{role} (single-segment),
     * and "roles/nuevo" matches that same URI shape, just under different
     * HTTP verbs — so Laravel's router throws MethodNotAllowedHttpException,
     * rendered as 405. Verified against the real route list
     * (`php artisan route:list --path=roles`, which shows only
     * GET admin/roles, POST admin/roles, PUT admin/roles/{role} and
     * DELETE admin/roles/{role}) rather than assumed, mirroring the
     * analogous collision UsersTest documents for admin/usuarios/nuevo.
     */
    public function test_get_roles_nuevo_405s_by_colliding_with_the_update_destroy_route_shape(): void
    {
        $this->actingAsUser();

        $response = $this->get('/admin/roles/nuevo');

        $response->assertStatus(405);
    }

    /**
     * GET /admin/roles/{id}/editar, unlike /nuevo above, doesn't collide
     * with any registered route shape at all (no two-segment pattern exists
     * under admin/roles for any verb) — so this is a genuine 404, confirmed
     * against the same route:list output.
     */
    public function test_get_roles_id_editar_404s_no_route_matches_that_shape(): void
    {
        $this->actingAsUser();
        $role = $this->role();

        $response = $this->get("/admin/roles/{$role->id}/editar");

        $response->assertStatus(404);
    }
}
