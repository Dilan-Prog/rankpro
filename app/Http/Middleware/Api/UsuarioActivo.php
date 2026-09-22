<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;

class UsuarioActivo
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            return response()->json(['message' => 'Usuario desactivado.'], 403);
        }

        return $next($request);
    }
}
