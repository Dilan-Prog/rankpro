<?php

use App\Http\Controllers\ReportePublicoController;
use App\Http\Middleware\ReportePublicoCabeceras;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Enlace público de solo lectura a la versión para cliente de un reporte
|--------------------------------------------------------------------------
|
| Se carga desde RouteServiceProvider (grupo web, sin auth). El token es de 48 caracteres
| aleatorios y en la base solo existe su hash; throttle por IP porque cualquiera puede
| probar tokens. Las cabeceras noindex/no-store las pone el middleware, también en errores.
*/
Route::middleware(['throttle:reporte-publico', ReportePublicoCabeceras::class])->group(function () {
    Route::get('/r/{token}', [ReportePublicoController::class, 'show'])->name('reporte-publico.show');
    Route::get('/r/{token}/pdf', [ReportePublicoController::class, 'pdf'])->name('reporte-publico.pdf');
});
