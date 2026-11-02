<?php

use App\Http\Controllers\Admin\ContenidoTablaController;
use Illuminate\Support\Facades\Route;

// Tabla editable (etapa 2): cambio de tipo de columna y «Llenar desde...».
// Archivo aparte de contenido.php; se carga ANTES (el glob ordena «-» antes que «.») y todas sus rutas
// tienen forma /{elemento}/tabla/..., así que ningún comodín de contenido.php las puede tapar.
Route::prefix('contenido')->name('contenido.')->middleware('permiso:contenido')->group(function () {
    Route::post('/{elemento}/tabla/convertir-columna', [ContenidoTablaController::class, 'convertirColumna'])->name('tabla.convertir-columna');
    Route::post('/{elemento}/tabla/llenar', [ContenidoTablaController::class, 'llenar'])->name('tabla.llenar');
});
