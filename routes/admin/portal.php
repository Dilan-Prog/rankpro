<?php

use App\Http\Controllers\Admin\PortalAccesosController;
use Illuminate\Support\Facades\Route;

// Se incluye desde routes/web.php dentro del grupo admin (auth, prefijo y nombre «admin.»).
// Cuelga del Resumen de cliente, así que usa el permiso del módulo Clientes.
Route::prefix('clientes/{cliente}/portal')->name('clientes.portal.')->middleware('permiso:clientes')->group(function () {
    Route::post('/', [PortalAccesosController::class, 'store'])->name('store');
    Route::delete('/{acceso}', [PortalAccesosController::class, 'destroy'])->name('destroy');
});
