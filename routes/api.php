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

Route::prefix('tracking')->name('tracking.')->group(function () {
    // Sin middleware de auth — token inválido = JS inerte 200, no 401 (un <script src> roto en el sitio del cliente es peor que uno que no hace nada).
    Route::get('/snippet/{token}.js', [TrackingController::class, 'snippet'])->name('snippet');

    Route::middleware(['client.token', 'throttle:tracking-public'])->group(function () {
        Route::post('/clic', [TrackingController::class, 'storeClic'])->name('clic');
        Route::post('/conversion', [TrackingController::class, 'storeConversion'])->name('conversion');
    });
});
