<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

/**
 * Reglas para que nadie pueda dejar el panel sin administrador.
 *
 * Con los permisos por módulo aplicados, un panel sin ningún usuario activo
 * con rol admin ya no se arregla desde /admin/roles ni /admin/usuarios: habría
 * que tocar la base de datos. Por eso estas reglas viven en una sola clase y
 * las usan por igual los controladores web y los de la API. Cada método
 * devuelve el mensaje de error (o null si la operación es segura) para que
 * cada capa lo exprese a su manera (422 JSON o ErrorDeDominio).
 */
class ProteccionAdmin
{
    public static function nombreRolAdmin(): string
    {
        return (string) config('permisos.rol_admin', 'admin');
    }

    public static function esRolAdmin(?Role $rol): bool
    {
        return $rol !== null && $rol->name === self::nombreRolAdmin();
    }

    /**
     * ¿Quien ejecuta la acción es administrador? Con los permisos apagados
     * (tests / PERMISOS_APLICAR=false) no hay jerarquía que proteger, igual que
     * User::tienePermiso(), que deja pasar todo en ese modo.
     */
    public static function esAdmin(?User $usuario): bool
    {
        if (! config('permisos.aplicar')) {
            return true;
        }

        return $usuario !== null && self::esRolAdmin($usuario->role);
    }

    /**
     * Autoescalada por usuarios: solo un admin puede dar el rol admin. Sin esto,
     * quien tiene «usuarios» (pero no es admin) se asignaría el rol admin a sí mismo.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function alAsignarRol(User $editor, ?int $rolId): ?array
    {
        if ($rolId === null || self::esAdmin($editor)) {
            return null;
        }

        return self::esRolAdmin(Role::find($rolId))
            ? ['role_id', 'Solo un administrador puede asignar el rol admin.']
            : null;
    }

    /** Un usuario no admin tampoco puede tocar la cuenta de un admin (cambiarle el correo equivale a tomarla). */
    public static function alTocarUsuario(User $editor, User $objetivo): ?string
    {
        return ! self::esAdmin($editor) && self::esRolAdmin($objetivo->role)
            ? 'Solo un administrador puede modificar a otro administrador.'
            : null;
    }

    /**
     * Autoescalada por roles: quien edita un rol solo puede AGREGARLE permisos
     * que él mismo tiene. Los que el rol ya traía se respetan (no se le exige
     * tenerlos para renombrarlo o quitarlos).
     *
     * @param  array<int, int|string>  $permisosIds  los que quedarán en el rol
     * @return array{0: string, 1: string}|null
     */
    public static function alConcederPermisos(User $editor, ?Role $rol, array $permisosIds): ?array
    {
        if (self::esAdmin($editor)) {
            return null;
        }

        $yaTenia = $rol ? $rol->permissions()->pluck('permissions.id')->all() : [];
        $nuevos = array_diff(array_map('intval', $permisosIds), $yaTenia);
        if ($nuevos === []) {
            return null;
        }

        $faltan = Permission::whereIn('id', $nuevos)->pluck('name')
            ->reject(fn (string $modulo) => $editor->tienePermiso($modulo));

        return $faltan->isEmpty()
            ? null
            : ['permissions', 'No puedes conceder permisos que tú no tienes: '.$faltan->implode(', ').'.'];
    }

    /** ¿Existe otro usuario activo con rol admin, distinto de $excluir? */
    public static function hayOtroAdminActivo(User $excluir): bool
    {
        return User::query()
            ->where('id', '!=', $excluir->id)
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->where('name', self::nombreRolAdmin()))
            ->exists();
    }

    /**
     * Validación al editar un usuario: devuelve [campo, mensaje] si el cambio
     * dejaría al sistema sin administrador activo; null si es seguro.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function alEditarUsuario(User $user, ?int $nuevoRolId, bool $quedaActivo): ?array
    {
        $eraAdminActivo = $user->is_active && self::esRolAdmin($user->role);
        if (! $eraAdminActivo) {
            return null;
        }

        $nuevoRol = $nuevoRolId ? Role::withCount('permissions')->find($nuevoRolId) : null;
        $seguiraSiendoAdmin = $quedaActivo && self::esRolAdmin($nuevoRol);
        if ($seguiraSiendoAdmin || self::hayOtroAdminActivo($user)) {
            return null;
        }

        if (! $quedaActivo && self::esRolAdmin($nuevoRol)) {
            return ['is_active', 'No puedes desactivar al único administrador activo. Activa o asigna el rol admin a otro usuario primero.'];
        }

        if ($nuevoRol === null) {
            return ['role_id', 'No puedes dejar sin rol al único administrador activo. Asigna el rol admin a otro usuario primero.'];
        }

        if ($nuevoRol->permissions_count === 0) {
            return ['role_id', "No puedes cambiar al único administrador activo a \"{$nuevoRol->label}\", un rol sin permisos: se bloquearía el panel. Asigna el rol admin a otro usuario primero."];
        }

        return ['role_id', 'No puedes quitar el rol admin al único administrador activo. Asigna el rol admin a otro usuario primero.'];
    }

    /** Validación al desactivar a un usuario (acción rápida). */
    public static function alDesactivarUsuario(User $user): ?string
    {
        $error = self::alEditarUsuario($user, $user->role_id, false);

        return $error ? $error[1] : null;
    }

    /**
     * Validación al guardar un rol: el rol admin no puede quedarse sin
     * permisos ni cambiar de identificador (la configuración lo busca por nombre).
     *
     * @return array{0: string, 1: string}|null
     */
    public static function alEditarRol(Role $rol, string $nuevoNombre, array $permisos): ?array
    {
        if (! self::esRolAdmin($rol)) {
            return null;
        }

        if ($nuevoNombre !== $rol->name) {
            return ['name', 'No puedes cambiar el identificador del rol admin: el sistema lo usa para reconocer a los administradores.'];
        }

        if (count($permisos) === 0) {
            return ['permissions', 'El rol admin no puede quedarse sin permisos: nadie podría entrar a los módulos.'];
        }

        return null;
    }

    public static function alEliminarRol(Role $rol): ?string
    {
        return self::esRolAdmin($rol) ? 'No puedes eliminar el rol admin: es el que garantiza el acceso al panel.' : null;
    }
}
