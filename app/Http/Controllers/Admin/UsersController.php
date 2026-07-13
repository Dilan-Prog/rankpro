<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UsersController extends Controller
{
    /**
     * Users are managed from the Roles y Usuarios index (roles.index) — this
     * controller only handles the update action (assign role, toggle active).
     */
    public function create(): View
    {
        return view('admin.usuarios.create', [
            'pageTitle' => 'Nuevo Usuario',
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role_id' => $data['role_id'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()->route('admin.roles.index')->with('status', "Usuario \"{$user->name}\" creado correctamente.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($user->id === auth()->id() && empty($data['is_active'])) {
            return back()->withErrors(['user' => 'No puedes desactivar tu propia cuenta.']);
        }

        $user->update([
            'role_id' => $data['role_id'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()->route('admin.roles.index')->with('status', "Usuario \"{$user->name}\" actualizado correctamente.");
    }
}
