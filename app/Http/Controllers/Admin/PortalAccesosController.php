<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\PortalAcceso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Enlaces mágicos del portal del cliente. El token en claro viaja una sola vez, en la respuesta que
 * lo crea: después solo existe su hash, así que un enlace perdido se revoca y se crea otro.
 */
class PortalAccesosController extends Controller
{
    public function store(Request $request, Cliente $cliente): JsonResponse
    {
        $datos = $request->validate([
            'etiqueta' => ['nullable', 'string', 'max:120'],
            'correo' => ['nullable', 'email', 'max:190'],
            'vigencia_dias' => ['nullable', 'integer', Rule::in(PortalAcceso::VIGENCIAS)],
        ]);

        $nuevo = PortalAcceso::crearPara(
            $cliente,
            Auth::id(),
            (int) ($datos['vigencia_dias'] ?? PortalAcceso::DIAS_VIGENCIA),
            filled($datos['etiqueta'] ?? null) ? trim($datos['etiqueta']) : null,
            filled($datos['correo'] ?? null) ? trim($datos['correo']) : null,
        );

        return response()->json([
            'acceso' => $nuevo['acceso']->toRow(),
            'url' => $nuevo['url'],
            'aviso' => 'Copia el enlace ahora: por seguridad no se vuelve a mostrar.',
        ], 201);
    }

    public function destroy(Cliente $cliente, PortalAcceso $acceso): JsonResponse
    {
        // El enlace debe ser de ESTE cliente: la URL no puede revocar el de otro.
        abort_unless($acceso->cliente_id === $cliente->id, 404);

        if ($acceso->revocado_en === null) {
            $acceso->forceFill(['revocado_en' => now()])->save();
        }

        return response()->json($acceso->toRow());
    }
}
