<?php

use App\Http\Controllers\Api\V1\Propuestas\PropuestaGeneracionApiController;
use App\Http\Controllers\Api\V1\Propuestas\PropuestaSeccionesApiController;
use App\Http\Controllers\Api\V1\Propuestas\PropuestasApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('propuestas')->name('propuestas.')->middleware('api.permiso:propuestas')->group(function () {
    Route::get('/', [PropuestasApiController::class, 'index']);
    Route::post('/', [PropuestasApiController::class, 'store'])->middleware('api.permiso:propuestas,escribir');
    Route::put('/{propuesta}', [PropuestasApiController::class, 'update'])->middleware('api.permiso:propuestas,escribir');
    Route::delete('/{propuesta}', [PropuestasApiController::class, 'destroy'])->middleware('api.permiso:propuestas,escribir');

    Route::patch('/{propuesta}/secciones/{seccion}', [PropuestaSeccionesApiController::class, 'actualizar'])
        ->middleware('api.permiso:propuestas,escribir');
    Route::post('/{propuesta}/sugerir-consultas', [PropuestaSeccionesApiController::class, 'sugerirConsultas'])
        ->middleware('api.permiso:propuestas,escribir');
    Route::post('/{propuesta}/estado', [PropuestasApiController::class, 'estado'])
        ->middleware('api.permiso:propuestas,escribir');
    Route::post('/{propuesta}/pdf', [PropuestaGeneracionApiController::class, 'pdf'])
        ->middleware('api.permiso:propuestas,escribir');

    // Al final del grupo: el comodín {propuesta} se traga las rutas literales
    // declaradas después (mismo motivo que routes/web.php).
    Route::get('/{propuesta}', [PropuestasApiController::class, 'show']);
});
