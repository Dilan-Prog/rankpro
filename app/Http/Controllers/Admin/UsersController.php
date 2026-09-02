<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AreaUsuario;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Servicio;
use App\Models\User;
use App\Support\Labels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UsersController extends Controller
{
    public function index(Request $request): View
    {
        $usuarios = User::with('role')->orderBy('name')->get()->map(fn (User $u) => $this->toRow($u, $request->user()));

        return view('admin.usuarios.index', [
            'pageTitle' => 'Usuarios del Sistema',
            'usuarios' => $usuarios,
            'roles' => Role::orderBy('label')->get(['id', 'label']),
            'areas' => collect(AreaUsuario::cases())->map(fn (AreaUsuario $a) => ['value' => $a->value, 'label' => Labels::areaUsuario($a->value)]),
            'usuariosTotal' => $usuarios->count(),
            'usuariosActivos' => $usuarios->where('is_active', true)->count(),
            'usuariosInternos' => $usuarios->filter(fn (array $u) => $u['area'] !== 'externo')->count(),
            'rolesEnUso' => $usuarios->pluck('role_id')->filter()->unique()->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'area' => ['nullable', Rule::enum(AreaUsuario::class)],
            'telefono' => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role_id' => $data['role_id'] ?? null,
            'area' => $data['area'] ?? null,
            'telefono' => $data['telefono'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json($this->toRow($user->fresh('role'), $request->user()), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'area' => ['nullable', Rule::enum(AreaUsuario::class)],
            'telefono' => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($user->id === $request->user()->id && ! $request->boolean('is_active')) {
            return response()->json([
                'message' => 'No puedes desactivar tu propia cuenta.',
                'errors' => ['is_active' => ['No puedes desactivar tu propia cuenta.']],
            ], 422);
        }

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'role_id' => $data['role_id'] ?? null,
            'area' => $data['area'] ?? null,
            'telefono' => $data['telefono'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return response()->json($this->toRow($user->fresh('role'), $request->user()));
    }

    /** Deactivates the account (is_active = false) — this app never hard-deletes a user, so it can't lose its audit trail (created content, role_permissions history, etc). Mirrors the reference's "Eliminar" action with the same real constraint update() already enforces: never your own account. */
    public function deactivate(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'No puedes desactivar tu propia cuenta.'], 422);
        }

        $user->update(['is_active' => false]);

        return response()->json($this->toRow($user->fresh('role'), $request->user()));
    }

    /** Shared shape for index()'s server-rendered rows and store()/update()/deactivate()'s AJAX responses. */
    private function toRow(User $user, User $viewer): array
    {
        $cuentas = Servicio::where('responsable_id', $user->id)
            ->with('cliente:id,nombre')
            ->get()
            ->pluck('cliente.nombre')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $user->role_id,
            'role_label' => $user->role?->label,
            'area' => $user->area?->value,
            'area_label' => $user->area ? Labels::areaUsuario($user->area->value) : null,
            'telefono' => $user->telefono,
            'is_active' => (bool) $user->is_active,
            'last_login_at' => $user->last_login_at?->format('Y-m-d H:i'),
            'cuentas_asignadas' => $cuentas->all(),
            'is_self' => $user->id === $viewer->id,
        ];
    }
}
