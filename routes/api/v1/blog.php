<?php

use App\Http\Controllers\Api\V1\Blog\BlogApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('blog')->name('blog.')->middleware('api.permiso:blog')->group(function () {
    Route::get('/articulos', [BlogApiController::class, 'index']);
    Route::post('/articulos', [BlogApiController::class, 'store'])->middleware('api.permiso:blog,escribir');
    Route::post('/previsualizar', [BlogApiController::class, 'previsualizar']);
    Route::put('/articulos/{articulo}', [BlogApiController::class, 'update'])->middleware('api.permiso:blog,escribir');
    Route::delete('/articulos/{articulo}', [BlogApiController::class, 'destroy'])->middleware('api.permiso:blog,escribir');
    Route::post('/articulos/{articulo}/publicar', [BlogApiController::class, 'publicar'])->middleware('api.permiso:blog,escribir');
    Route::get('/articulos/{articulo}', [BlogApiController::class, 'show']);
});
