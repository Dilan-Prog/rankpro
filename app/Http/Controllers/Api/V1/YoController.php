<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Api\Respuesta;
use Illuminate\Http\Request;

class YoController extends ControladorApi
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        return Respuesta::recurso([
            'usuario' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'area' => $user->area?->value,
                'role' => $user->role?->name,
            ],
            'token' => $token ? [
                'id' => $token->id,
                'nombre' => $token->name,
                'habilidades' => $token->abilities,
                'expira_en' => $token->expires_at?->toIso8601String(),
                'ultimo_uso' => $token->last_used_at?->toIso8601String(),
            ] : null,
        ]);
    }
}
