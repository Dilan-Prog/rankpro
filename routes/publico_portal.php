<?php

use App\Http\Controllers\Portal\InicioController;
use App\Http\Controllers\Portal\LogoController;
use App\Http\Controllers\Portal\MetricasController;
use App\Http\Controllers\Portal\NoEncontradoController;
use App\Http\Controllers\Portal\PropuestasController;
use App\Http\Controllers\Portal\ReportesController;
use App\Http\Middleware\PortalAcceso;
use App\Http\Middleware\PortalCabeceras;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/*
|--------------------------------------------------------------------------
| Portal de solo lectura del cliente final (enlace mágico)
|--------------------------------------------------------------------------
|
| Se carga desde RouteServiceProvider (grupo web, sin auth). Orden de los middleware: cabeceras
| (envuelven también el 404/410/429) y PortalAcceso (límite por IP y resolución del token).
| Solo GET; el catch-all del final cubre rutas y métodos desconocidos con el mismo trato.
*/
// Sin sesión ni cookies: el portal no autentica a nadie (el enlace es la credencial), así que no
// deja ninguna cookie en el navegador del cliente ni responde 419 a un POST perdido.
Route::prefix('portal/{token}')->name('portal.')
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, AddQueuedCookiesToResponse::class, EncryptCookies::class, VerifyCsrfToken::class])
    ->middleware([PortalCabeceras::class, PortalAcceso::class])
    ->group(function () {
        Route::get('/', InicioController::class)->name('inicio');
        Route::get('/reportes', [ReportesController::class, 'index'])->name('reportes');
        Route::get('/reportes/descargar/{entrega}', [ReportesController::class, 'descargar'])->whereNumber('entrega')->name('reportes.descargar');
        Route::get('/propuestas', [PropuestasController::class, 'index'])->name('propuestas');
        Route::get('/propuestas/descargar/{propuesta}', [PropuestasController::class, 'descargar'])->whereNumber('propuesta')->name('propuestas.descargar');
        Route::get('/metricas', MetricasController::class)->name('metricas');
        Route::get('/logo', LogoController::class)->name('logo');
        Route::any('/{resto?}', NoEncontradoController::class)->where('resto', '.*')->name('desconocida');
    });
