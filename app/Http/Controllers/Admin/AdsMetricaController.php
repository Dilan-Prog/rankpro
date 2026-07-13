<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdsCampana;
use App\Models\AdsMetrica;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdsMetricaController extends Controller
{
    public function store(Request $request, AdsCampana $campana): JsonResponse
    {
        $data = $this->validated($request, $campana);
        $data['cliente_id'] = $campana->cliente_id;

        $metrica = $campana->metricas()->create($data);

        return response()->json($metrica, 201);
    }

    public function update(Request $request, AdsMetrica $metrica): JsonResponse
    {
        $data = $this->validated($request, $metrica->adsCampana, $metrica->id);

        $metrica->update($data);

        return response()->json($metrica->fresh());
    }

    public function destroy(AdsMetrica $metrica): JsonResponse
    {
        $metrica->delete();

        return response()->json(['deleted' => true]);
    }

    private function validated(Request $request, AdsCampana $campana, ?int $ignoreId = null): array
    {
        return $request->validate([
            // ads_metricas has a unique (ads_campana_id, mes, anio) index — surface it as a validation error instead of a 500.
            'mes' => ['required', 'integer', 'min:1', 'max:12',
                Rule::unique('ads_metricas')->where('ads_campana_id', $campana->id)->where('anio', $request->integer('anio'))->ignore($ignoreId),
            ],
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'inversion_real' => ['nullable', 'numeric', 'min:0'],
            'impresiones' => ['nullable', 'integer', 'min:0'],
            'clics' => ['nullable', 'integer', 'min:0'],
            'ctr' => ['nullable', 'numeric', 'min:0'],
            'cpc' => ['nullable', 'numeric', 'min:0'],
            'conversiones' => ['nullable', 'integer', 'min:0'],
            'cpl' => ['nullable', 'numeric', 'min:0'],
            'cpa' => ['nullable', 'numeric', 'min:0'],
            'roas' => ['nullable', 'numeric', 'min:0'],
            'valor_conversion' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
