<?php

use App\Http\Controllers\Admin\ContenidoController;
use App\Http\Controllers\Admin\ContenidoDocumentoController;
use App\Http\Controllers\Admin\ContenidoTablaController;
use Illuminate\Support\Facades\Route;

// Módulo «Contenido»: se carga dentro del grupo admin (auth, prefijo y nombre «admin.»).
// El `show` (GET /{elemento}) va al final para que el comodín no se trague las rutas literales.
Route::prefix('contenido')->name('contenido.')->middleware('permiso:contenido')->group(function () {
    Route::get('/', [ContenidoController::class, 'index'])->name('index');
    Route::post('/', [ContenidoController::class, 'store'])->name('store');
    Route::post('/subir', [ContenidoController::class, 'subir'])->name('subir');
    Route::post('/previsualizar', [ContenidoDocumentoController::class, 'previsualizar'])->name('previsualizar');

    Route::patch('/{elemento}', [ContenidoController::class, 'actualizar'])->name('actualizar');
    Route::delete('/{elemento}', [ContenidoController::class, 'destroy'])->name('destroy');
    Route::get('/{elemento}/descargar', [ContenidoController::class, 'descargar'])->name('descargar');

    Route::put('/{elemento}/documento', [ContenidoDocumentoController::class, 'guardar'])->name('documento.guardar');

    Route::put('/{elemento}/tabla', [ContenidoTablaController::class, 'guardar'])->name('tabla.guardar');
    Route::post('/{elemento}/tabla/importar', [ContenidoTablaController::class, 'importar'])->name('tabla.importar');
    Route::get('/{elemento}/tabla/exportar', [ContenidoTablaController::class, 'exportar'])->name('tabla.exportar');

    Route::get('/{elemento}', [ContenidoController::class, 'show'])->name('show');
});
