<?php

use App\Http\Controllers\Api\V1\Conversiones\{
    AdsConversionColumnaApiController,
    AdsEmbudoEtapaApiController,
    ClicsApiController,
    ConversionesApiController,
};
use Illuminate\Support\Facades\Route;

Route::prefix('conversiones')->name('conversiones.')->middleware('api.permiso:conversiones')->group(function () {
    Route::get('/', [ConversionesApiController::class, 'index'])->name('index');
    Route::post('/', [ConversionesApiController::class, 'store'])->name('store')->middleware('api.permiso:conversiones,escribir');

    Route::get('/columnas', [AdsConversionColumnaApiController::class, 'index'])->name('columnas.index');
    Route::post('/columnas', [AdsConversionColumnaApiController::class, 'store'])->name('columnas.store')->middleware('api.permiso:conversiones,escribir');
    Route::put('/columnas/{columna}', [AdsConversionColumnaApiController::class, 'update'])->name('columnas.update')->middleware('api.permiso:conversiones,escribir');
    Route::delete('/columnas/{columna}', [AdsConversionColumnaApiController::class, 'destroy'])->name('columnas.destroy')->middleware('api.permiso:conversiones,escribir');

    Route::post('/{conversion}/etapa', [ConversionesApiController::class, 'asignarEtapa'])->name('etapa')->middleware('api.permiso:conversiones,escribir');
    Route::put('/{conversion}', [ConversionesApiController::class, 'update'])->name('update')->middleware('api.permiso:conversiones,escribir');
});

Route::prefix('clics')->name('clics.')->middleware('api.permiso:conversiones')->group(function () {
    Route::get('/', [ClicsApiController::class, 'index'])->name('index');
    Route::post('/', [ClicsApiController::class, 'store'])->name('store')->middleware('api.permiso:conversiones,escribir');
});

Route::prefix('clientes/{cliente}/embudo/etapas')->name('clientes.embudo.etapas.')->middleware('api.permiso:conversiones')->group(function () {
    Route::get('/', [AdsEmbudoEtapaApiController::class, 'index'])->name('index');
    Route::post('/', [AdsEmbudoEtapaApiController::class, 'store'])->name('store')->middleware('api.permiso:conversiones,escribir');
});

Route::prefix('embudo/etapas')->name('embudo.etapas.')->middleware('api.permiso:conversiones')->group(function () {
    Route::put('/{etapa}', [AdsEmbudoEtapaApiController::class, 'update'])->name('update')->middleware('api.permiso:conversiones,escribir');
    Route::delete('/{etapa}', [AdsEmbudoEtapaApiController::class, 'destroy'])->name('destroy')->middleware('api.permiso:conversiones,escribir');
});
