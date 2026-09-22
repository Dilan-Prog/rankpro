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

        return $next($request);
    }
}
