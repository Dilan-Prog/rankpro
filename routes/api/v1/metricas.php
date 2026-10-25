<?php

use App\Http\Controllers\Api\V1\Metricas\MetricasApiController;
use Illuminate\Support\Facades\Route;

/*
| El lote vive bajo /clientes/{cliente}/... porque es lo que n8n tiene a mano
| (el cliente), pero se protege con la habilidad 'metricas', no con la de 'clientes'.
*/
Route::prefix('metricas')->name('metricas.')->middleware('api.permiso:metricas')->group(function () {
    Route::get('/', [MetricasApiController::class, 'index'])->name('index');
    Route::post('/', [MetricasApiController::class, 'store'])->middleware('api.permiso:metricas,escribir')->name('store');

    Route::get('/{metrica}/valores', [MetricasApiController::class, 'valores'])->name('valores');
    Route::put('/{metrica}/valores/{periodo}', [MetricasApiController::class, 'guardarValor'])->middleware('api.permiso:metricas,escribir')->name('valores.guardar');

    Route::put('/{metrica}', [MetricasApiController::class, 'update'])->middleware('api.permiso:metricas,escribir')->name('update');
    Route::delete('/{metrica}', [MetricasApiController::class, 'destroy'])->middleware('api.permiso:metricas,escribir')->name('destroy');
    // Al final del grupo: el comodín {metrica} se traga cualquier ruta literal declarada después.
    Route::get('/{metrica}', [MetricasApiController::class, 'show'])->name('show');
});

Route::post('/clientes/{cliente}/metricas/valores', [MetricasApiController::class, 'lote'])
    ->middleware('api.permiso:metricas,escribir')->name('clientes.metricas.valores.lote');
