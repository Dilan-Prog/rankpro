<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Aplicar permisos por módulo
    |--------------------------------------------------------------------------
    |
    | Hasta ahora las tablas roles/role_permissions existían pero ninguna ruta
    | las consultaba. Con esto en true, cada grupo de rutas de /admin exige el
    | permiso de su módulo (middleware `permiso:{modulo}`); el rol `admin`
    | siempre pasa. Un usuario sin rol no pasa.
    |
    | Se deja como interruptor por entorno para poder desactivarlo de golpe si
    | al desplegar algún rol quedó sin permisos asignados (ver /admin/roles).
    | Los tests lo apagan en Tests\TestCase: PermisosTest lo enciende.
    |
    | Cómo desplegar sin bloquear a nadie:
    |   1. Antes de subir, entra a /admin/roles (pestaña "Matriz de permisos")
    |      y comprueba que cada rol tiene marcados los módulos que sus usuarios
    |      necesitan, sobre todo los módulos nuevos: la migración de permisos
    |      solo se los concede a `admin`, el resto se ajusta a mano ahí.
    |   2. Despliega y entra con un usuario admin y con uno de cada otro rol.
    |   3. Si alguien queda fuera de algo que debería ver y no hay tiempo de
    |      corregir el rol, pon PERMISOS_APLICAR=false en el .env y limpia la
    |      caché de configuración (php artisan config:clear). Con eso todos
    |      vuelven a entrar a todo mientras se arreglan los roles; luego se
    |      vuelve a true. Mientras esté en false NO hay control por módulo.
    |
    | Además, el sistema impide por sí mismo dejar el panel sin administrador:
    | no se puede quitar el rol admin, desactivar ni pasar a un rol sin
    | permisos al último usuario activo con ese rol, ni eliminar el rol admin o
    | dejarlo sin permisos (App\Support\ProteccionAdmin).
    |
    */

    'aplicar' => env('PERMISOS_APLICAR', true),

    /*
    | Rol que siempre tiene acceso a todo, aunque aún no tenga filas en
    | role_permissions para un módulo recién creado.
    */

    'rol_admin' => 'admin',

];
