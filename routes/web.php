<?php

use App\Http\Controllers\Admin\AdsController;
use App\Http\Controllers\Admin\ArchivosController;
use App\Http\Controllers\Admin\BugController;
use App\Http\Controllers\Admin\ClientesController;
use App\Http\Controllers\Admin\ComunicacionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DesarrolloController;
use App\Http\Controllers\Admin\DocumentosController;
use App\Http\Controllers\Admin\FinanzasController;
use App\Http\Controllers\Admin\IntegracionesController;
use App\Http\Controllers\Admin\KeywordsController;
use App\Http\Controllers\Admin\ProyectoFaseController;
use App\Http\Controllers\Admin\QaController;
use App\Http\Controllers\Admin\RolesController;
use App\Http\Controllers\Admin\SeoBacklinkController;
use App\Http\Controllers\Admin\SeoContenidoController;
use App\Http\Controllers\Admin\SeoController;
use App\Http\Controllers\Admin\SeoFaseController;
use App\Http\Controllers\Admin\SeoPosicionController;
use App\Http\Controllers\Admin\ServiciosController;
use App\Http\Controllers\Admin\TareaController;
use App\Http\Controllers\Admin\UsersController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('pages.index');
})->name('home');

/*
|--------------------------------------------------------------------------
| Admin routes
|--------------------------------------------------------------------------
|
| Built out module by module. Now gated behind the `auth` middleware —
| routes/auth.php (Laravel Breeze) provides the `login` route it redirects
| guests to.
|
*/

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    Route::redirect('/', '/admin/dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('clientes')->name('clientes.')->group(function () {
        Route::get('/', [ClientesController::class, 'index'])->name('index');
        Route::get('/nuevo', [ClientesController::class, 'create'])->name('create');
        Route::post('/', [ClientesController::class, 'store'])->name('store');
        Route::get('/{cliente}/editar', [ClientesController::class, 'edit'])->name('edit');
        Route::put('/{cliente}', [ClientesController::class, 'update'])->name('update');
        Route::delete('/{cliente}', [ClientesController::class, 'destroy'])->name('destroy');
        Route::get('/{cliente}', [ClientesController::class, 'show'])->name('show');
    });

    Route::prefix('servicios')->name('servicios.')->group(function () {
        Route::get('/', [ServiciosController::class, 'index'])->name('index');
        Route::get('/nuevo', [ServiciosController::class, 'create'])->name('create');
        Route::post('/', [ServiciosController::class, 'store'])->name('store');
        Route::get('/{servicio}/editar', [ServiciosController::class, 'edit'])->name('edit');
        Route::put('/{servicio}', [ServiciosController::class, 'update'])->name('update');
        Route::delete('/{servicio}', [ServiciosController::class, 'destroy'])->name('destroy');
        Route::get('/{servicio}', [ServiciosController::class, 'show'])->name('show');
    });

    Route::prefix('seo')->name('seo.')->group(function () {
        Route::get('/', [SeoController::class, 'index'])->name('index');
        Route::get('/nueva', [SeoController::class, 'create'])->name('create');
        Route::post('/', [SeoController::class, 'store'])->name('store');
        Route::get('/{campana}/editar', [SeoController::class, 'edit'])->name('edit');
        Route::put('/{campana}', [SeoController::class, 'update'])->name('update');
        Route::delete('/{campana}', [SeoController::class, 'destroy'])->name('destroy');

        Route::post('/{campana}/fase/guardar', [SeoFaseController::class, 'guardar'])->name('fase.guardar');
        Route::post('/{campana}/fase/aprobar', [SeoFaseController::class, 'aprobar'])->name('fase.aprobar');
        Route::post('/{campana}/fase/retroceder', [SeoFaseController::class, 'retroceder'])->name('fase.retroceder');
        Route::post('/{campana}/fase/nuevo-ciclo', [SeoFaseController::class, 'nuevoCiclo'])->name('fase.nuevo-ciclo');
        Route::post('/{campana}/fase/cerrar', [SeoFaseController::class, 'cerrar'])->name('fase.cerrar');
        Route::post('/{campana}/fase/pausar', [SeoFaseController::class, 'pausar'])->name('fase.pausar');

        Route::post('/{campana}/posiciones', [SeoPosicionController::class, 'store'])->name('posiciones.store');
        Route::delete('/posiciones/{posicion}', [SeoPosicionController::class, 'destroy'])->name('posiciones.destroy');

        Route::post('/{campana}/backlinks', [SeoBacklinkController::class, 'store'])->name('backlinks.store');
        Route::delete('/backlinks/{backlink}', [SeoBacklinkController::class, 'destroy'])->name('backlinks.destroy');

        Route::post('/{campana}/contenido', [SeoContenidoController::class, 'store'])->name('contenido.store');
        Route::put('/contenido/{contenido}', [SeoContenidoController::class, 'update'])->name('contenido.update');
        Route::delete('/contenido/{contenido}', [SeoContenidoController::class, 'destroy'])->name('contenido.destroy');

        Route::get('/{campana}', [SeoController::class, 'show'])->name('show');
    });

    Route::prefix('keywords')->name('keywords.')->group(function () {
        Route::get('/', [KeywordsController::class, 'index'])->name('index');
        Route::get('/nueva', [KeywordsController::class, 'create'])->name('create');
        Route::post('/', [KeywordsController::class, 'store'])->name('store');
        Route::get('/{keyword}/editar', [KeywordsController::class, 'edit'])->name('edit');
        Route::put('/{keyword}', [KeywordsController::class, 'update'])->name('update');
        Route::delete('/{keyword}', [KeywordsController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('ads')->name('ads.')->group(function () {
        Route::get('/', [AdsController::class, 'index'])->name('index');
        Route::get('/nueva', [AdsController::class, 'create'])->name('create');
        Route::post('/', [AdsController::class, 'store'])->name('store');
        Route::get('/{campana}/editar', [AdsController::class, 'edit'])->name('edit');
        Route::put('/{campana}', [AdsController::class, 'update'])->name('update');
        Route::delete('/{campana}', [AdsController::class, 'destroy'])->name('destroy');
        Route::post('/{campana}/creativos', [AdsController::class, 'storeCreativo'])->name('creativos.store');
        Route::delete('/creativos/{creativo}', [AdsController::class, 'destroyCreativo'])->name('creativos.destroy');
        Route::get('/{campana}', [AdsController::class, 'show'])->name('show');
    });

    Route::prefix('desarrollo')->name('desarrollo.')->group(function () {
        Route::get('/', [DesarrolloController::class, 'index'])->name('index');
        Route::get('/nuevo', [DesarrolloController::class, 'create'])->name('create');
        Route::post('/', [DesarrolloController::class, 'store'])->name('store');
        Route::get('/{proyecto}/editar', [DesarrolloController::class, 'edit'])->name('edit');
        Route::put('/{proyecto}', [DesarrolloController::class, 'update'])->name('update');
        Route::delete('/{proyecto}', [DesarrolloController::class, 'destroy'])->name('destroy');

        Route::post('/{proyecto}/fase/guardar', [ProyectoFaseController::class, 'guardar'])->name('fase.guardar');
        Route::post('/{proyecto}/fase/aprobar', [ProyectoFaseController::class, 'aprobar'])->name('fase.aprobar');
        Route::post('/{proyecto}/fase/retroceder', [ProyectoFaseController::class, 'retroceder'])->name('fase.retroceder');

        Route::post('/{proyecto}/tareas', [TareaController::class, 'store'])->name('tareas.store');
        Route::put('/tareas/{tarea}', [TareaController::class, 'update'])->name('tareas.update');
        Route::delete('/tareas/{tarea}', [TareaController::class, 'destroy'])->name('tareas.destroy');

        Route::post('/{proyecto}/bugs', [BugController::class, 'store'])->name('bugs.store');
        Route::put('/bugs/{bug}', [BugController::class, 'update'])->name('bugs.update');
        Route::delete('/bugs/{bug}', [BugController::class, 'destroy'])->name('bugs.destroy');

        Route::post('/{proyecto}/comunicaciones', [ComunicacionController::class, 'store'])->name('comunicaciones.store');
        Route::delete('/comunicaciones/{comunicacion}', [ComunicacionController::class, 'destroy'])->name('comunicaciones.destroy');

        Route::post('/{proyecto}/qa', [QaController::class, 'store'])->name('qa.store');
        Route::put('/qa/{qa}', [QaController::class, 'update'])->name('qa.update');
        Route::delete('/qa/{qa}', [QaController::class, 'destroy'])->name('qa.destroy');

        Route::get('/{proyecto}', [DesarrolloController::class, 'show'])->name('show');
    });

    Route::prefix('finanzas')->name('finanzas.')->group(function () {
        Route::get('/', [FinanzasController::class, 'index'])->name('index');
        Route::get('/nuevo', [FinanzasController::class, 'create'])->name('create');
        Route::post('/', [FinanzasController::class, 'store'])->name('store');
        Route::get('/{finanza}/editar', [FinanzasController::class, 'edit'])->name('edit');
        Route::put('/{finanza}', [FinanzasController::class, 'update'])->name('update');
        Route::delete('/{finanza}', [FinanzasController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('archivos')->name('archivos.')->group(function () {
        Route::get('/', [ArchivosController::class, 'index'])->name('index');
        Route::get('/contratos/nuevo', [DocumentosController::class, 'createContrato'])->name('contratos.create');
        Route::post('/contratos/vista-previa', [DocumentosController::class, 'previewContrato'])->name('contratos.preview');
        Route::post('/contratos', [DocumentosController::class, 'storeContrato'])->name('contratos.store');
        Route::get('/propuestas/nuevo', [DocumentosController::class, 'createPropuesta'])->name('propuestas.create');
        Route::post('/propuestas/vista-previa', [DocumentosController::class, 'previewPropuesta'])->name('propuestas.preview');
        Route::post('/propuestas', [DocumentosController::class, 'storePropuesta'])->name('propuestas.store');
        Route::get('/{archivo}/descargar', [ArchivosController::class, 'download'])->name('download');
        Route::delete('/{archivo}', [ArchivosController::class, 'destroy'])->name('destroy');
    });

    Route::get('/integraciones', [IntegracionesController::class, 'index'])->name('integraciones.index');

    Route::prefix('roles')->name('roles.')->group(function () {
        Route::get('/', [RolesController::class, 'index'])->name('index');
        Route::get('/nuevo', [RolesController::class, 'create'])->name('create');
        Route::post('/', [RolesController::class, 'store'])->name('store');
        Route::get('/{role}/editar', [RolesController::class, 'edit'])->name('edit');
        Route::put('/{role}', [RolesController::class, 'update'])->name('update');
        Route::delete('/{role}', [RolesController::class, 'destroy'])->name('destroy');
    });

    Route::put('/usuarios/{user}', [UsersController::class, 'update'])->name('usuarios.update');
});

require __DIR__.'/auth.php';
