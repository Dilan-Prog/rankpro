<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FaseSeo;
use App\Http\Controllers\Controller;
use App\Models\SeoCampana;
use App\Models\SeoFaseAuditoria;
use App\Models\SeoFaseEjecucion;
use App\Models\SeoFaseEstrategia;
use App\Models\SeoReporte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SeoFaseController extends Controller
{
    /**
     * Autosave endpoint for the current phase's panel. Auditoría is the one
     * special case: its data (seo_score, velocidad, sitemap_ok, ...) lives
     * on seo_campanas itself (pre-existing columns), not a dedicated phase
     * table, so this splits the payload between the campaign row and the
     * seo_fase_auditoria checklist row for that phase only.
     */
    public function guardar(Request $request, SeoCampana $campana): JsonResponse
    {
        $fase = $campana->fase_actual;

        $data = match ($fase) {
            FaseSeo::Auditoria => $request->validate([
                'seo_score' => ['nullable', 'integer', 'min:0', 'max:100'],
                'trafico_organico_mensual' => ['nullable', 'integer', 'min:0'],
                'backlinks_total' => ['nullable', 'integer', 'min:0'],
                'errores_tecnicos' => ['nullable', 'integer', 'min:0'],
                'velocidad_mobile' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'velocidad_desktop' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'sitemap_ok' => ['nullable', 'boolean'],
                'robots_ok' => ['nullable', 'boolean'],
                'checklist' => ['nullable', 'array'],
                'checklist.*' => ['boolean'],
            ]),
            FaseSeo::Estrategia => $request->validate([
                'analisis_competencia' => ['nullable', 'string', 'max:5000'],
                'plan_contenido' => ['nullable', 'string', 'max:5000'],
                'link_building_strategy' => ['nullable', 'string', 'max:5000'],
                'meta_trafico_mensual' => ['nullable', 'integer', 'min:0'],
                'meta_posiciones_top10' => ['nullable', 'integer', 'min:0'],
                'meta_leads_mensual' => ['nullable', 'integer', 'min:0'],
                'checklist' => ['nullable', 'array'],
                'checklist.*' => ['boolean'],
            ]),
            FaseSeo::Ejecucion => $request->validate([
                'on_page_completado' => ['nullable', 'boolean'],
                'off_page_completado' => ['nullable', 'boolean'],
                'tecnico_completado' => ['nullable', 'boolean'],
                'contenido_completado' => ['nullable', 'boolean'],
                'checklist' => ['nullable', 'array'],
                'checklist.*' => ['boolean'],
            ]),
            FaseSeo::Reporte => $request->validate([
                'resultados_vs_metas' => ['nullable', 'string', 'max:5000'],
                'trafico_organico_final' => ['nullable', 'integer', 'min:0'],
                'posiciones_ganadas' => ['nullable', 'integer'],
                'roas_organico' => ['nullable', 'numeric', 'min:0'],
                'checklist' => ['nullable', 'array'],
                'checklist.*' => ['boolean'],
            ]),
        };

        $registro = $fase === FaseSeo::Auditoria
            ? $campana->faseAuditoria()->firstOrCreate([], ['checklist' => []])
            : $this->registroFase($campana, $fase);

        if (array_key_exists('checklist', $data)) {
            $keys = $this->checklistKeys($fase);
            $data['checklist'] = collect($keys)
                ->mapWithKeys(fn ($label, $key) => [$key => (bool) ($data['checklist'][$key] ?? $registro->checklist[$key] ?? false)])
                ->all();
        }

        if ($fase === FaseSeo::Auditoria) {
            // This phase's non-checklist data lives on seo_campanas itself (pre-existing columns).
            $campanaData = collect($data)->except(['checklist'])->all();
            if (! empty($campanaData)) {
                $campanaData['sitemap_ok'] = $request->boolean('sitemap_ok');
                $campanaData['robots_ok'] = $request->boolean('robots_ok');
                $campana->update($campanaData);
            }
            if (array_key_exists('checklist', $data)) {
                $registro->update(['checklist' => $data['checklist']]);
            }
        } else {
            $registro->update($data);
        }

        return response()->json([
            'checklist' => $registro->fresh()->checklist,
            'completo' => $this->checklistCompleto($registro->fresh(), $fase),
        ]);
    }

    public function aprobar(SeoCampana $campana): RedirectResponse
    {
        $fase = $campana->fase_actual;
        $registro = $fase === FaseSeo::Auditoria
            ? $campana->faseAuditoria()->firstOrCreate([], ['checklist' => []])
            : $this->registroFase($campana, $fase);

        if (! $this->checklistCompleto($registro, $fase)) {
            return back()->withErrors(['checklist' => 'Completa todo el checklist antes de aprobar esta fase.']);
        }

        $registro->update(['aprobado' => true, 'fecha_aprobacion' => now()]);

        $siguiente = $fase->siguiente();

        if ($siguiente === null) {
            // Reporte approved: the cycle's report is finalized, but the campaign
            // stays in "reporte" until the user explicitly starts the next cycle.
            return redirect()->route('admin.seo.show', $campana)->with('status', 'Reporte aprobado. Puedes iniciar el siguiente ciclo cuando quieras.');
        }

        $campana->fase_actual = $siguiente;
        $campana->save();

        return redirect()->route('admin.seo.show', $campana)->with('status', 'Fase aprobada. La campaña avanzó a la siguiente etapa.');
    }

    public function retroceder(SeoCampana $campana): RedirectResponse
    {
        $fase = $campana->fase_actual;
        $anterior = $fase->anterior();

        if ($anterior === null) {
            return back()->withErrors(['fase' => 'La campaña ya está en la primera fase.']);
        }

        $registroAnterior = $anterior === FaseSeo::Auditoria
            ? $campana->faseAuditoria()->firstOrCreate([], ['checklist' => []])
            : $this->registroFase($campana, $anterior);
        $registroAnterior->update(['aprobado' => false, 'fecha_aprobacion' => null]);

        $campana->fase_actual = $anterior;
        $campana->save();

        return redirect()->route('admin.seo.show', $campana)->with('status', 'La campaña retrocedió a la fase anterior.');
    }

    /** Only allowed once the current cycle's report is approved. Loops back to Ejecución, not Auditoría/Estrategia. */
    public function siguienteCiclo(SeoCampana $campana): RedirectResponse
    {
        if ($campana->fase_actual !== FaseSeo::Reporte || ! $campana->reporteActual?->aprobado) {
            return back()->withErrors(['fase' => 'Aprueba el reporte del ciclo actual antes de iniciar uno nuevo.']);
        }

        $nuevoCiclo = $campana->ciclo_actual + 1;

        $campana->faseEjecucion()->update([
            'on_page_completado' => false,
            'off_page_completado' => false,
            'tecnico_completado' => false,
            'contenido_completado' => false,
            'checklist' => [],
            'aprobado' => false,
            'fecha_aprobacion' => null,
        ]);

        $campana->reportes()->create(['ciclo' => $nuevoCiclo, 'checklist' => []]);

        $campana->fase_actual = FaseSeo::Ejecucion;
        $campana->ciclo_actual = $nuevoCiclo;
        $campana->save();

        return redirect()->route('admin.seo.show', $campana)->with('status', "Ciclo {$nuevoCiclo} iniciado. La campaña volvió a fase de Ejecución.");
    }

    private function registroFase(SeoCampana $campana, FaseSeo $fase)
    {
        return match ($fase) {
            FaseSeo::Auditoria => $campana->faseAuditoria()->firstOrCreate([], ['checklist' => []]),
            FaseSeo::Estrategia => $campana->faseEstrategia()->firstOrCreate([], ['checklist' => []]),
            FaseSeo::Ejecucion => $campana->faseEjecucion()->firstOrCreate([], ['checklist' => []]),
            FaseSeo::Reporte => $campana->reporteActual ?? $campana->reportes()->create(['ciclo' => $campana->ciclo_actual, 'checklist' => []]),
        };
    }

    private function checklistKeys(FaseSeo $fase): array
    {
        return match ($fase) {
            FaseSeo::Auditoria => SeoFaseAuditoria::CHECKLIST,
            FaseSeo::Estrategia => SeoFaseEstrategia::CHECKLIST,
            FaseSeo::Ejecucion => SeoFaseEjecucion::CHECKLIST,
            FaseSeo::Reporte => SeoReporte::CHECKLIST,
        };
    }

    private function checklistCompleto($registro, FaseSeo $fase): bool
    {
        $keys = array_keys($this->checklistKeys($fase));
        $checklist = $registro->checklist ?? [];

        foreach ($keys as $key) {
            if (empty($checklist[$key])) {
                return false;
            }
        }

        return true;
    }
}
