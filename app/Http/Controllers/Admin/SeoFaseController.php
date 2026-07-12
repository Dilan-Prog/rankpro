<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoCampana;
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
     * Autosave endpoint for the current phase's panel. Every phase row is
     * cycle-scoped (seo_campanas.ciclo_actual), and the *Actual relations
     * on SeoCampana (ofMany 'ciclo','max') already resolve to the row for
     * the current cycle, so this never needs to filter by ciclo manually.
     */
    public function guardar(Request $request, SeoCampana $campana): JsonResponse
    {
        $fase = $campana->fase_actual;

        if ($fase === FaseSeo::Cerrada) {
            return response()->json(['message' => 'Esta campaña está cerrada.'], 422);
        }

        $data = match ($fase) {
            FaseSeo::Auditoria => $request->validate([
                'seo_score' => ['nullable', 'integer', 'min:0', 'max:100'],
                'velocidad_mobile' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'velocidad_desktop' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'lcp_mobile' => ['nullable', 'numeric', 'min:0'],
                'fid_mobile' => ['nullable', 'numeric', 'min:0'],
                'cls_mobile' => ['nullable', 'numeric', 'min:0'],
                'lcp_desktop' => ['nullable', 'numeric', 'min:0'],
                'fid_desktop' => ['nullable', 'numeric', 'min:0'],
                'cls_desktop' => ['nullable', 'numeric', 'min:0'],
                'errores_tecnicos' => ['nullable', 'integer', 'min:0'],
                'indexacion_ok' => ['nullable', 'boolean'],
                'sitemap_ok' => ['nullable', 'boolean'],
                'robots_ok' => ['nullable', 'boolean'],
                'errores_404' => ['nullable', 'integer', 'min:0'],
                'redirecciones_incorrectas' => ['nullable', 'integer', 'min:0'],
                'duplicidad_contenido' => ['nullable', 'boolean'],
                'canonical_ok' => ['nullable', 'boolean'],
                'schema_ok' => ['nullable', 'boolean'],
                'herramienta' => ['nullable', 'in:semrush,ahrefs,screaming_frog,google_search_console,otro'],
                'notas' => ['nullable', 'string', 'max:2000'],
                'checklist' => ['nullable', 'array'],
                'checklist.*' => ['boolean'],
            ]),
            FaseSeo::Estrategia => $request->validate([
                'keywords_ids' => ['nullable', 'array'],
                'keywords_ids.*' => ['integer'],
                'analisis_competencia' => ['nullable', 'string', 'max:5000'],
                'plan_contenido' => ['nullable', 'string', 'max:5000'],
                'estrategia_link_building' => ['nullable', 'string', 'max:5000'],
                'meta_trafico_mensual' => ['nullable', 'integer', 'min:0'],
                'meta_top3' => ['nullable', 'integer', 'min:0'],
                'meta_top10' => ['nullable', 'integer', 'min:0'],
                'meta_leads_mensual' => ['nullable', 'integer', 'min:0'],
                'herramientas' => ['nullable', 'string', 'max:255'],
                'cronograma' => ['nullable', 'string', 'max:5000'],
                'notas' => ['nullable', 'string', 'max:2000'],
                'checklist' => ['nullable', 'array'],
                'checklist.*' => ['boolean'],
            ]),
            FaseSeo::Ejecucion => $request->validate([
                'porcentaje_avance' => ['nullable', 'integer', 'min:0', 'max:100'],
                'paginas_optimizadas' => ['nullable', 'integer', 'min:0'],
                'titles_meta_ok' => ['nullable', 'boolean'],
                'headings_ok' => ['nullable', 'boolean'],
                'imagenes_ok' => ['nullable', 'boolean'],
                'links_internos_ok' => ['nullable', 'boolean'],
                'backlinks_mes' => ['nullable', 'integer', 'min:0'],
                'errores_404_ok' => ['nullable', 'boolean'],
                'redirecciones_ok' => ['nullable', 'boolean'],
                'schema_ok' => ['nullable', 'boolean'],
                'velocidad_ok' => ['nullable', 'boolean'],
                'articulos_publicados' => ['nullable', 'integer', 'min:0'],
                'checklist' => ['nullable', 'array'],
                'checklist.*' => ['boolean'],
            ]),
            FaseSeo::Reporte => $request->validate([
                'trafico_inicio' => ['nullable', 'integer', 'min:0'],
                'trafico_actual' => ['nullable', 'integer', 'min:0'],
                'keywords_top3' => ['nullable', 'integer', 'min:0'],
                'keywords_top10' => ['nullable', 'integer', 'min:0'],
                'keywords_top100' => ['nullable', 'integer', 'min:0'],
                'backlinks_total' => ['nullable', 'integer', 'min:0'],
                'articulos_total' => ['nullable', 'integer', 'min:0'],
                'errores_resueltos' => ['nullable', 'integer', 'min:0'],
                'errores_pendientes' => ['nullable', 'integer', 'min:0'],
                'conclusiones' => ['nullable', 'string', 'max:5000'],
                'recomendaciones' => ['nullable', 'string', 'max:5000'],
                'satisfaccion_cliente' => ['nullable', 'integer', 'min:1', 'max:5'],
                'continua_campana' => ['nullable', 'boolean'],
                'notas_cierre' => ['nullable', 'string', 'max:2000'],
                'checklist' => ['nullable', 'array'],
                'checklist.*' => ['boolean'],
            ]),
        };

        $registro = $this->registroFase($campana, $fase);

        if (array_key_exists('checklist', $data)) {
            $keys = $this->checklistKeys($fase);
            $data['checklist'] = collect($keys)
                ->mapWithKeys(fn ($label, $key) => [$key => (bool) ($data['checklist'][$key] ?? $registro->checklist[$key] ?? false)])
                ->all();
        }

        // Boolean fields are only present in $data when checked (HTML checkboxes omit unchecked ones),
        // so any boolean field the phase supports needs an explicit false fallback via $request->boolean().
        foreach ($this->booleanFields($fase) as $field) {
            $data[$field] = $request->boolean($field);
        }

        $registro->update($data);

        return response()->json([
            'checklist' => $registro->fresh()->checklist,
            'completo' => $this->checklistCompleto($registro->fresh(), $fase),
        ]);
    }

    public function aprobar(SeoCampana $campana): RedirectResponse
    {
        $fase = $campana->fase_actual;

        if ($fase === FaseSeo::Cerrada) {
            return back()->withErrors(['fase' => 'Esta campaña está cerrada.']);
        }

        $registro = $this->registroFase($campana, $fase);

        if (! $this->checklistCompleto($registro, $fase)) {
            return back()->withErrors(['checklist' => 'Completa todo el checklist antes de aprobar esta fase.']);
        }

        $registro->update(['aprobado' => true, 'fecha_aprobacion' => now()]);

        $siguiente = $fase->siguiente();

        if ($siguiente === null) {
            // Reporte approved: stays in "reporte" until the user picks Nuevo Ciclo / Cerrar / Pausar.
            return redirect()->route('admin.seo.show', $campana)->with('status', 'Reporte aprobado. Elige cómo continuar la campaña.');
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
            return back()->withErrors(['fase' => 'La campaña ya está en la primera fase o está cerrada.']);
        }

        $this->registroFase($campana, $anterior)->update(['aprobado' => false, 'fecha_aprobacion' => null]);

        $campana->fase_actual = $anterior;
        $campana->save();

        return redirect()->route('admin.seo.show', $campana)->with('status', 'La campaña retrocedió a la fase anterior.');
    }

    /** Archives the current cycle and starts a fresh one from Fase 1 — full history stays queryable via seoCampana->auditorias()/estrategias()/ejecuciones()/reportes(). */
    public function nuevoCiclo(SeoCampana $campana): RedirectResponse
    {
        if (! $this->reporteListoParaCerrarCiclo($campana)) {
            return back()->withErrors(['fase' => 'Aprueba el reporte del ciclo actual antes de iniciar uno nuevo.']);
        }

        $nuevo = $campana->ciclo_actual + 1;

        $campana->auditorias()->create(['ciclo' => $nuevo, 'checklist' => []]);
        $campana->estrategias()->create(['ciclo' => $nuevo, 'checklist' => []]);
        $campana->ejecuciones()->create(['ciclo' => $nuevo, 'checklist' => []]);
        $campana->reportes()->create(['ciclo' => $nuevo, 'checklist' => []]);

        $campana->fase_actual = FaseSeo::Auditoria;
        $campana->ciclo_actual = $nuevo;
        $campana->save();

        return redirect()->route('admin.seo.show', $campana)->with('status', "Ciclo {$nuevo} iniciado. La campaña volvió a fase de Auditoría.");
    }

    public function cerrar(SeoCampana $campana): RedirectResponse
    {
        if (! $this->reporteListoParaCerrarCiclo($campana)) {
            return back()->withErrors(['fase' => 'Aprueba el reporte del ciclo actual antes de cerrar la campaña.']);
        }

        $campana->fase_actual = FaseSeo::Cerrada;
        $campana->estado = EstadoCampana::Finalizada;
        $campana->save();

        return redirect()->route('admin.seo.show', $campana)->with('status', 'Campaña cerrada.');
    }

    public function pausar(SeoCampana $campana): RedirectResponse
    {
        if (! $this->reporteListoParaCerrarCiclo($campana)) {
            return back()->withErrors(['fase' => 'Aprueba el reporte del ciclo actual antes de pausar la campaña.']);
        }

        $campana->estado = EstadoCampana::Pausada;
        $campana->save();

        return redirect()->route('admin.seo.show', $campana)->with('status', 'Campaña pausada.');
    }

    private function reporteListoParaCerrarCiclo(SeoCampana $campana): bool
    {
        return $campana->fase_actual === FaseSeo::Reporte && (bool) $campana->reporteActual?->aprobado;
    }

    private function registroFase(SeoCampana $campana, FaseSeo $fase)
    {
        return match ($fase) {
            FaseSeo::Auditoria => $campana->faseAuditoria ?? $campana->auditorias()->create(['ciclo' => $campana->ciclo_actual, 'checklist' => []]),
            FaseSeo::Estrategia => $campana->faseEstrategia ?? $campana->estrategias()->create(['ciclo' => $campana->ciclo_actual, 'checklist' => []]),
            FaseSeo::Ejecucion => $campana->faseEjecucion ?? $campana->ejecuciones()->create(['ciclo' => $campana->ciclo_actual, 'checklist' => []]),
            FaseSeo::Reporte => $campana->reporteActual ?? $campana->reportes()->create(['ciclo' => $campana->ciclo_actual, 'checklist' => []]),
            FaseSeo::Cerrada => throw new \InvalidArgumentException('La campaña está cerrada.'),
        };
    }

    private function checklistKeys(FaseSeo $fase): array
    {
        return match ($fase) {
            FaseSeo::Auditoria => SeoFaseAuditoria::CHECKLIST,
            FaseSeo::Estrategia => SeoFaseEstrategia::CHECKLIST,
            FaseSeo::Ejecucion => SeoFaseEjecucion::CHECKLIST,
            FaseSeo::Reporte => SeoReporte::CHECKLIST,
            FaseSeo::Cerrada => [],
        };
    }

    private function booleanFields(FaseSeo $fase): array
    {
        return match ($fase) {
            FaseSeo::Auditoria => ['indexacion_ok', 'sitemap_ok', 'robots_ok', 'duplicidad_contenido', 'canonical_ok', 'schema_ok'],
            FaseSeo::Ejecucion => ['titles_meta_ok', 'headings_ok', 'imagenes_ok', 'links_internos_ok', 'errores_404_ok', 'redirecciones_ok', 'schema_ok', 'velocidad_ok'],
            FaseSeo::Reporte => ['continua_campana'],
            default => [],
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
