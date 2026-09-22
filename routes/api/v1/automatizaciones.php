<?php

use App\Http\Controllers\Api\V1\Automatizaciones\{
    AutomatizacionFaseApiController,
    AutomatizacionFlujoApiController,
    AutomatizacionProyectoApiController,
};
use Illuminate\Support\Facades\Route;

Route::prefix('automatizaciones/proyectos')->name('automatizaciones.proyectos.')->middleware('api.permiso:automatizaciones')->group(function () {
    Route::get('/', [AutomatizacionProyectoApiController::class, 'index'])->name('index');
    Route::post('/', [AutomatizacionProyectoApiController::class, 'store'])->name('store')->middleware('api.permiso:automatizaciones,escribir');

    Route::get('/{proyecto}/fase', [AutomatizacionFaseApiController::class, 'fase'])->name('fase');
    Route::post('/{proyecto}/fase/guardar', [AutomatizacionFaseApiController::class, 'guardar'])->name('fase.guardar')->middleware('api.permiso:automatizaciones,escribir');
    Route::post('/{proyecto}/fase/aprobar', [AutomatizacionFaseApiController::class, 'aprobar'])->name('fase.aprobar')->middleware('api.permiso:automatizaciones,escribir');
    Route::post('/{proyecto}/fase/retroceder', [AutomatizacionFaseApiController::class, 'retroceder'])->name('fase.retroceder')->middleware('api.permiso:automatizaciones,escribir');
    Route::post('/{proyecto}/fase/nuevo-ciclo', [AutomatizacionFaseApiController::class, 'nuevoCiclo'])->name('fase.nuevo-ciclo')->middleware('api.permiso:automatizaciones,escribir');
    Route::post('/{proyecto}/fase/cerrar', [AutomatizacionFaseApiController::class, 'cerrar'])->name('fase.cerrar')->middleware('api.permiso:automatizaciones,escribir');
    Route::post('/{proyecto}/fase/pausar', [AutomatizacionFaseApiController::class, 'pausar'])->name('fase.pausar')->middleware('api.permiso:automatizaciones,escribir');

    Route::get('/{proyecto}/flujos', [AutomatizacionFlujoApiController::class, 'index'])->name('flujos.index');
    Route::post('/{proyecto}/flujos', [AutomatizacionFlujoApiController::class, 'store'])->name('flujos.store')->middleware('api.permiso:automatizaciones,escribir');

    // Al final del grupo: el comodín {proyecto} se traga las rutas literales declaradas después.
    Route::get('/{proyecto}', [AutomatizacionProyectoApiController::class, 'show'])->name('show');
    Route::put('/{proyecto}', [AutomatizacionProyectoApiController::class, 'update'])->name('update')->middleware('api.permiso:automatizaciones,escribir');
    Route::delete('/{proyecto}', [AutomatizacionProyectoApiController::class, 'destroy'])->name('destroy')->middleware('api.permiso:automatizaciones,escribir');
});

Route::prefix('automatizaciones/flujos')->name('automatizaciones.flujos.')->middleware('api.permiso:automatizaciones')->group(function () {
    Route::put('/{flujo}', [AutomatizacionFlujoApiController::class, 'update'])->name('update')->middleware('api.permiso:automatizaciones,escribir');
    Route::delete('/{flujo}', [AutomatizacionFlujoApiController::class, 'destroy'])->name('destroy')->middleware('api.permiso:automatizaciones,escribir');
});
