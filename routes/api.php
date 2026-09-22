<?php

use App\Http\Controllers\Api\TrackingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| API v1 — RankPro <-> n8n
|--------------------------------------------------------------------------
|
| Cada módulo declara su propio archivo en routes/api/v1/*.php, con su
| propio prefijo y sus propias habilidades (api.permiso:{modulo}[,escribir]).
| Nadie añade rutas aquí directamente: así ningún agente/tarea vuelve a tocar
| este archivo después de crear el suyo en routes/api/v1/.
|
*/
Route::prefix('v1')->name('api.v1.')->middleware(['api.json', 'auth:sanctum', 'api.activo'])->group(function () {
    foreach (glob(base_path('routes/api/v1/*.php')) as $archivoDeModulo) {
        require $archivoDeModulo;
    }
});

// Descarga pública de archivos generados por la API v1 (PDF/XLSX de reportes y
// propuestas): fuera de auth:sanctum a propósito, protegida por firma temporal.
require base_path('routes/api_publico_archivos.php');

// Especificación OpenAPI: pública, sin token, para que Swagger UI / n8n la lean.
require base_path('routes/api_publico_openapi.php');

Route::prefix('tracking')->name('tracking.')->group(function () {
    // Sin middleware de auth — token inválido = JS inerte 200, no 401 (un <script src> roto en el sitio del cliente es peor que uno que no hace nada).
    Route::get('/snippet/{token}.js', [TrackingController::class, 'snippet'])->name('snippet');

    Route::middleware(['client.token', 'throttle:tracking-public'])->group(function () {
        Route::post('/clic', [TrackingController::class, 'storeClic'])->name('clic');
        Route::post('/conversion', [TrackingController::class, 'storeConversion'])->name('conversion');
    });
});
