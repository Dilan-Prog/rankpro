<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige el permiso de un módulo del panel (`permiso:clientes`).
 *
 * Va después de `auth`: si no hay usuario no decide nada y deja que el
 * middleware de autenticación redirija al login.
 */
class VerificarPermisoModulo
{
    public function handle(Request $request, Closure $next, string $modulo): Response
    {
        $usuario = $request->user();

        if ($usuario !== null && ! $usuario->tienePermiso($modulo)) {
            abort(403, 'Tu rol no tiene acceso a este módulo.');
        }

        return $next($request);
    }
}
