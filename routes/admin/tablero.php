<?php

use App\Http\Controllers\Admin\TableroController;
use Illuminate\Support\Facades\Route;

// Se incluye desde routes/web.php dentro del grupo admin (auth, prefijo y nombre «admin.»).
// Se carga ANTES que el grupo de Reportes de web.php: así /reportes/tablero no lo captura /reportes/{reporte}.
Route::prefix('reportes/tablero')->name('reportes.tablero.')->middleware('permiso:reportes')->group(function () {
    Route::get('/', [TableroController::class, 'index'])->name('index');
});
