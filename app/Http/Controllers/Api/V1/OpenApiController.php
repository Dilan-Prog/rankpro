<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Support\Facades\Route;

/**
 * Esqueleto de OpenAPI 3.0 generado a partir de las rutas registradas: no
 * infiere el schema de cada cuerpo (para eso hace falta anotar cada regla de
 * validación, fuera de alcance de esta primera versión), pero da a n8n/
 * Swagger la lista real de rutas, métodos y el esquema de autenticación.
 * Sin caché deliberadamente: son pocas rutas y esto no se llama con
 * frecuencia; si se vuelve un problema, envolver en Cache::remember().
 */
class OpenApiController
{
    public function __invoke()
    {
        $paths = [];

        foreach (Route::getRoutes() as $ruta) {
            if (! str_starts_with($ruta->uri(), 'api/v1/')) {
                continue;
            }

            $path = substr($ruta->uri(), strlen('api/v1')) ?: '/';
            $nombre = $ruta->getName();

            foreach ($ruta->methods() as $metodo) {
                if ($metodo === 'HEAD') {
                    continue;
                }

                $paths[$path][strtolower($metodo)] = [
                    'summary' => $nombre ?: $path,
                    'operationId' => $nombre,
                    'security' => [['bearerAuth' => []]],
                    'responses' => ['200' => ['description' => 'OK']],
                ];
            }
        }

        ksort($paths);

        return response()->json([
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'RankPro API',
                'version' => 'v1',
                'description' => 'API para integrar RankPro con n8n u otras herramientas de automatización.',
            ],
            'servers' => [['url' => url('/api/v1')]],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => ['type' => 'http', 'scheme' => 'bearer'],
                ],
            ],
            'paths' => $paths,
        ]);
    }
}
