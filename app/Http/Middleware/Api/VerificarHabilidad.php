<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;

/**
 * Habilidades de token: '{modulo}:leer', '{modulo}:escribir' o '*'. Escribir
 * implica leer. Un token creado antes de este middleware (o de sesión web sin
 * token de Sanctum) no tiene currentAccessToken() -> 403 siempre: la API solo
 * se usa con un token explícito, nunca "gratis" por estar logueado.
 */
class VerificarHabilidad
{
    public function handle(Request $request, Closure $next, string $modulo, string $accion = 'leer')
    {
        $token = $request->user()?->currentAccessToken();

        if (! $token) {
            return response()->json(['message' => 'Esta ruta requiere un token de API.'], 403);
        }

        $requerida = "{$modulo}:{$accion}";
        $autorizado = $token->can('*')
            || $token->can("{$modulo}:escribir")
            || ($accion === 'leer' && $token->can("{$modulo}:leer"));

        if (! $autorizado) {
            return response()->json([
                'message' => "El token no tiene la habilidad {$requerida}.",
                'habilidad_requerida' => $requerida,
            ], 403);
        }

        return $next($request);
    }
}
