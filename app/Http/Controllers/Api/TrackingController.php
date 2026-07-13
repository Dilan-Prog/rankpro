<?php

namespace App\Http\Controllers\Api;

use App\Enums\TipoConversion;
use App\Http\Controllers\Controller;
use App\Models\AdsCampana;
use App\Models\AdsClic;
use App\Models\Cliente;
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

        $data['ads_campana_id'] = $this->matchCampana($cliente, $data['utm_campaign'] ?? null);
        $data['user_agent'] = $request->userAgent();
        $data['ip_address'] = $request->ip();

        $cliente->adsClics()->create($data);

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

        $clic = $cliente->adsClics()->where('visitor_id', $data['visitor_id'])->latest('created_at')->first();

        // Respaldo server-side: si el navegador no traía el gclid (localStorage limpiado), se copia del último clic conocido de ese visitor_id.
        foreach (['gclid', 'gbraid', 'wbraid'] as $campo) {
            if (empty($data[$campo]) && $clic) {
                $data[$campo] = $clic->{$campo};
            }
        }

        $data['ads_clic_id'] = $clic?->id;

        $cliente->adsConversiones()->create($data);

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

    /**
     * Match best-effort por nombre de campaña — solo si hay coincidencia
     * única, nunca adivina entre varias.
     */
    private function matchCampana(Cliente $cliente, ?string $utmCampaign): ?int
    {
        if (blank($utmCampaign)) {
            return null;
        }

        $campanas = AdsCampana::where('cliente_id', $cliente->id)
            ->whereRaw('LOWER(nombre) = ?', [strtolower($utmCampaign)])
            ->pluck('id');

        return $campanas->count() === 1 ? $campanas->first() : null;
    }
}
