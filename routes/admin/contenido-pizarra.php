<?php

use App\Http\Controllers\Admin\ContenidoPizarraController;
use Illuminate\Support\Facades\Route;

// Pizarra del módulo «Contenido». Rutas de dos segmentos (/{elemento}/pizarra): no chocan con el
// comodín GET /{elemento} de contenido.php, pero van en su propio archivo para no tocar ese.
Route::prefix('contenido')->name('contenido.')->middleware('permiso:contenido')->group(function () {
    Route::get('/{elemento}/pizarra', [ContenidoPizarraController::class, 'editar'])->name('pizarra');
    Route::put('/{elemento}/pizarra', [ContenidoPizarraController::class, 'guardar'])->name('pizarra.guardar');
});
