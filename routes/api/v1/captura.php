<?php

use App\Http\Controllers\Api\V1\Captura\CapturaApiController;
use Illuminate\Support\Facades\Route;

Route::get('/captura', CapturaApiController::class)->middleware('api.permiso:captura')->name('captura');
