<?php

use App\Http\Controllers\Admin\CalendarioController;
use Illuminate\Support\Facades\Route;

Route::prefix('calendario')->name('calendario.')->middleware('permiso:calendario')->group(function () {
    Route::get('/', [CalendarioController::class, 'index'])->name('index');
    Route::get('/eventos', [CalendarioController::class, 'eventos'])->name('eventos');
    Route::post('/eventos', [CalendarioController::class, 'store'])->name('store');
    Route::put('/eventos/{evento}', [CalendarioController::class, 'update'])->whereNumber('evento')->name('update');
    Route::delete('/eventos/{evento}', [CalendarioController::class, 'destroy'])->whereNumber('evento')->name('destroy');
});
