<?php

use App\Http\Controllers\Admin\InicioController;
use Illuminate\Support\Facades\Route;

// Resumen del día del usuario. Rutas literales, sin comodines.
Route::middleware('permiso:inicio')->group(function () {
    Route::get('/inicio', [InicioController::class, 'index'])->name('inicio');
    Route::patch('/inicio/tareas/{tarea}/completar', [InicioController::class, 'completarTarea'])
        ->whereNumber('tarea')->name('inicio.tareas.completar');
});
