<?php

use App\Http\Controllers\Admin\UsersController;
use Illuminate\Support\Facades\Route;

// Se incluye desde routes/web.php dentro del grupo admin (auth, prefijo y nombre «admin.»).
// Va aparte del grupo de Usuarios de web.php y se carga antes que él; la ruta es literal por usuario
// (no pisa los comodines /{user}) y exige el mismo permiso del módulo.
Route::prefix('usuarios')->name('usuarios.')->middleware('permiso:usuarios')->group(function () {
    Route::post('/{user}/restablecer-contrasena', [UsersController::class, 'restablecerContrasena'])
        ->whereNumber('user')->name('restablecer-contrasena');
});
