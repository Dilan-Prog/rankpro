<?php

use App\Http\Controllers\Api\V1\CatalogoController;
use App\Http\Controllers\Api\V1\YoController;
use Illuminate\Support\Facades\Route;

Route::get('/yo', YoController::class)->name('yo');
Route::get('/catalogo', CatalogoController::class)->name('catalogo');
