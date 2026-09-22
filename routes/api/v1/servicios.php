<?php

use App\Http\Controllers\Api\V1\Crm\ServiciosApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('servicios')->name('servicios.')->middleware('api.permiso:servicios')->group(function () {
    Route::get('/', [ServiciosApiController::class, 'index'])->name('index');
    Route::post('/', [ServiciosApiController::class, 'store'])->middleware('api.permiso:servicios,escribir')->name('store');

    Route::get('/{servicio}/eventos', [ServiciosApiController::class, 'eventos'])->name('eventos');

    Route::put('/{servicio}', [ServiciosApiController::class, 'update'])->middleware('api.permiso:servicios,escribir')->name('update');
    Route::delete('/{servicio}', [ServiciosApiController::class, 'destroy'])->middleware('api.permiso:servicios,escribir')->name('destroy');

    Route::get('/{servicio}', [ServiciosApiController::class, 'show'])->name('show');
});
