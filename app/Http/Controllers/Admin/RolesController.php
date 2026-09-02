<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RolesController extends Controller
{
    public function index(): View
    {
        $roles = Role::withCount('users')->with('permissions')->orderBy('name')->get();
        $permisos = Permission::orderBy('module')->get();

        return view('admin.roles.index', [
            'pageTitle' => 'Roles y Permisos',
            'roles' => $roles->map(fn (Role $role) => $this->toRow($role)),
            'permisos' => $permisos,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $role = Role::create([
            'name' => $data['name'],
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
        ]);

        $role->permissions()->sync($data['permissions'] ?? []);

        return response()->json($this->toRow($role->fresh('permissions')->loadCount('users')), 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $data = $this->validated($request, $role);

        $role->update([
            'name' => $data['name'],
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
        ]);

        $role->permissions()->sync($data['permissions'] ?? []);

        return response()->json($this->toRow($role->fresh('permissions')->loadCount('users')));
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->users()->exists()) {
            return response()->json([
                'message' => "No puedes eliminar el rol \"{$role->label}\" porque tiene usuarios asignados. Reasígnalos primero.",
            ], 422);
        }

        $role->delete();

        return response()->json(['deleted' => true]);
    }

    /** Shared shape for index()'s server-rendered cards/matrix and store()/update()'s AJAX responses. */
    private function toRow(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'label' => $role->label,
            'description' => $role->description,
            'users_count' => $role->users_count,
            'permission_ids' => $role->permissions->pluck('id')->all(),
            'permission_modules' => $role->permissions->pluck('module')->all(),
        ];
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/',
                $role ? Rule::unique('roles', 'name')->ignore($role->id) : Rule::unique('roles', 'name'),
            ],
            'label' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ], [
            'name.regex' => 'El identificador solo puede tener minúsculas, números y guion bajo.',
        ]);
    }
}
