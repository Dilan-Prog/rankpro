<?php

use App\Http\Controllers\Admin\ConfiguracionMedicionController;
use Illuminate\Support\Facades\Route;

// Configuración > Medición y Google. Se registra antes que routes/web.php; el grupo
// `configuracion` de allá solo define /smtp, así que las rutas literales no chocan.
Route::prefix('configuracion/medicion')->name('configuracion.medicion.')->middleware('permiso:configuracion')->group(function () {
    Route::get('/', [ConfiguracionMedicionController::class, 'edit'])->name('edit');
    Route::put('/', [ConfiguracionMedicionController::class, 'update'])->name('update');
});
