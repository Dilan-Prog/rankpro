<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FaseAds;
use App\Exceptions\ErrorDeFase;
use App\Http\Controllers\Controller;
use App\Models\AdsCampana;
use App\Services\Fases\MaquinaAds;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdsFaseController extends Controller
{
    public function __construct(private MaquinaAds $maquina)
    {
    }

    /**
     * Autosave endpoint for the current phase's panel. Every phase row is
     * cycle-scoped; the singular relations on AdsCampana (ofMany 'ciclo',
     * 'max') already resolve to the current cycle's row.
     */
    public function guardar(Request $request, AdsCampana $campana): JsonResponse
    {
        $fase = $campana->fase_actual;

        if ($fase === FaseAds::Cerrada) {
            return response()->json(['message' => 'Esta campaña está cerrada.'], 422);
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

        // HTML checkboxes omit unchecked fields, so booleans need an explicit false fallback.
        foreach ($this->booleanFields($fase) as $field) {
            $data[$field] = $request->boolean($field);
        }

        $registro->update($data);

        return response()->json([
            'checklist' => $registro->fresh()->checklist,
            'completo' => $this->maquina->checklistCompleto($registro->fresh(), $fase),
        ]);
    }

    public function aprobar(AdsCampana $campana): RedirectResponse
    {
        try {
            $r = $this->maquina->aprobar($campana);
        } catch (ErrorDeFase $e) {
            return back()->withErrors([$e->campo => $e->getMessage()]);
        }

        return redirect()->route('admin.ads.show', $campana)->with('status', $r->mensaje);
    }

    public function retroceder(AdsCampana $campana): RedirectResponse
    {
        try {
            $r = $this->maquina->retroceder($campana);
        } catch (ErrorDeFase $e) {
            return back()->withErrors([$e->campo => $e->getMessage()]);
        }

        return redirect()->route('admin.ads.show', $campana)->with('status', $r->mensaje);
    }

    public function nuevoCiclo(AdsCampana $campana): RedirectResponse
    {
        try {
            $r = $this->maquina->nuevoCiclo($campana);
        } catch (ErrorDeFase $e) {
            return back()->withErrors([$e->campo => $e->getMessage()]);
        }

        return redirect()->route('admin.ads.show', $campana)->with('status', $r->mensaje.' La campaña volvió a fase de Briefing.');
    }

    public function cerrar(AdsCampana $campana): RedirectResponse
    {
        try {
            $this->maquina->cerrar($campana);
        } catch (ErrorDeFase $e) {
            return back()->withErrors([$e->campo => $e->getMessage()]);
        }

        return redirect()->route('admin.ads.show', $campana)->with('status', 'Campaña cerrada.');
    }

    public function pausar(AdsCampana $campana): RedirectResponse
    {
        try {
            $this->maquina->pausar($campana);
        } catch (ErrorDeFase $e) {
            return back()->withErrors([$e->campo => $e->getMessage()]);
        }

        return redirect()->route('admin.ads.show', $campana)->with('status', 'Campaña pausada.');
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
