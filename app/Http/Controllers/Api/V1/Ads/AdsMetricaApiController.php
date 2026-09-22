<?php

namespace App\Http\Controllers\Api\V1\Ads;

use App\Models\AdsCampana;
use App\Models\AdsMetrica;
use App\Support\Api\{Respuesta, Serializador};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdsMetricaApiController
{
    public function index(AdsCampana $campana)
    {
        return Respuesta::coleccion(Serializador::coleccion($campana->metricas()->get()));
    }

    public function store(Request $request, AdsCampana $campana)
    {
        $data = $this->withZeroDefaults($this->validated($request, $campana));
        $data['cliente_id'] = $campana->cliente_id;

        $metrica = $campana->metricas()->create($data);

        return Respuesta::recurso(Serializador::modelo($metrica), 201);
    }

    public function update(Request $request, AdsMetrica $metrica)
    {
        $data = $this->withZeroDefaults($this->validated($request, $metrica->adsCampana, $metrica->id));

        $metrica->update($data);

        return Respuesta::recurso(Serializador::modelo($metrica->fresh()));
    }

    public function destroy(AdsMetrica $metrica)
    {
        $metrica->delete();

        return Respuesta::eliminado();
    }

    /**
     * Carga en batch para n8n: POST /ads/metricas/lote, sin {campana} en la
     * URL — cada fila trae su propio ads_campana_id (así una sola llamada
     * puede cargar métricas de varias campañas). Cada fila sigue las mismas
     * reglas que store() (incluida la unicidad ads_campana_id+mes+anio).
     * Todo o nada: si una fila falla, no se crea ninguna.
     */
    public function lote(Request $request)
    {
        // OJO: ->validate() con reglas de wildcard ('metricas.*.campo') solo
        // devuelve las claves que tienen regla propia (aquí, solo
        // ads_campana_id) — por eso se valida así, pero se itera sobre
        // $request->input('metricas') (el array completo, sin filtrar) y no
        // sobre el resultado de validate().
        $request->validate([
            'metricas' => ['required', 'array', 'min:1'],
            'metricas.*.ads_campana_id' => ['required', 'integer', 'exists:ads_campanas,id'],
        ]);
        $filas = $request->input('metricas');

        $creadas = DB::transaction(function () use ($filas) {
            $creadas = [];
            $campanas = [];

            foreach ($filas as $fila) {
                $campanaId = (int) $fila['ads_campana_id'];
                $campana = $campanas[$campanaId] ??= AdsCampana::findOrFail($campanaId);

                $data = Validator::make((array) $fila, $this->reglas($campana, (int) ($fila['anio'] ?? 0)))
                    ->validate();
                $data = $this->withZeroDefaults($data);
                $data['cliente_id'] = $campana->cliente_id;

                $creadas[] = $campana->metricas()->create($data);
            }

            return $creadas;
        });

        return Respuesta::recurso(Serializador::coleccion($creadas), 201);
    }

    /** inversion_real/impresiones/clics/conversiones son NOT NULL con default 0 — ese default solo aplica si la columna se omite del INSERT, no si llega null explícito. */
    private function withZeroDefaults(array $data): array
    {
        foreach (['inversion_real', 'impresiones', 'clics', 'conversiones'] as $campo) {
            if (array_key_exists($campo, $data) && $data[$campo] === null) {
                $data[$campo] = 0;
            }
        }

        return $data;
    }

    private function validated(Request $request, AdsCampana $campana, ?int $ignoreId = null): array
    {
        return $request->validate($this->reglas($campana, $request->integer('anio'), $ignoreId));
    }

    private function reglas(AdsCampana $campana, int $anio, ?int $ignoreId = null): array
    {
        return [
            'mes' => ['required', 'integer', 'min:1', 'max:12',
                Rule::unique('ads_metricas')->where('ads_campana_id', $campana->id)->where('anio', $anio)->ignore($ignoreId),
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
        ];
    }
}
