<?php

use App\Http\Controllers\Api\V1\SearchConsole\SearchConsoleApiController;
use Illuminate\Support\Facades\Route;

// Reutiliza el módulo `integraciones` (ya existe en Modulos::TODOS): leer lista, escribir dispara la sincronización.
Route::prefix('search-console')->name('search-console.')->middleware('api.permiso:integraciones')->group(function () {
    Route::get('/propiedades', [SearchConsoleApiController::class, 'index'])->name('propiedades');
    Route::post('/propiedades/{propiedad}/sincronizar', [SearchConsoleApiController::class, 'sincronizar'])
        ->middleware('api.permiso:integraciones,escribir')->name('propiedades.sincronizar');
});
