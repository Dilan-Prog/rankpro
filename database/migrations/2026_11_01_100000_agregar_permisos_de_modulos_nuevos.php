<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permisos de los módulos que se suman con la nueva interfaz (más agenda y
 * configuración, que existían sin permiso propio).
 *
 * Es una migración y no solo el PermissionSeeder porque RoleSeeder reasigna con
 * sync() los permisos de cada rol: correrlo en producción pisaría los roles
 * personalizados desde /admin/roles. Aquí solo se crean las filas que faltan y
 * se concede el acceso al rol admin; el resto de roles se ajusta en pantalla.
 */
return new class extends Migration
{
    /** @var array<string, string> nombre => etiqueta */
    private const PERMISOS = [
        'inicio' => 'Inicio',
        'mi-espacio' => 'Mi espacio',
        'calendario' => 'Calendario',
        'identidad' => 'Identidad de marca',
        'audiencia' => 'Audiencia',
        'seo-plan' => 'SEO Plan',
        'contenido' => 'Contenido',
        'agenda' => 'Agenda',
        'configuracion' => 'Configuración de correo',
    ];

    public function up(): void
    {
        $ahora = now();
        $admin = DB::table('roles')->where('name', config('permisos.rol_admin', 'admin'))->value('id');

        foreach (self::PERMISOS as $nombre => $etiqueta) {
            $permisoId = DB::table('permissions')->where('name', $nombre)->value('id');

            if ($permisoId === null) {
                $permisoId = DB::table('permissions')->insertGetId([
                    'name' => $nombre,
                    'label' => $etiqueta,
                    'module' => $nombre,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }

            if ($admin !== null) {
                $yaTiene = DB::table('role_permissions')
                    ->where('role_id', $admin)
                    ->where('permission_id', $permisoId)
                    ->exists();

                if (! $yaTiene) {
                    DB::table('role_permissions')->insert([
                        'role_id' => $admin,
                        'permission_id' => $permisoId,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('name', array_keys(self::PERMISOS))->pluck('id');

        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
