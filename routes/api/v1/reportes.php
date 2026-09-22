<?php

use App\Http\Controllers\Api\V1\Reportes\ReporteGeneracionApiController;
use App\Http\Controllers\Api\V1\Reportes\ReporteSeccionesApiController;
use App\Http\Controllers\Api\V1\Reportes\ReportesApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('reportes')->name('reportes.')->middleware('api.permiso:reportes')->group(function () {
    Route::get('/', [ReportesApiController::class, 'index']);
    Route::post('/', [ReportesApiController::class, 'store'])->middleware('api.permiso:reportes,escribir');

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
