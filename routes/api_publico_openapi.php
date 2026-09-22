<?php

use App\Http\Controllers\Api\V1\OpenApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Especificación OpenAPI (pública, sin auth)
|--------------------------------------------------------------------------
|
| Fuera del grupo v1/auth:sanctum a propósito: Swagger UI y n8n necesitan
| poder leerla sin un token. No expone datos, solo la forma de las rutas.
|
*/
Route::get('/v1/openapi.json', OpenApiController::class)->name('api.v1.openapi');
