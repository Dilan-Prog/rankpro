<?php

use App\Http\Controllers\Admin\AltaClienteController;
use App\Http\Controllers\Admin\ImportacionClientesController;
use Illuminate\Support\Facades\Route;

// Se incluye desde routes/web.php dentro del grupo admin (auth, prefijo y nombre «admin.»). Solo POST: no choca con el GET /{cliente} del CRM.
Route::prefix('clientes')->name('clientes.')->middleware('permiso:clientes')->group(function () {
    Route::post('/asistente', [AltaClienteController::class, 'store'])->name('asistente.store');
    Route::post('/asistente/validar', [AltaClienteController::class, 'validarPaso'])->name('asistente.validar');
    Route::post('/asistente/servicios', [AltaClienteController::class, 'resumenServicios'])->name('asistente.servicios');

    Route::post('/importar/vista-previa', [ImportacionClientesController::class, 'vistaPrevia'])->name('importar.vista-previa');
    Route::post('/importar', [ImportacionClientesController::class, 'importar'])->name('importar.store');
});
