<?php

namespace App\Http\Controllers\Api;

use App\Enums\TipoConversion;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Support\Conversiones\RegistradorConversion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TrackingController extends Controller
{
    public function storeClic(Request $request): JsonResponse
    {
        /** @var Cliente $cliente */
        $cliente = $request->attributes->get('cliente');

        $data = $request->validate([
            'visitor_id' => ['required', 'string', 'max:64'],
            'gclid' => ['nullable', 'string', 'max:255'],
            'gbraid' => ['nullable', 'string', 'max:255'],
            'wbraid' => ['nullable', 'string', 'max:255'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
            'utm_term' => ['nullable', 'string', 'max:255'],
            'utm_content' => ['nullable', 'string', 'max:255'],
            'landing_url' => ['required', 'string', 'max:2048'],
            'referrer' => ['nullable', 'string', 'max:2048'],
        ]);

        $data['user_agent'] = $request->userAgent();
        $data['ip_address'] = $request->ip();

        RegistradorConversion::registrarClic($cliente, $data);

        return response()->json(['ok' => true], 201);
    }

    public function storeConversion(Request $request): JsonResponse
    {
        /** @var Cliente $cliente */
        $cliente = $request->attributes->get('cliente');

        $data = $request->validate([
            'visitor_id' => ['required', 'string', 'max:64'],
            'gclid' => ['nullable', 'string', 'max:255'],
            'gbraid' => ['nullable', 'string', 'max:255'],
            'wbraid' => ['nullable', 'string', 'max:255'],
            'tipo' => ['required', Rule::enum(TipoConversion::class)],
            'valor' => ['nullable', 'numeric', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ]);

        RegistradorConversion::registrarConversion($cliente, $data);

        return response()->json(['ok' => true], 201);
    }

    public function snippet(string $token)
    {
        $cliente = Cliente::whereNotNull('api_token')->where('api_token', $token)->first();

        $js = view('tracking.snippet', [
            'valido' => (bool) $cliente,
            'token' => $token,
            'apiBase' => rtrim(url('/api/tracking'), '/'),
        ])->render();

        return response($js, 200)
            ->header('Content-Type', 'application/javascript; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=300');
    }
}
