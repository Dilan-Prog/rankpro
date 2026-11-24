<?php

use App\Http\Controllers\Admin\RedesSocialesController;
use Illuminate\Support\Facades\Route;

// Configuración > Redes sociales (iconos del pie del sitio público).
Route::prefix('configuracion/redes')->name('configuracion.redes.')->middleware('permiso:configuracion')->group(function () {
    Route::get('/', [RedesSocialesController::class, 'edit'])->name('edit');
    Route::put('/', [RedesSocialesController::class, 'update'])->name('update');
});
