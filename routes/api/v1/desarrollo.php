<?php

use App\Http\Controllers\Api\V1\Desarrollo\BugsApiController;
use App\Http\Controllers\Api\V1\Desarrollo\ComunicacionesApiController;
use App\Http\Controllers\Api\V1\Desarrollo\ProyectoFaseApiController;
use App\Http\Controllers\Api\V1\Desarrollo\ProyectosApiController;
use App\Http\Controllers\Api\V1\Desarrollo\QaApiController;
use App\Http\Controllers\Api\V1\Desarrollo\TareasApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('desarrollo')->name('desarrollo.')->middleware('api.permiso:desarrollo')->group(function () {
    // Bugs es una lista global (no anidada) igual que en el panel
    // (ver Admin\BugController::index) — se declara antes de {proyecto} para
    // que "bugs" no se intente resolver como un id de proyecto.
    Route::get('/bugs', [BugsApiController::class, 'index']);
    Route::put('/bugs/{bug}', [BugsApiController::class, 'update'])->middleware('api.permiso:desarrollo,escribir');
    Route::delete('/bugs/{bug}', [BugsApiController::class, 'destroy'])->middleware('api.permiso:desarrollo,escribir');
    Route::post('/bugs/{bug}/resolver', [BugsApiController::class, 'resolver'])->middleware('api.permiso:desarrollo,escribir');

    Route::put('/tareas/{tarea}', [TareasApiController::class, 'update'])->middleware('api.permiso:desarrollo,escribir');
    Route::delete('/tareas/{tarea}', [TareasApiController::class, 'destroy'])->middleware('api.permiso:desarrollo,escribir');
    Route::post('/tareas/{tarea}/completar', [TareasApiController::class, 'completar'])->middleware('api.permiso:desarrollo,escribir');

    Route::delete('/comunicaciones/{comunicacion}', [ComunicacionesApiController::class, 'destroy'])->middleware('api.permiso:desarrollo,escribir');

    Route::put('/qa/{qa}', [QaApiController::class, 'update'])->middleware('api.permiso:desarrollo,escribir');
    Route::delete('/qa/{qa}', [QaApiController::class, 'destroy'])->middleware('api.permiso:desarrollo,escribir');

    Route::prefix('proyectos')->name('proyectos.')->group(function () {
        Route::get('/', [ProyectosApiController::class, 'index']);
        Route::post('/', [ProyectosApiController::class, 'store'])->middleware('api.permiso:desarrollo,escribir');

        Route::get('/{proyecto}/fase', [ProyectoFaseApiController::class, 'ver']);
        Route::post('/{proyecto}/fase/guardar', [ProyectoFaseApiController::class, 'guardar'])->middleware('api.permiso:desarrollo,escribir');
        Route::post('/{proyecto}/fase/aprobar', [ProyectoFaseApiController::class, 'aprobar'])->middleware('api.permiso:desarrollo,escribir');
        Route::post('/{proyecto}/fase/retroceder', [ProyectoFaseApiController::class, 'retroceder'])->middleware('api.permiso:desarrollo,escribir');

        Route::post('/{proyecto}/tareas', [TareasApiController::class, 'store'])->middleware('api.permiso:desarrollo,escribir');
        Route::post('/{proyecto}/bugs', [BugsApiController::class, 'store'])->middleware('api.permiso:desarrollo,escribir');
        Route::post('/{proyecto}/comunicaciones', [ComunicacionesApiController::class, 'store'])->middleware('api.permiso:desarrollo,escribir');
        Route::get('/{proyecto}/comunicaciones', [ComunicacionesApiController::class, 'index']);
        Route::post('/{proyecto}/qa', [QaApiController::class, 'store'])->middleware('api.permiso:desarrollo,escribir');

        Route::put('/{proyecto}', [ProyectosApiController::class, 'update'])->middleware('api.permiso:desarrollo,escribir');
        Route::delete('/{proyecto}', [ProyectosApiController::class, 'destroy'])->middleware('api.permiso:desarrollo,escribir');

        // Al final del grupo: el comodín {proyecto} se traga las rutas
        // literales declaradas después (mismo motivo que routes/web.php).
        Route::get('/{proyecto}', [ProyectosApiController::class, 'show']);
    });
});
