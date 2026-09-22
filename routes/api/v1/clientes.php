<?php

use App\Http\Controllers\Api\V1\Crm\ClientesApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('clientes')->name('clientes.')->middleware('api.permiso:clientes')->group(function () {
    Route::get('/', [ClientesApiController::class, 'index'])->name('index');
    Route::post('/', [ClientesApiController::class, 'store'])->middleware('api.permiso:clientes,escribir')->name('store');

    Route::get('/{cliente}/servicios', [ClientesApiController::class, 'servicios'])->name('servicios');
    Route::get('/{cliente}/finanzas', [ClientesApiController::class, 'finanzas'])->name('finanzas');
    Route::get('/{cliente}/archivos', [ClientesApiController::class, 'archivos'])->name('archivos');
    Route::get('/{cliente}/keywords', [ClientesApiController::class, 'keywords'])->name('keywords');
    Route::get('/{cliente}/clics', [ClientesApiController::class, 'clics'])->name('clics');
    Route::get('/{cliente}/conversiones', [ClientesApiController::class, 'conversiones'])->name('conversiones');
    Route::post('/{cliente}/token', [ClientesApiController::class, 'token'])->middleware('api.permiso:clientes,escribir')->name('token');

    Route::put('/{cliente}', [ClientesApiController::class, 'update'])->middleware('api.permiso:clientes,escribir')->name('update');
    Route::delete('/{cliente}', [ClientesApiController::class, 'destroy'])->middleware('api.permiso:clientes,escribir')->name('destroy');

    // Al final del grupo: el comodín {cliente} se traga cualquier ruta literal declarada después.
    Route::get('/{cliente}', [ClientesApiController::class, 'show'])->name('show');
});
