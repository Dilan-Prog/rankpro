<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoCampana;
use App\Models\SeoMetricaMensual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SeoMetricaMensualController extends Controller
{
    public function store(Request $request, SeoCampana $campana): JsonResponse
    {
        $data = $this->withZeroDefaults($this->validated($request, $campana));
        $data['ciclo'] = $campana->ciclo_actual;

        $metrica = $campana->metricasMensuales()->create($data);

        return response()->json($metrica, 201);
    }

    public function update(Request $request, SeoMetricaMensual $metrica): JsonResponse
    {
        $data = $this->withZeroDefaults($this->validated($request, $metrica->seoCampana, $metrica->id));

        $metrica->update($data);

        return response()->json($metrica->fresh());
    }

    /** Los campos numéricos son NOT NULL con default 0 en la BD — pero ese default solo aplica si la columna se omite del INSERT, no si llega null explícito (input vacío). */
    private function withZeroDefaults(array $data): array
    {
        foreach (['trafico_organico', 'keywords_top3', 'keywords_top10', 'keywords_top100', 'backlinks_total', 'errores_resueltos', 'errores_pendientes'] as $campo) {
            if (array_key_exists($campo, $data) && $data[$campo] === null) {
                $data[$campo] = 0;
            }
        }

        return $data;
    }

    public function destroy(SeoMetricaMensual $metrica): JsonResponse
    {
        $metrica->delete();

        return response()->json(['deleted' => true]);
    }

    private function validated(Request $request, SeoCampana $campana, ?int $ignoreId = null): array
    {
        return $request->validate([
            // seo_metricas_mensuales has a unique (seo_campana_id, mes, anio) index — surface it as a validation error instead of a 500.
            'mes' => ['required', 'integer', 'min:1', 'max:12',
                Rule::unique('seo_metricas_mensuales')->where('seo_campana_id', $campana->id)->where('anio', $request->integer('anio'))->ignore($ignoreId),
            ],
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'trafico_organico' => ['nullable', 'integer', 'min:0'],
            'keywords_top3' => ['nullable', 'integer', 'min:0'],
            'keywords_top10' => ['nullable', 'integer', 'min:0'],
            'keywords_top100' => ['nullable', 'integer', 'min:0'],
            'backlinks_total' => ['nullable', 'integer', 'min:0'],
            'errores_resueltos' => ['nullable', 'integer', 'min:0'],
            'errores_pendientes' => ['nullable', 'integer', 'min:0'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
