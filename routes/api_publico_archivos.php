<?php

use App\Http\Controllers\Api\Publico\ArchivoDescargaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Descarga pública de archivos generados por la API v1
|--------------------------------------------------------------------------
|
| Fuera de auth:sanctum a propósito: n8n (o quien reciba la URL) descarga el
| PDF/XLSX con la URL firmada temporal que devuelven los endpoints de
| /api/v1/reportes/{reporte}/pdf|xlsx y /api/v1/propuestas/{propuesta}/pdf,
| sin tener que mandar el Bearer token para un simple GET de archivo.
|
| AGENTE D (Reportes/Propuestas/Correo/Desarrollo/Blog): este archivo NO se
| auto-incluye (no vive en routes/api/v1/*). El coordinador debe agregar
| `require base_path('routes/api_publico_archivos.php');` en routes/api.php,
| fuera del grupo `auth:sanctum` de /api/v1.
|
*/
Route::get('/archivos/{archivo}/descargar', ArchivoDescargaController::class)
    ->middleware('signed')
    ->name('api.publico.archivos.descargar');
