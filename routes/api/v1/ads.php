<?php

use App\Http\Controllers\Api\V1\Ads\{
    AdsCampanaApiController,
    AdsCreativoApiController,
    AdsFaseApiController,
    AdsGrupoApiController,
    AdsGrupoKeywordApiController,
    AdsKeywordColumnaApiController,
    AdsMetricaApiController,
    AdsOptimizacionApiController,
};
use Illuminate\Support\Facades\Route;

Route::prefix('ads/campanas')->name('ads.campanas.')->middleware('api.permiso:ads')->group(function () {
    Route::get('/', [AdsCampanaApiController::class, 'index'])->name('index');
    Route::post('/', [AdsCampanaApiController::class, 'store'])->name('store')->middleware('api.permiso:ads,escribir');

    Route::get('/{campana}/fase', [AdsFaseApiController::class, 'fase'])->name('fase');
    Route::post('/{campana}/fase/guardar', [AdsFaseApiController::class, 'guardar'])->name('fase.guardar')->middleware('api.permiso:ads,escribir');
    Route::post('/{campana}/fase/aprobar', [AdsFaseApiController::class, 'aprobar'])->name('fase.aprobar')->middleware('api.permiso:ads,escribir');
    Route::post('/{campana}/fase/retroceder', [AdsFaseApiController::class, 'retroceder'])->name('fase.retroceder')->middleware('api.permiso:ads,escribir');
    Route::post('/{campana}/fase/nuevo-ciclo', [AdsFaseApiController::class, 'nuevoCiclo'])->name('fase.nuevo-ciclo')->middleware('api.permiso:ads,escribir');
    Route::post('/{campana}/fase/cerrar', [AdsFaseApiController::class, 'cerrar'])->name('fase.cerrar')->middleware('api.permiso:ads,escribir');
    Route::post('/{campana}/fase/pausar', [AdsFaseApiController::class, 'pausar'])->name('fase.pausar')->middleware('api.permiso:ads,escribir');

    Route::get('/{campana}/grupos', [AdsGrupoApiController::class, 'index'])->name('grupos.index');
    Route::post('/{campana}/grupos', [AdsGrupoApiController::class, 'store'])->name('grupos.store')->middleware('api.permiso:ads,escribir');

    Route::post('/{campana}/creativos', [AdsCreativoApiController::class, 'store'])->name('creativos.store')->middleware('api.permiso:ads,escribir');
    Route::get('/{campana}/creativos', [AdsCreativoApiController::class, 'index'])->name('creativos.index');

    Route::get('/{campana}/metricas', [AdsMetricaApiController::class, 'index'])->name('metricas.index');
    Route::post('/{campana}/metricas', [AdsMetricaApiController::class, 'store'])->name('metricas.store')->middleware('api.permiso:ads,escribir');

    Route::get('/{campana}/optimizaciones', [AdsOptimizacionApiController::class, 'index'])->name('optimizaciones.index');
    Route::post('/{campana}/optimizaciones', [AdsOptimizacionApiController::class, 'store'])->name('optimizaciones.store')->middleware('api.permiso:ads,escribir');

    // Al final del grupo (como en el resto del proyecto): el comodín {campana} se traga las rutas literales declaradas después.
    Route::get('/{campana}', [AdsCampanaApiController::class, 'show'])->name('show');
    Route::put('/{campana}', [AdsCampanaApiController::class, 'update'])->name('update')->middleware('api.permiso:ads,escribir');
    Route::delete('/{campana}', [AdsCampanaApiController::class, 'destroy'])->name('destroy')->middleware('api.permiso:ads,escribir');
});

Route::prefix('ads/metricas')->name('ads.metricas.')->middleware('api.permiso:ads')->group(function () {
    // Carga en batch para la subida diaria/mensual desde n8n — cada fila trae su propio ads_campana_id.
    Route::post('/lote', [AdsMetricaApiController::class, 'lote'])->name('lote')->middleware('api.permiso:ads,escribir');
    Route::put('/{metrica}', [AdsMetricaApiController::class, 'update'])->name('update')->middleware('api.permiso:ads,escribir');
    Route::delete('/{metrica}', [AdsMetricaApiController::class, 'destroy'])->name('destroy')->middleware('api.permiso:ads,escribir');
});

Route::prefix('ads/grupos')->name('ads.grupos.')->middleware('api.permiso:ads')->group(function () {
    Route::get('/{grupo}', [AdsGrupoApiController::class, 'show'])->name('show');
    Route::put('/{grupo}', [AdsGrupoApiController::class, 'update'])->name('update')->middleware('api.permiso:ads,escribir');
    Route::delete('/{grupo}', [AdsGrupoApiController::class, 'destroy'])->name('destroy')->middleware('api.permiso:ads,escribir');

    Route::get('/{grupo}/keywords', [AdsGrupoKeywordApiController::class, 'index'])->name('keywords.index');
    Route::post('/{grupo}/keywords', [AdsGrupoKeywordApiController::class, 'store'])->name('keywords.store')->middleware('api.permiso:ads,escribir');

    Route::get('/{grupo}/columnas', [AdsKeywordColumnaApiController::class, 'index'])->name('columnas.index');
    Route::post('/{grupo}/columnas', [AdsKeywordColumnaApiController::class, 'store'])->name('columnas.store')->middleware('api.permiso:ads,escribir');
});

Route::prefix('ads/grupos/keywords')->name('ads.grupos.keywords.')->middleware('api.permiso:ads')->group(function () {
    Route::put('/{keyword}', [AdsGrupoKeywordApiController::class, 'update'])->name('update')->middleware('api.permiso:ads,escribir');
    Route::delete('/{keyword}', [AdsGrupoKeywordApiController::class, 'destroy'])->name('destroy')->middleware('api.permiso:ads,escribir');
});

Route::prefix('ads/grupos/columnas')->name('ads.grupos.columnas.')->middleware('api.permiso:ads')->group(function () {
    Route::put('/{columna}', [AdsKeywordColumnaApiController::class, 'update'])->name('update')->middleware('api.permiso:ads,escribir');
    Route::delete('/{columna}', [AdsKeywordColumnaApiController::class, 'destroy'])->name('destroy')->middleware('api.permiso:ads,escribir');
});

Route::prefix('ads/creativos')->name('ads.creativos.')->middleware('api.permiso:ads')->group(function () {
    Route::put('/{creativo}', [AdsCreativoApiController::class, 'update'])->name('update')->middleware('api.permiso:ads,escribir');
    Route::delete('/{creativo}', [AdsCreativoApiController::class, 'destroy'])->name('destroy')->middleware('api.permiso:ads,escribir');
});

Route::prefix('ads/optimizaciones')->name('ads.optimizaciones.')->middleware('api.permiso:ads')->group(function () {
    Route::put('/{optimizacion}', [AdsOptimizacionApiController::class, 'update'])->name('update')->middleware('api.permiso:ads,escribir');
    Route::delete('/{optimizacion}', [AdsOptimizacionApiController::class, 'destroy'])->name('destroy')->middleware('api.permiso:ads,escribir');
});
