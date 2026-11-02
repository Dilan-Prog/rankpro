<?php

use App\Http\Controllers\Admin\AudienciaController;
use Illuminate\Support\Facades\Route;

// Se incluye desde routes/web.php dentro del grupo admin (auth, prefijo y nombre «admin.»).
Route::prefix('audiencia')->name('audiencia.')->middleware('permiso:audiencia')->group(function () {
    Route::get('/', [AudienciaController::class, 'index'])->name('index');
    Route::post('/', [AudienciaController::class, 'store'])->name('store');
    Route::put('/{persona}', [AudienciaController::class, 'update'])->name('update');
    Route::delete('/{persona}', [AudienciaController::class, 'destroy'])->name('destroy');
    Route::post('/{persona}/mover', [AudienciaController::class, 'mover'])->name('mover');
});
