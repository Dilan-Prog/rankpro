<?php

use App\Http\Controllers\Admin\IdentidadController;
use Illuminate\Support\Facades\Route;

// Se incluye desde routes/web.php dentro del grupo admin (auth, prefijo y nombre «admin.»).
Route::prefix('identidad')->name('identidad.')->middleware('permiso:identidad')->group(function () {
    Route::get('/', [IdentidadController::class, 'index'])->name('index');
    Route::put('/', [IdentidadController::class, 'guardar'])->name('guardar');
    Route::get('/logo', [IdentidadController::class, 'logo'])->name('logo');
    Route::delete('/logo', [IdentidadController::class, 'quitarLogo'])->name('logo.quitar');
});
