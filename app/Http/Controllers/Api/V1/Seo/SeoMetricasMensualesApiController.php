<?php

namespace App\Http\Controllers\Api\V1\Seo;

use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\SeoCampana;
use App\Models\SeoMetricaMensual;
use App\Support\Api\{ConsultaOpciones, Respuesta};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SeoMetricasMensualesApiController extends ControladorApi
{
    public function index(Request $request, SeoCampana $campana)
    {
        return $this->listar($campana->metricasMensuales(), $request, new ConsultaOpciones(
            filtrosExactos: ['anio', 'mes', 'ciclo'],
            ordenables: ['id', 'anio', 'mes', 'created_at', 'updated_at'],
            ordenPorDefecto: 'anio',
        ));
    }

    public function store(Request $request, SeoCampana $campana)
    {
        $data = $this->conCerosPorDefecto($this->validado($request, $campana));
        $data['ciclo'] = $campana->ciclo_actual;

        $metrica = $campana->metricasMensuales()->create($data);

        return Respuesta::recurso($metrica, 201);
    }

    public function update(Request $request, SeoMetricaMensual $metrica)
    {
        $data = $this->conCerosPorDefecto($this->validado($request, $metrica->seoCampana, $metrica->id));

        $metrica->update($data);

        return Respuesta::recurso($metrica->fresh());
    }

    public function destroy(SeoMetricaMensual $metrica)
    {
        $metrica->delete();

        return Respuesta::eliminado();
    }

    /** Los campos numéricos son NOT NULL con default 0 en la BD — ver SeoMetricaMensualController (web). */
    private function conCerosPorDefecto(array $data): array
    {
        foreach (['trafico_organico', 'keywords_top3', 'keywords_top10', 'keywords_top100', 'backlinks_total', 'errores_resueltos', 'errores_pendientes'] as $campo) {
            if (array_key_exists($campo, $data) && $data[$campo] === null) {
                $data[$campo] = 0;
            }
        }

        return $data;
    }

    private function validado(Request $request, SeoCampana $campana, ?int $ignorarId = null): array
    {
        return $request->validate([
            'mes' => ['required', 'integer', 'min:1', 'max:12',
                Rule::unique('seo_metricas_mensuales')->where('seo_campana_id', $campana->id)->where('anio', $request->integer('anio'))->ignore($ignorarId),
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
