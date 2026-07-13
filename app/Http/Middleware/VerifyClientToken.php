<?php

namespace App\Http\Middleware;

use App\Models\Cliente;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica peticiones públicas del snippet de tracking por token de bearer
 * propio (header X-RankPro-Token), no por Sanctum: Cliente no es (ni debe
 * ser) un modelo Authenticatable — el token identifica al sitio, no a una
 * sesión de usuario.
 */
class VerifyClientToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-RankPro-Token');

        $cliente = $token
            ? Cliente::whereNotNull('api_token')->where('api_token', $token)->first()
            : null;

        if (! $cliente) {
            return response()->json(['message' => 'Token inválido'], 401);
        }

        $request->attributes->set('cliente', $cliente);

        return $next($request);
    }
}
