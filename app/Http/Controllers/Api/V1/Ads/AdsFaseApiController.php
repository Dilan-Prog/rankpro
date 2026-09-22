<?php

namespace App\Http\Controllers\Api\V1\Ads;

use App\Enums\FaseAds;
use App\Models\AdsCampana;
use App\Services\Fases\MaquinaAds;
use App\Support\Api\Respuesta;
use Illuminate\Http\Request;

/**
 * Endpoints de fase de AdsCampana para la API. aprobar/retroceder/nuevo-ciclo
 * /cerrar/pausar delegan en MaquinaAds (ya refactorizada); guardar() replica
 * el match por fase de App\Http\Controllers\Admin\AdsFaseController::guardar()
 * (esa parte no tiene servicio compartido — ver api_contrato.md).
 */
class AdsFaseApiController
{
    public function __construct(private MaquinaAds $maquina)
    {
    }

    public function fase(AdsCampana $campana)
    {
        return Respuesta::recurso($this->maquina->estado($campana));
    }

    public function guardar(Request $request, AdsCampana $campana)
    {
        $fase = $campana->fase_actual;

        if ($fase === FaseAds::Cerrada) {
            return Respuesta::mensaje('Esta campaña está cerrada.', 422);
        }

        $data = match ($fase) {
            FaseAds::Briefing => $request->validate([
                'publico_objetivo' => ['nullable', 'string', 'max:5000'],
                'rango_edad' => ['nullable', 'string', 'max:100'],
                'genero' => ['nullable', 'string', 'max:100'],
                'ubicacion_geografica' => ['nullable', 'string', 'max:255'],
                'intereses' => ['nullable', 'string', 'max:5000'],
                'propuesta_valor' => ['nullable', 'string', 'max:5000'],
                'analisis_competencia' => ['nullable', 'string', 'max:5000'],
                'producto_servicio' => ['nullable', 'string', 'max:255'],
                'url_destino' => ['nullable', 'string', 'max:255'],
                'fecha_inicio_estimada' => ['nullable', 'date'],
                'notas' => ['nullable', 'string', 'max:2000'],
                'checklist' => ['nullable', 'array'],
                'checklist.*' => ['boolean'],
            ]),
            FaseAds::Configuracion => $request->validate([
                'estructura_campana' => ['nullable', 'string', 'max:5000'],
                'pixel_ok' => ['nullable', 'boolean'],
                'cuenta_publicitaria' => ['nullable', 'string', 'max:255'],
                'utms_ok' => ['nullable', 'boolean'],
                'notas' => ['nullable', 'string', 'max:2000'],
                'checklist' => ['nullable', 'array'],
                'checklist.*' => ['boolean'],
            ]),
            FaseAds::Lanzamiento => $request->validate([
                'fecha_lanzamiento' => ['nullable', 'date'],
                'porcentaje_avance' => ['nullable', 'integer', 'min:0', 'max:100'],
                'checklist' => ['nullable', 'array'],
                'checklist.*' => ['boolean'],
            ]),
            FaseAds::Reporte => $request->validate([
                'inversion_total' => ['nullable', 'numeric', 'min:0'],
                'impresiones_total' => ['nullable', 'integer', 'min:0'],
                'clics_total' => ['nullable', 'integer', 'min:0'],
                'ctr_promedio' => ['nullable', 'numeric', 'min:0'],
                'conversiones_total' => ['nullable', 'integer', 'min:0'],
                'roas_promedio' => ['nullable', 'numeric', 'min:0'],
                'cpl_promedio' => ['nullable', 'numeric', 'min:0'],
                'cpa_promedio' => ['nullable', 'numeric', 'min:0'],
                'mejor_anuncio_ctr' => ['nullable', 'string', 'max:255'],
                'mejor_anuncio_conversiones' => ['nullable', 'string', 'max:255'],
                'conclusiones' => ['nullable', 'string', 'max:5000'],
                'recomendaciones' => ['nullable', 'string', 'max:5000'],
                'satisfaccion_cliente' => ['nullable', 'integer', 'min:1', 'max:5'],
                'continua_campana' => ['nullable', 'boolean'],
                'notas_cierre' => ['nullable', 'string', 'max:2000'],
                'checklist' => ['nullable', 'array'],
                'checklist.*' => ['boolean'],
            ]),
        };

        $registro = $this->maquina->registro($campana, $fase);

        if (array_key_exists('checklist', $data)) {
            $data['checklist'] = $this->maquina->fusionarChecklist($registro, $fase, $data['checklist']);
        }

        foreach ($this->booleanFields($fase) as $field) {
            $data[$field] = $request->boolean($field);
        }

        $registro->update($data);

        return Respuesta::recurso([
            'checklist' => $registro->fresh()->checklist,
            'completo' => $this->maquina->checklistCompleto($registro->fresh(), $fase),
        ]);
    }

    public function aprobar(AdsCampana $campana)
    {
        return Respuesta::recurso($this->maquina->aprobar($campana)->toArray());
    }

    public function retroceder(AdsCampana $campana)
    {
        return Respuesta::recurso($this->maquina->retroceder($campana)->toArray());
    }

    public function nuevoCiclo(AdsCampana $campana)
    {
        return Respuesta::recurso($this->maquina->nuevoCiclo($campana)->toArray());
    }

    public function cerrar(AdsCampana $campana)
    {
        return Respuesta::recurso($this->maquina->cerrar($campana)->toArray());
    }

    public function pausar(AdsCampana $campana)
    {
        return Respuesta::recurso($this->maquina->pausar($campana)->toArray());
    }

    private function booleanFields(FaseAds $fase): array
    {
        return match ($fase) {
            FaseAds::Configuracion => ['pixel_ok', 'utms_ok'],
            FaseAds::Reporte => ['continua_campana'],
            default => [],
        };
    }
}
