<?php

use App\Http\Controllers\Admin\MiEspacioController;
use Illuminate\Support\Facades\Route;

// Tareas personales del usuario. Todo es literal: no hay comodín que se trague rutas.
Route::prefix('mi-espacio')->name('mi-espacio.')->middleware('permiso:mi-espacio')->group(function () {
    Route::get('/', [MiEspacioController::class, 'index'])->name('index');
    Route::post('/tareas', [MiEspacioController::class, 'store'])->name('store');
    Route::put('/tareas/{tarea}', [MiEspacioController::class, 'update'])->whereNumber('tarea')->name('update');
    Route::patch('/tareas/{tarea}/mover', [MiEspacioController::class, 'mover'])->whereNumber('tarea')->name('mover');
    Route::patch('/tareas/{tarea}/restaurar', [MiEspacioController::class, 'restaurar'])->whereNumber('tarea')->name('restaurar');
    Route::delete('/tareas/{tarea}', [MiEspacioController::class, 'destroy'])->whereNumber('tarea')->name('destroy');
});
