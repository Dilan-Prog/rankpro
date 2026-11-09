<?php

use App\Http\Controllers\Admin\ReporteEnlacesController;
use App\Http\Controllers\Admin\ReporteGeneracionController;
use App\Http\Controllers\Admin\ReportesController;
use Illuminate\Support\Facades\Route;

// Se incluye desde routes/web.php dentro del grupo admin (auth, prefijo y nombre «admin.»), antes del grupo
// de Reportes: ninguna de estas rutas choca con /reportes/{reporte}, pero así viven juntas las del flujo mensual.
Route::prefix('reportes')->name('reportes.')->middleware('permiso:reportes')->group(function () {
    // «Generar reporte del mes»: plantilla + mes calendario, con datos precargados.
    Route::post('/generar-mes', [ReportesController::class, 'generarMes'])->name('generar-mes');
    Route::post('/{reporte}/actualizar-datos', [ReportesController::class, 'actualizarDatos'])->name('actualizar-datos');

    // Versión para cliente (1 página) y su enlace de solo lectura.
    Route::get('/{reporte}/preview-cliente', [ReporteGeneracionController::class, 'previewCliente'])->name('preview-cliente');
    Route::post('/{reporte}/pdf-cliente', [ReporteGeneracionController::class, 'pdfCliente'])->name('pdf-cliente');
    Route::post('/{reporte}/enlaces', [ReporteEnlacesController::class, 'store'])->name('enlaces.store');
    Route::delete('/{reporte}/enlaces/{enlace}', [ReporteEnlacesController::class, 'destroy'])->name('enlaces.destroy');
});
