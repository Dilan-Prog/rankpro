<?php

use App\Http\Controllers\Api\V1\Crm\DashboardApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('dashboard')->name('dashboard.')->middleware('api.permiso:dashboard')->group(function () {
    Route::get('/resumen', [DashboardApiController::class, 'resumen'])->name('resumen');
});
