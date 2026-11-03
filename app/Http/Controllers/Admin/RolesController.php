<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Support\GruposPermisos;
use App\Support\ProteccionAdmin;
use App\Support\Reglas\Roles as ReglasRoles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RolesController extends Controller
{
    public function index(): View
    {
        $roles = Role::withCount('users')->with(['permissions', 'users:id,name,email,role_id,is_active'])->orderBy('name')->get();
        $permisos = GruposPermisos::ordenar(Permission::orderBy('module')->get());

        return view('admin.roles.index', [
            'pageTitle' => 'Roles y Permisos',
            'roles' => $roles->map(fn (Role $role) => $this->toRow($role)),
            'permisos' => $permisos,
            // Mismos permisos, agrupados con etiqueta para la matriz y el formulario.
            'gruposPermisos' => GruposPermisos::agrupar($permisos),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        if ($error = ProteccionAdmin::alConcederPermisos($request->user(), null, $data['permissions'] ?? [])) {
            return $this->rechazo($error[0], $error[1]);
        }

        $role = Role::create([
            'name' => $data['name'],
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
        ]);

        $role->permissions()->sync($data['permissions'] ?? []);

        return response()->json($this->toRow($role->fresh(['permissions', 'users'])->loadCount('users')), 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $data = $this->validated($request, $role);

        if ($error = ProteccionAdmin::alConcederPermisos($request->user(), $role, $data['permissions'] ?? [])) {
            return $this->rechazo($error[0], $error[1]);
        }

        if ($error = ProteccionAdmin::alEditarRol($role, $data['name'], $data['permissions'] ?? [])) {
            return $this->rechazo($error[0], $error[1]);
        }

        $role->update([
            'name' => $data['name'],
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
        ]);

        $role->permissions()->sync($data['permissions'] ?? []);

        return response()->json($this->toRow($role->fresh(['permissions', 'users'])->loadCount('users')));
    }

    /**
     * Crea una copia del rol con sus permisos. El nombre sugerido se vuelve
     * único aquí (y no en el cliente) para que dos clics seguidos no choquen
     * con el unique de roles.name; luego se renombra desde Editar.
     */
    public function duplicar(Request $request, Role $role): JsonResponse
    {
        // La copia es editable: duplicar el rol admin daría a un no-admin un rol con todos los permisos.
        if ($error = ProteccionAdmin::alConcederPermisos($request->user(), null, $role->permissions()->pluck('permissions.id')->all())) {
            return $this->rechazo($error[0], $error[1]);
        }

        $base = substr($role->name, 0, 90).'_copia';
        $nombre = $base;
        for ($i = 2; Role::where('name', $nombre)->exists(); $i++) {
            $nombre = $base.'_'.$i;
        }

        $copia = Role::create([
            'name' => $nombre,
            'label' => 'Copia de '.$role->label,
            'description' => $role->description,
        ]);
        // El rol admin se puede duplicar: la copia es un rol normal, no admin.
        $copia->permissions()->sync($role->permissions()->pluck('permissions.id')->all());

        return response()->json($this->toRow($copia->fresh(['permissions', 'users'])->loadCount('users')), 201);
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($mensaje = ProteccionAdmin::alEliminarRol($role)) {
            return $this->rechazo('delete', $mensaje);
        }

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
            // Para la ficha de solo lectura: quién tiene el rol.
            'usuarios' => $role->users->sortBy('name')->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'is_active' => (bool) $u->is_active,
            ])->values()->all(),
        ];
    }

    private function rechazo(string $campo, string $mensaje): JsonResponse
    {
        return response()->json(['message' => $mensaje, 'errors' => [$campo => [$mensaje]]], 422);
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate(ReglasRoles::guardar($role), ReglasRoles::mensajes());
    }
}
