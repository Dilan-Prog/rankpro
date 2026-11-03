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

        // La habilidad del token no basta: el dueño debe seguir teniendo el permiso del módulo en su rol
        // (si se le quitó el permiso o le cambiaron el rol después, el token no puede conservar ese poder).
        if (! $request->user()->tienePermiso($modulo)) {
            return response()->json([
                'message' => "El usuario dueño del token no tiene permiso sobre el módulo {$modulo}.",
                'habilidad_requerida' => "{$modulo}:{$accion}",
            ], 403);
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
