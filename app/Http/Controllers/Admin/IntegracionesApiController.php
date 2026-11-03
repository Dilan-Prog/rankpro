<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Api\Modulos;
use App\Support\ProteccionAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Tokens de API para n8n u otras integraciones: Sanctum ya trae la tabla
 * personal_access_tokens y User ya usa HasApiTokens (ver Autenticación y
 * permisos.md), así que aquí solo se construye la UI de alta/baja y el
 * mapeo de habilidades por módulo (App\Support\Api\Modulos).
 */
class IntegracionesApiController extends Controller
{
    public function index(): View
    {
        $tokens = PersonalAccessToken::with('tokenable')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PersonalAccessToken $t) => [
                'id' => $t->id,
                'nombre' => $t->name,
                'habilidades' => $t->abilities,
                'propietario' => $t->tokenable?->name,
                'ultimo_uso' => $t->last_used_at?->diffForHumans(),
                'expira_en' => $t->expires_at?->format('Y-m-d H:i'),
                'creado_en' => $t->created_at?->format('Y-m-d'),
            ]);

        return view('admin.integraciones.api', [
            'tokens' => $tokens,
            'modulos' => Modulos::TODOS,
            'etiquetas' => collect(Modulos::TODOS)->mapWithKeys(fn ($m) => [$m => Modulos::etiqueta($m)]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:80'],
            'habilidades' => ['required', 'array', 'min:1'],
            'habilidades.*' => [Rule::in(Modulos::habilidades())],
            'expira_en' => ['nullable', 'date', 'after:now'],
        ]);

        $habilidades = Modulos::normalizar($data['habilidades']);

        // Un token no puede valer más que su creador: sin esto, quien solo tiene «integraciones»
        // se fabricaría un token usuarios:escribir (o '*') y escalaría privilegios por la API.
        $usuario = Auth::user();
        $noPermitidas = collect($habilidades)->filter(function (string $h) use ($usuario) {
            if ($h === '*') {
                return ! ProteccionAdmin::esAdmin($usuario);
            }

            return ! $usuario->tienePermiso(explode(':', $h)[0]);
        })->values();
        if ($noPermitidas->isNotEmpty()) {
            $mensaje = 'No puedes crear un token con habilidades que tu rol no tiene: '.$noPermitidas->implode(', ').'.';

            return response()->json(['message' => $mensaje, 'errors' => ['habilidades' => [$mensaje]]], 422);
        }
        $expira = $data['expira_en'] ?? null;

        $creado = Auth::user()->createToken($data['nombre'], $habilidades, $expira ? \Illuminate\Support\Carbon::parse($expira) : null);

        return response()->json([
            'ok' => true,
            'token' => $creado->plainTextToken,
            'row' => [
                'id' => $creado->accessToken->id,
                'nombre' => $creado->accessToken->name,
                'habilidades' => $creado->accessToken->abilities,
                'propietario' => Auth::user()->name,
                'ultimo_uso' => null,
                'expira_en' => $creado->accessToken->expires_at?->format('Y-m-d H:i'),
                'creado_en' => $creado->accessToken->created_at?->format('Y-m-d'),
            ],
        ], 201);
    }

    public function destroy(int $token): JsonResponse
    {
        DB::table('personal_access_tokens')->where('id', $token)->delete();

        return response()->json(['deleted' => true]);
    }

    public function docs(): View
    {
        return view('admin.integraciones.api-docs', [
            'modulos' => Modulos::TODOS,
            'etiquetas' => collect(Modulos::TODOS)->mapWithKeys(fn ($m) => [$m => Modulos::etiqueta($m)]),
        ]);
    }
}
