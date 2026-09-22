<?php

use App\Http\Controllers\Api\V1\Crm\RolesApiController;
use App\Http\Controllers\Api\V1\Crm\UsuariosApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('usuarios')->name('usuarios.')->middleware('api.permiso:usuarios')->group(function () {
    Route::get('/', [UsuariosApiController::class, 'index'])->name('index');
    Route::post('/', [UsuariosApiController::class, 'store'])->middleware('api.permiso:usuarios,escribir')->name('store');

    Route::post('/{user}/password', [UsuariosApiController::class, 'password'])->middleware('api.permiso:usuarios,escribir')->name('password');
    Route::post('/{user}/desactivar', [UsuariosApiController::class, 'desactivar'])->middleware('api.permiso:usuarios,escribir')->name('desactivar');
    Route::put('/{user}', [UsuariosApiController::class, 'update'])->middleware('api.permiso:usuarios,escribir')->name('update');

    Route::get('/{user}', [UsuariosApiController::class, 'show'])->name('show');
});

Route::prefix('roles')->name('roles.')->middleware('api.permiso:roles')->group(function () {
    Route::get('/', [RolesApiController::class, 'index'])->name('index');
    Route::post('/', [RolesApiController::class, 'store'])->middleware('api.permiso:roles,escribir')->name('store');
    Route::put('/{role}', [RolesApiController::class, 'update'])->middleware('api.permiso:roles,escribir')->name('update');
    Route::delete('/{role}', [RolesApiController::class, 'destroy'])->middleware('api.permiso:roles,escribir')->name('destroy');
});
