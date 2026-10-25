<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;

/**
 * n8n (y curl, y Postman) no siempre manda `Accept: application/json`. Sin
 * esto, una excepción de auth/validación en /api/v1/* intentaría redirigir a
 * `login` en vez de responder JSON.
 */
class AceptarJson
{
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');

        if (! config('api.habilitada', true)) {
            return response()->json(['message' => 'La API está deshabilitada temporalmente.'], 503);
        }

        // Laravel trata un JSON roto como cuerpo vacío y el cliente recibía un 422
        // engañoso de campos "obligatorios"; solo se revisa si hay cuerpo JSON (no GET/DELETE vacíos ni multipart).
        if ($request->isJson()) {
            $cuerpo = trim((string) $request->getContent());
            if ($cuerpo !== '') {
                json_decode($cuerpo);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return response()->json(['message' => 'JSON inválido: el cuerpo de la petición no se pudo interpretar.'], 400);
                }
            }
        }

        return $next($request);
    }
}
