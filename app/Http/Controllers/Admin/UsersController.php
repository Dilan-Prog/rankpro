<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AreaUsuario;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Servicio;
use App\Models\User;
use App\Support\GruposPermisos;
use App\Support\Labels;
use App\Support\ProteccionAdmin;
use App\Support\Reglas\Usuarios as ReglasUsuarios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class UsersController extends Controller
{
    public function index(Request $request): View
    {
        $lista = User::with('role')->orderBy('name')->get();
        $cuentas = $this->cuentasAsignadas($lista->pluck('id')->all());
        $usuarios = $lista->map(fn (User $u) => $this->toRow($u, $request->user(), $cuentas));

        return view('admin.usuarios.index', [
            'pageTitle' => 'Usuarios del Sistema',
            'usuarios' => $usuarios,
            'roles' => Role::orderBy('label')->get(['id', 'label']),
            'areas' => collect(AreaUsuario::cases())->map(fn (AreaUsuario $a) => ['value' => $a->value, 'label' => Labels::areaUsuario($a->value)]),
            'usuariosTotal' => $usuarios->count(),
            'usuariosActivos' => $usuarios->where('is_active', true)->count(),
            'usuariosInternos' => $usuarios->filter(fn (array $u) => $u['area'] !== 'externo')->count(),
            'rolesEnUso' => $usuarios->pluck('role_id')->filter()->unique()->count(),
            'permisosPorRol' => $this->permisosPorRol(),
        ]);
    }

    /**
     * Exporta la lista a CSV (UTF-8 con BOM para que Excel respete los acentos).
     * Solo lleva lo que ya se ve en la tabla: nunca teléfono, hash ni tokens.
     */
    public function exportar(): StreamedResponse
    {
        $usuarios = User::with('role')->orderBy('name')->get();

        return response()->streamDownload(function () use ($usuarios) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, ['Nombre', 'Correo', 'Rol', 'Área', 'Estado']);
            foreach ($usuarios as $u) {
                fputcsv($salida, array_map([self::class, 'celdaSegura'], [
                    $u->name,
                    $u->email,
                    $u->role?->label ?? 'Sin rol',
                    $u->area ? Labels::areaUsuario($u->area->value) : '',
                    $u->is_active ? 'Activo' : 'Inactivo',
                ]));
            }
            fclose($salida);
        }, 'usuarios-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Evita la inyección de fórmulas: una celda que empieza por = + - @ (o tab/CR)
     * se interpreta como fórmula al abrir el CSV en Excel; el apóstrofo la vuelve texto.
     */
    public static function celdaSegura(?string $valor): string
    {
        $valor = (string) $valor;

        return $valor !== '' && in_array($valor[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$valor : $valor;
    }

    /**
     * Módulos que concede cada rol, agrupados, para el panel de solo lectura
     * del formulario (cambia al elegir otro rol sin recargar).
     *
     * @return array<int, list<array{titulo: string, modulos: list<string>}>>
     */
    private function permisosPorRol(): array
    {
        return Role::with('permissions')->get()->mapWithKeys(function (Role $rol) {
            $grupos = collect(GruposPermisos::agrupar($rol->permissions))
                ->map(fn (array $g) => ['titulo' => $g['titulo'], 'modulos' => collect($g['permisos'])->pluck('label')->all()])
                ->all();

            return [$rol->id => $grupos];
        })->all();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(array_merge(ReglasUsuarios::datos(), ReglasUsuarios::passwordConfirmada()));

        if ($error = ProteccionAdmin::alAsignarRol($request->user(), $data['role_id'] ?? null)) {
            return response()->json(['message' => $error[1], 'errors' => [$error[0] => [$error[1]]]], 422);
        }

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
        $data = $request->validate(ReglasUsuarios::datos($user));

        if ($mensaje = ProteccionAdmin::alTocarUsuario($request->user(), $user)) {
            return response()->json(['message' => $mensaje], 403);
        }

        // Solo vigila el CAMBIO a admin: un admin que ya lo es puede seguir siendo editado por otro admin sin trabas.
        if (($data['role_id'] ?? null) !== $user->role_id && ($error = ProteccionAdmin::alAsignarRol($request->user(), $data['role_id'] ?? null))) {
            return response()->json(['message' => $error[1], 'errors' => [$error[0] => [$error[1]]]], 422);
        }

        if ($user->id === $request->user()->id && ! $request->boolean('is_active')) {
            return response()->json([
                'message' => 'No puedes desactivar tu propia cuenta.',
                'errors' => ['is_active' => ['No puedes desactivar tu propia cuenta.']],
            ], 422);
        }

        if ($error = ProteccionAdmin::alEditarUsuario($user, $data['role_id'] ?? null, $request->boolean('is_active'))) {
            return response()->json(['message' => $error[1], 'errors' => [$error[0] => [$error[1]]]], 422);
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

        if ($mensaje = ProteccionAdmin::alTocarUsuario($request->user(), $user)) {
            return response()->json(['message' => $mensaje], 403);
        }

        if ($mensaje = ProteccionAdmin::alDesactivarUsuario($user)) {
            return response()->json(['message' => $mensaje], 422);
        }

        $user->update(['is_active' => false]);

        return response()->json($this->toRow($user->fresh('role'), $request->user()));
    }

    /**
     * Envía al correo del usuario el enlace de restablecimiento del broker de Laravel (el mismo de
     * «Olvidé mi contraseña»). Nunca muestra ni fija una contraseña: el administrador no debe conocerla,
     * y el broker ya aplica su throttle por usuario. Un usuario inactivo no puede entrar, así que no se
     * le manda un enlace que sería inútil (y que reactivaría su acceso a medias).
     */
    public function restablecerContrasena(User $user): JsonResponse
    {
        if (! $user->is_active) {
            return response()->json(['message' => 'El usuario está inactivo: actívalo antes de enviarle un enlace.'], 422);
        }

        if (blank($user->email)) {
            return response()->json(['message' => 'El usuario no tiene correo registrado.'], 422);
        }

        $estado = Password::sendResetLink(['email' => $user->email]);

        if ($estado === Password::RESET_LINK_SENT) {
            return response()->json(['message' => "Enlace de restablecimiento enviado a {$user->email}."]);
        }

        if ($estado === Password::RESET_THROTTLED) {
            return response()->json(['message' => 'Ya se envió un enlace hace poco. Espera un minuto antes de reenviarlo.'], 429);
        }

        return response()->json(['message' => 'No se pudo enviar el enlace de restablecimiento.'], 422);
    }

    /**
     * Clientes (sin repetir, ordenados) de los servicios que cada usuario tiene como responsable,
     * en UNA consulta para toda la lista: hacerlo por usuario era un N+1 en cada carga del índice.
     *
     * @param  array<int, int>  $usuarioIds
     * @return array<int, list<string>>
     */
    private function cuentasAsignadas(array $usuarioIds): array
    {
        return Servicio::query()
            ->whereIn('servicios.responsable_id', $usuarioIds)
            ->join('clientes', 'clientes.id', '=', 'servicios.cliente_id')
            ->whereNotNull('clientes.nombre')
            ->whereNull('clientes.deleted_at') // igual que la relación: un cliente borrado no cuenta
            ->select('servicios.responsable_id', 'clientes.nombre')
            ->distinct()
            ->orderBy('clientes.nombre')
            ->get()
            ->groupBy('responsable_id')
            ->map(fn ($filas) => $filas->pluck('nombre')->sort()->values()->all())
            ->all();
    }

    /** Shared shape for index()'s server-rendered rows and store()/update()/deactivate()'s AJAX responses. */
    private function toRow(User $user, User $viewer, ?array $cuentasPorUsuario = null): array
    {
        $cuentas = collect(($cuentasPorUsuario ?? $this->cuentasAsignadas([$user->id]))[$user->id] ?? []);

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
