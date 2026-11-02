<?php

use App\Http\Controllers\Admin\SeoPlanActividadController;
use App\Http\Controllers\Admin\SeoPlanClusterController;
use App\Http\Controllers\Admin\SeoPlanController;
use App\Http\Controllers\Admin\SeoPlanNotaController;
use Illuminate\Support\Facades\Route;

// Se carga dentro del grupo admin (auth, prefijo y nombre 'admin.'). Todo se acota al cliente activo.
Route::prefix('seo-plan')->name('seo-plan.')->middleware('permiso:seo-plan')->group(function () {
    Route::get('/', [SeoPlanController::class, 'index'])->name('index');
    Route::put('/plan', [SeoPlanController::class, 'guardarPlan'])->name('plan');

    Route::post('/actividades', [SeoPlanActividadController::class, 'store'])->name('actividades.store');
    Route::put('/actividades/{accion}', [SeoPlanActividadController::class, 'update'])->name('actividades.update');
    Route::delete('/actividades/{accion}', [SeoPlanActividadController::class, 'destroy'])->name('actividades.destroy');
    Route::patch('/actividades/{accion}/mover', [SeoPlanActividadController::class, 'mover'])->name('actividades.mover');

    Route::get('/actividades/{accion}/notas', [SeoPlanNotaController::class, 'index'])->name('notas.index');
    Route::post('/actividades/{accion}/notas', [SeoPlanNotaController::class, 'store'])->name('notas.store');
    Route::delete('/actividades/{accion}/notas/{nota}', [SeoPlanNotaController::class, 'destroy'])->name('notas.destroy');

    Route::post('/clusters', [SeoPlanClusterController::class, 'store'])->name('clusters.store');
    Route::put('/clusters/{cluster}', [SeoPlanClusterController::class, 'update'])->name('clusters.update');
    Route::delete('/clusters/{cluster}', [SeoPlanClusterController::class, 'destroy'])->name('clusters.destroy');
    Route::patch('/keywords/{keyword}/cluster', [SeoPlanClusterController::class, 'asignarKeyword'])->name('keywords.cluster');
});
