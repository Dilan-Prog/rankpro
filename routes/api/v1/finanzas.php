<?php

use App\Http\Controllers\Api\V1\Crm\FinanzasApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('finanzas')->name('finanzas.')->middleware('api.permiso:finanzas')->group(function () {
    Route::get('/', [FinanzasApiController::class, 'index'])->name('index');
    Route::get('/resumen', [FinanzasApiController::class, 'resumen'])->name('resumen');
    Route::post('/', [FinanzasApiController::class, 'store'])->middleware('api.permiso:finanzas,escribir')->name('store');

    Route::post('/{finanza}/pagar', [FinanzasApiController::class, 'pagar'])->middleware('api.permiso:finanzas,escribir')->name('pagar');
    Route::put('/{finanza}', [FinanzasApiController::class, 'update'])->middleware('api.permiso:finanzas,escribir')->name('update');
    Route::delete('/{finanza}', [FinanzasApiController::class, 'destroy'])->middleware('api.permiso:finanzas,escribir')->name('destroy');

    // Al final del grupo: el comodín {finanza} se traga cualquier ruta literal declarada después.
    Route::get('/{finanza}', [FinanzasApiController::class, 'show'])->name('show');
});
