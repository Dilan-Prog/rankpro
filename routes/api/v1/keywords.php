<?php

use App\Http\Controllers\Api\V1\Keywords\KeywordImportApiController;
use App\Http\Controllers\Api\V1\Keywords\KeywordListasApiController;
use App\Http\Controllers\Api\V1\Keywords\KeywordMedicionesApiController;
use App\Http\Controllers\Api\V1\Keywords\KeywordsApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('keywords')->name('keywords.')->middleware('api.permiso:keywords')->group(function () {
    Route::get('/', [KeywordsApiController::class, 'index'])->name('index');
    Route::post('/bulk-descartar', [KeywordsApiController::class, 'bulkDescartar'])->middleware('api.permiso:keywords,escribir')->name('bulk-descartar');
    Route::get('/{keyword}', [KeywordsApiController::class, 'show'])->name('show');
    Route::post('/', [KeywordsApiController::class, 'store'])->middleware('api.permiso:keywords,escribir')->name('store');
    Route::put('/{keyword}', [KeywordsApiController::class, 'update'])->middleware('api.permiso:keywords,escribir')->name('update');
    Route::delete('/{keyword}', [KeywordsApiController::class, 'destroy'])->middleware('api.permiso:keywords,escribir')->name('destroy');

    Route::get('/listas', [KeywordListasApiController::class, 'index'])->name('listas.index');
    Route::post('/listas', [KeywordListasApiController::class, 'store'])->middleware('api.permiso:keywords,escribir')->name('listas.store');
    Route::get('/listas/{lista}', [KeywordListasApiController::class, 'show'])->name('listas.show');
    Route::put('/listas/{lista}', [KeywordListasApiController::class, 'update'])->middleware('api.permiso:keywords,escribir')->name('listas.update');
    Route::delete('/listas/{lista}', [KeywordListasApiController::class, 'destroy'])->middleware('api.permiso:keywords,escribir')->name('listas.destroy');

    Route::post('/listas/{lista}/importar', [KeywordImportApiController::class, 'store'])->middleware('api.permiso:keywords,escribir')->name('listas.importar');

    Route::get('/listas/{lista}/mediciones', [KeywordMedicionesApiController::class, 'index'])->name('listas.mediciones.index');
    Route::post('/listas/{lista}/mediciones', [KeywordMedicionesApiController::class, 'store'])->middleware('api.permiso:keywords,escribir')->name('listas.mediciones.store');
    Route::post('/mediciones/lote', [KeywordMedicionesApiController::class, 'lote'])->middleware('api.permiso:keywords,escribir')->name('mediciones.lote');
    Route::put('/mediciones/{medicion}', [KeywordMedicionesApiController::class, 'update'])->middleware('api.permiso:keywords,escribir')->name('mediciones.update');
    Route::delete('/mediciones/{medicion}', [KeywordMedicionesApiController::class, 'destroy'])->middleware('api.permiso:keywords,escribir')->name('mediciones.destroy');
});
