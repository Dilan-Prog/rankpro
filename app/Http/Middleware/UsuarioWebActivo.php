<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Saca del panel a un usuario que fue desactivado mientras tenía la sesión abierta.
 *
 * El login ya rechaza a los inactivos, pero una sesión (o la cookie de «recordarme»)
 * ya emitida seguía viva: desactivar a alguien no le cortaba el acceso. Se comprueba
 * en cada petición porque is_active se lee del usuario recién cargado de la BD.
 * Logout() rota también el remember_token, así que la cookie persistente deja de valer.
 */
class UsuarioWebActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        // === false (no `!`): is_active es NOT NULL con default true en la BD; un modelo recién creado en memoria trae null y no es un desactivado.
        if ($usuario !== null && $usuario->is_active === false) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Tu cuenta está desactivada.'], 401);
            }

            return redirect()->route('login')->withErrors(['email' => 'Tu cuenta está desactivada. Contacta a un administrador.']);
        }

        return $next($request);
    }
}
