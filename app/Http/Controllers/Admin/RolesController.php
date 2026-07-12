<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RolesController extends Controller
{
    public function index(): View
    {
        $roles = Role::withCount('users')->with('permissions')->orderBy('name')->get();
        $usuarios = User::with('role')->orderBy('name')->get();

        return view('admin.roles.index', [
            'pageTitle' => 'Roles y Usuarios',
            'roles' => $roles,
            'usuarios' => $usuarios,
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.create', [
            'pageTitle' => 'Nuevo Rol',
            'permisos' => Permission::orderBy('module')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:roles,name', 'regex:/^[a-z0-9_]+$/'],
            'label' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ], [
            'name.regex' => 'El identificador solo puede tener minúsculas, números y guion bajo.',
        ]);

        $role = Role::create([
            'name' => $data['name'],
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
        ]);

        $role->permissions()->sync($data['permissions'] ?? []);

        return redirect()->route('admin.roles.index')->with('status', "Rol \"{$role->label}\" creado correctamente.");
    }

    public function edit(Role $role): View
    {
        return view('admin.roles.edit', [
            'pageTitle' => 'Editar Rol',
            'role' => $role->load('permissions'),
            'permisos' => Permission::orderBy('module')->get(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/', Rule::unique('roles', 'name')->ignore($role->id)],
            'label' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ], [
            'name.regex' => 'El identificador solo puede tener minúsculas, números y guion bajo.',
        ]);

        $role->update([
            'name' => $data['name'],
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
        ]);

        $role->permissions()->sync($data['permissions'] ?? []);

        return redirect()->route('admin.roles.index')->with('status', "Rol \"{$role->label}\" actualizado correctamente.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->users()->exists()) {
            return back()->withErrors(['role' => "No puedes eliminar el rol \"{$role->label}\" porque tiene usuarios asignados. Reasígnalos primero."]);
        }

        $role->delete();

        return redirect()->route('admin.roles.index')->with('status', "Rol \"{$role->label}\" eliminado.");
    }
}
