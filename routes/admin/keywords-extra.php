<?php

use App\Http\Controllers\Admin\KeywordExportController;
use Illuminate\Support\Facades\Route;

// Se incluye desde routes/web.php dentro del grupo admin, ANTES del grupo `keywords` de web.php. Los dos
// grupos comparten prefijo y nombre: las rutas de aquí son GET con segmentos literales (`exportar`) y no
// chocan con los comodines existentes (`PUT|DELETE /{keyword}`, `GET /{keyword}/mediciones`).
Route::prefix('keywords')->name('keywords.')->middleware('permiso:keywords')->group(function () {
    Route::get('/exportar', [KeywordExportController::class, 'seleccion'])->name('exportar-seleccion');
    Route::get('/listas/{lista}/exportar', [KeywordExportController::class, 'lista'])->name('exportar-lista');
});
