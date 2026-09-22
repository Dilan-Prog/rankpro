<?php

use App\Http\Controllers\Api\V1\Seo\SeoBacklinksApiController;
use App\Http\Controllers\Api\V1\Seo\SeoCampanasApiController;
use App\Http\Controllers\Api\V1\Seo\SeoContenidoApiController;
use App\Http\Controllers\Api\V1\Seo\SeoFaseApiController;
use App\Http\Controllers\Api\V1\Seo\SeoMetricasMensualesApiController;
use App\Http\Controllers\Api\V1\Seo\SeoOnPageApiController;
use App\Http\Controllers\Api\V1\Seo\SeoPosicionesApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 — SEO
|--------------------------------------------------------------------------
| Nido bajo /seo/campanas/{campana}/... para los sub-recursos: es lo más
| idiomático en REST y evita colisiones de nombres con Keywords (routes/
| api/v1/keywords.php), que vive aparte por ser un módulo con su propia
| habilidad ('keywords').
*/
Route::prefix('seo')->name('seo.')->middleware('api.permiso:seo')->group(function () {
    Route::get('/campanas', [SeoCampanasApiController::class, 'index'])->name('campanas.index');
    Route::get('/campanas/{campana}', [SeoCampanasApiController::class, 'show'])->name('campanas.show');
    Route::post('/campanas', [SeoCampanasApiController::class, 'store'])->middleware('api.permiso:seo,escribir')->name('campanas.store');
    Route::put('/campanas/{campana}', [SeoCampanasApiController::class, 'update'])->middleware('api.permiso:seo,escribir')->name('campanas.update');
    Route::delete('/campanas/{campana}', [SeoCampanasApiController::class, 'destroy'])->middleware('api.permiso:seo,escribir')->name('campanas.destroy');

    Route::get('/campanas/{campana}/fase', [SeoFaseApiController::class, 'estado'])->name('campanas.fase.estado');
    Route::post('/campanas/{campana}/fase/guardar', [SeoFaseApiController::class, 'guardar'])->middleware('api.permiso:seo,escribir')->name('campanas.fase.guardar');
    Route::post('/campanas/{campana}/fase/aprobar', [SeoFaseApiController::class, 'aprobar'])->middleware('api.permiso:seo,escribir')->name('campanas.fase.aprobar');
    Route::post('/campanas/{campana}/fase/retroceder', [SeoFaseApiController::class, 'retroceder'])->middleware('api.permiso:seo,escribir')->name('campanas.fase.retroceder');
    Route::post('/campanas/{campana}/fase/nuevo-ciclo', [SeoFaseApiController::class, 'nuevoCiclo'])->middleware('api.permiso:seo,escribir')->name('campanas.fase.nuevo-ciclo');
    Route::post('/campanas/{campana}/fase/cerrar', [SeoFaseApiController::class, 'cerrar'])->middleware('api.permiso:seo,escribir')->name('campanas.fase.cerrar');
    Route::post('/campanas/{campana}/fase/pausar', [SeoFaseApiController::class, 'pausar'])->middleware('api.permiso:seo,escribir')->name('campanas.fase.pausar');

    Route::get('/campanas/{campana}/posiciones', [SeoPosicionesApiController::class, 'index'])->name('campanas.posiciones.index');
    Route::post('/campanas/{campana}/posiciones', [SeoPosicionesApiController::class, 'store'])->middleware('api.permiso:seo,escribir')->name('campanas.posiciones.store');
    Route::delete('/posiciones/{posicion}', [SeoPosicionesApiController::class, 'destroy'])->middleware('api.permiso:seo,escribir')->name('posiciones.destroy');

    Route::get('/campanas/{campana}/backlinks', [SeoBacklinksApiController::class, 'index'])->name('campanas.backlinks.index');
    Route::post('/campanas/{campana}/backlinks', [SeoBacklinksApiController::class, 'store'])->middleware('api.permiso:seo,escribir')->name('campanas.backlinks.store');
    Route::delete('/backlinks/{backlink}', [SeoBacklinksApiController::class, 'destroy'])->middleware('api.permiso:seo,escribir')->name('backlinks.destroy');

    Route::get('/campanas/{campana}/contenido', [SeoContenidoApiController::class, 'index'])->name('campanas.contenido.index');
    Route::post('/campanas/{campana}/contenido', [SeoContenidoApiController::class, 'store'])->middleware('api.permiso:seo,escribir')->name('campanas.contenido.store');
    Route::put('/contenido/{contenido}', [SeoContenidoApiController::class, 'update'])->middleware('api.permiso:seo,escribir')->name('contenido.update');
    Route::delete('/contenido/{contenido}', [SeoContenidoApiController::class, 'destroy'])->middleware('api.permiso:seo,escribir')->name('contenido.destroy');

    Route::get('/campanas/{campana}/onpage', [SeoOnPageApiController::class, 'index'])->name('campanas.onpage.index');
    Route::post('/campanas/{campana}/onpage', [SeoOnPageApiController::class, 'store'])->middleware('api.permiso:seo,escribir')->name('campanas.onpage.store');
    Route::put('/onpage/{accion}', [SeoOnPageApiController::class, 'update'])->middleware('api.permiso:seo,escribir')->name('onpage.update');
    Route::delete('/onpage/{accion}', [SeoOnPageApiController::class, 'destroy'])->middleware('api.permiso:seo,escribir')->name('onpage.destroy');

    Route::get('/campanas/{campana}/metricas-mensuales', [SeoMetricasMensualesApiController::class, 'index'])->name('campanas.metricas-mensuales.index');
    Route::post('/campanas/{campana}/metricas-mensuales', [SeoMetricasMensualesApiController::class, 'store'])->middleware('api.permiso:seo,escribir')->name('campanas.metricas-mensuales.store');
    Route::put('/metricas-mensuales/{metrica}', [SeoMetricasMensualesApiController::class, 'update'])->middleware('api.permiso:seo,escribir')->name('metricas-mensuales.update');
    Route::delete('/metricas-mensuales/{metrica}', [SeoMetricasMensualesApiController::class, 'destroy'])->middleware('api.permiso:seo,escribir')->name('metricas-mensuales.destroy');
});
