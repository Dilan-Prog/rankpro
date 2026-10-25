<?php

use App\Http\Controllers\Api\V1\Reportes\ReporteGeneracionApiController;
use App\Http\Controllers\Api\V1\Reportes\ReporteProgramacionesApiController;
use App\Http\Controllers\Api\V1\Reportes\ReporteSeccionesApiController;
use App\Http\Controllers\Api\V1\Reportes\ReportesApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('reportes')->name('reportes.')->middleware('api.permiso:reportes')->group(function () {
    Route::get('/', [ReportesApiController::class, 'index']);
    Route::post('/', [ReportesApiController::class, 'store'])->middleware('api.permiso:reportes,escribir');

    // Antes de los comodines {reporte}: 'programados' no es un id de reporte.
    Route::prefix('programados')->group(function () {
        Route::get('/', [ReporteProgramacionesApiController::class, 'index'])->name('programados.index');
        Route::post('/', [ReporteProgramacionesApiController::class, 'store'])->middleware('api.permiso:reportes,escribir')->name('programados.store');
        Route::put('/{programacion}', [ReporteProgramacionesApiController::class, 'update'])->middleware('api.permiso:reportes,escribir')->name('programados.update');
        Route::delete('/{programacion}', [ReporteProgramacionesApiController::class, 'destroy'])->middleware('api.permiso:reportes,escribir')->name('programados.destroy');
        Route::post('/{programacion}/pausar', [ReporteProgramacionesApiController::class, 'pausar'])->middleware('api.permiso:reportes,escribir')->name('programados.pausar');
        Route::post('/{programacion}/activar', [ReporteProgramacionesApiController::class, 'activar'])->middleware('api.permiso:reportes,escribir')->name('programados.activar');
        Route::post('/{programacion}/enviar', [ReporteProgramacionesApiController::class, 'enviar'])->middleware('api.permiso:reportes,escribir')->name('programados.enviar');
        Route::post('/{programacion}/probar', [ReporteProgramacionesApiController::class, 'probar'])->middleware('api.permiso:reportes,escribir')->name('programados.probar');
        Route::get('/{programacion}', [ReporteProgramacionesApiController::class, 'show'])->name('programados.show');
    });

    // scopeBindings(): {seccion} se resuelve DENTRO del {reporte} de la URL
    // (mismo patrón que routes/web.php) para que una sección de otro reporte
    // devuelva 404 en vez de dejarse tocar solo por saber su id.
    Route::scopeBindings()->group(function () {
        Route::get('/{reporte}/secciones', [ReporteSeccionesApiController::class, 'index']);
        Route::post('/{reporte}/secciones', [ReporteSeccionesApiController::class, 'store'])
            ->middleware('api.permiso:reportes,escribir');
        Route::put('/{reporte}/secciones/{seccion}', [ReporteSeccionesApiController::class, 'update'])
            ->middleware('api.permiso:reportes,escribir');
        Route::delete('/{reporte}/secciones/{seccion}', [ReporteSeccionesApiController::class, 'destroy'])
            ->middleware('api.permiso:reportes,escribir');
        Route::post('/{reporte}/secciones/reordenar', [ReporteSeccionesApiController::class, 'reordenar'])
            ->middleware('api.permiso:reportes,escribir');
    });

    Route::post('/{reporte}/pdf', [ReporteGeneracionApiController::class, 'pdf'])
        ->middleware('api.permiso:reportes,escribir');
    Route::post('/{reporte}/xlsx', [ReporteGeneracionApiController::class, 'xlsx'])
        ->middleware('api.permiso:reportes,escribir');

    Route::put('/{reporte}', [ReportesApiController::class, 'update'])->middleware('api.permiso:reportes,escribir');
    Route::delete('/{reporte}', [ReportesApiController::class, 'destroy'])->middleware('api.permiso:reportes,escribir');

    // Al final del grupo, como en el controlador web: el comodín {reporte} se
    // traga cualquier ruta literal declarada después.
    Route::get('/{reporte}', [ReportesApiController::class, 'show']);
});
