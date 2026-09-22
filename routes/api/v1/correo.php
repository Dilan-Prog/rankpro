<?php

use App\Http\Controllers\Api\V1\Correo\CorreoEnviosApiController;
use App\Http\Controllers\Api\V1\Correo\CorreoPlantillasApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('correo')->name('correo.')->middleware('api.permiso:correo')->group(function () {
    Route::prefix('plantillas')->name('plantillas.')->group(function () {
        Route::get('/', [CorreoPlantillasApiController::class, 'index']);
        Route::post('/', [CorreoPlantillasApiController::class, 'store'])->middleware('api.permiso:correo,escribir');
        Route::post('/preview', [CorreoPlantillasApiController::class, 'preview']);
        Route::put('/{plantilla}', [CorreoPlantillasApiController::class, 'update'])->middleware('api.permiso:correo,escribir');
        Route::delete('/{plantilla}', [CorreoPlantillasApiController::class, 'destroy'])->middleware('api.permiso:correo,escribir');
        Route::post('/{plantilla}/duplicar', [CorreoPlantillasApiController::class, 'duplicar'])->middleware('api.permiso:correo,escribir');
        // Al final: el comodín {plantilla} se traga las rutas literales posteriores.
        Route::get('/{plantilla}', [CorreoPlantillasApiController::class, 'show']);
    });

    Route::prefix('envios')->name('envios.')->group(function () {
        Route::get('/', [CorreoEnviosApiController::class, 'index']);
        Route::post('/', [CorreoEnviosApiController::class, 'store'])->middleware('api.permiso:correo,escribir');
        // Envío de prueba: no crea Envio, no deja rastro — se trata como
        // escritura porque manda un correo real igualmente.
        Route::post('/prueba', [CorreoEnviosApiController::class, 'prueba'])->middleware('api.permiso:correo,escribir');
        Route::put('/{envio}', [CorreoEnviosApiController::class, 'update'])->middleware('api.permiso:correo,escribir');
        Route::delete('/{envio}', [CorreoEnviosApiController::class, 'destroy'])->middleware('api.permiso:correo,escribir');

        // `enviar` es SÍNCRONO (App\Services\Correo\EnviadorCorreo::enviar) y
        // bloquea la petición hasta que salió el último correo del envío. Para
        // no dejar a n8n esperando una petición larga, prefiere `programar`
        // con un `programado_para` cercano (dentro de un par de minutos): el
        // scheduler `correo:procesar-programados` (cada minuto, ver
        // app/Console/Kernel.php) lo manda por su cuenta sin bloquear esta API.
        Route::post('/{envio}/enviar', [CorreoEnviosApiController::class, 'enviar'])->middleware('api.permiso:correo,escribir');
        Route::post('/{envio}/programar', [CorreoEnviosApiController::class, 'programar'])->middleware('api.permiso:correo,escribir');
        Route::post('/{envio}/cancelar', [CorreoEnviosApiController::class, 'cancelar'])->middleware('api.permiso:correo,escribir');

        Route::get('/{envio}', [CorreoEnviosApiController::class, 'show']);
    });
});
