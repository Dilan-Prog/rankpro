<?php

use App\Http\Controllers\Api\V1\Crm\ArchivosApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('archivos')->name('archivos.')->middleware('api.permiso:archivos')->group(function () {
    Route::get('/', [ArchivosApiController::class, 'index'])->name('index');
    Route::post('/', [ArchivosApiController::class, 'store'])->middleware('api.permiso:archivos,escribir')->name('store');

    Route::get('/{archivo}/url', [ArchivosApiController::class, 'url'])->name('url');
    Route::delete('/{archivo}', [ArchivosApiController::class, 'destroy'])->middleware('api.permiso:archivos,escribir')->name('destroy');

    // Al final del grupo: el comodín {archivo} se traga cualquier ruta literal declarada después.
    Route::get('/{archivo}', [ArchivosApiController::class, 'show'])->name('show');
});
