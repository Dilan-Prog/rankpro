<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoCampana;
use App\Enums\FaseAds;
use App\Http\Controllers\Controller;
use App\Models\AdsBriefing;
use App\Models\AdsCampana;
use App\Models\AdsConfiguracion;
use App\Models\AdsLanzamiento;
use App\Models\AdsReporte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdsFaseController extends Controller
{
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

        $registro = $this->registroFase($campana, $fase);

        if (array_key_exists('checklist', $data)) {
            $keys = $this->checklistKeys($fase);
            $data['checklist'] = collect($keys)
                ->mapWithKeys(fn ($label, $key) => [$key => (bool) ($data['checklist'][$key] ?? $registro->checklist[$key] ?? false)])
                ->all();
        }

        // HTML checkboxes omit unchecked fields, so booleans need an explicit false fallback.
        foreach ($this->booleanFields($fase) as $field) {
            $data[$field] = $request->boolean($field);
        }

        $registro->update($data);

        return response()->json([
            'checklist' => $registro->fresh()->checklist,
            'completo' => $this->checklistCompleto($registro->fresh(), $fase),
        ]);
    }

    public function aprobar(AdsCampana $campana): RedirectResponse
    {
        $fase = $campana->fase_actual;

        if ($fase === FaseAds::Cerrada) {
            return back()->withErrors(['fase' => 'Esta campaña está cerrada.']);
        }

        $registro = $this->registroFase($campana, $fase);

        if (! $this->checklistCompleto($registro, $fase)) {
            return back()->withErrors(['checklist' => 'Completa todo el checklist antes de aprobar esta fase.']);
        }

        $registro->update(['aprobado' => true, 'fecha_aprobacion' => now()]);

        $siguiente = $fase->siguiente();

        if ($siguiente === null) {
            return redirect()->route('admin.ads.show', $campana)->with('status', 'Reporte aprobado. Elige cómo continuar la campaña.');
        }

        $campana->fase_actual = $siguiente;
        $campana->save();

        return redirect()->route('admin.ads.show', $campana)->with('status', 'Fase aprobada. La campaña avanzó a la siguiente etapa.');
    }

    public function retroceder(AdsCampana $campana): RedirectResponse
    {
        $anterior = $campana->fase_actual->anterior();

        if ($anterior === null) {
            return back()->withErrors(['fase' => 'La campaña ya está en la primera fase o está cerrada.']);
        }

        $this->registroFase($campana, $anterior)->update(['aprobado' => false, 'fecha_aprobacion' => null]);

        $campana->fase_actual = $anterior;
        $campana->save();

        return redirect()->route('admin.ads.show', $campana)->with('status', 'La campaña retrocedió a la fase anterior.');
    }

    /** Archives the current cycle and restarts from Briefing — history stays via briefings()/configuraciones()/lanzamientos()/reportes(). */
    public function nuevoCiclo(AdsCampana $campana): RedirectResponse
    {
        if (! $this->reporteListoParaCerrarCiclo($campana)) {
            return back()->withErrors(['fase' => 'Aprueba el reporte del ciclo actual antes de iniciar uno nuevo.']);
        }

        $nuevo = $campana->ciclo_actual + 1;

        $campana->briefings()->create(['ciclo' => $nuevo, 'checklist' => []]);
        $campana->configuraciones()->create(['ciclo' => $nuevo, 'checklist' => []]);
        $campana->lanzamientos()->create(['ciclo' => $nuevo, 'checklist' => []]);
        $campana->reportes()->create(['ciclo' => $nuevo, 'checklist' => []]);

        $campana->fase_actual = FaseAds::Briefing;
        $campana->ciclo_actual = $nuevo;
        $campana->save();

        return redirect()->route('admin.ads.show', $campana)->with('status', "Ciclo {$nuevo} iniciado. La campaña volvió a fase de Briefing.");
    }

    public function cerrar(AdsCampana $campana): RedirectResponse
    {
        if (! $this->reporteListoParaCerrarCiclo($campana)) {
            return back()->withErrors(['fase' => 'Aprueba el reporte del ciclo actual antes de cerrar la campaña.']);
        }

        $campana->fase_actual = FaseAds::Cerrada;
        $campana->estado = EstadoCampana::Finalizada;
        $campana->save();

        return redirect()->route('admin.ads.show', $campana)->with('status', 'Campaña cerrada.');
    }

    public function pausar(AdsCampana $campana): RedirectResponse
    {
        if (! $this->reporteListoParaCerrarCiclo($campana)) {
            return back()->withErrors(['fase' => 'Aprueba el reporte del ciclo actual antes de pausar la campaña.']);
        }

        $campana->estado = EstadoCampana::Pausada;
        $campana->save();

        return redirect()->route('admin.ads.show', $campana)->with('status', 'Campaña pausada.');
    }

    private function reporteListoParaCerrarCiclo(AdsCampana $campana): bool
    {
        return $campana->fase_actual === FaseAds::Reporte && (bool) $campana->reporteActual?->aprobado;
    }

    private function registroFase(AdsCampana $campana, FaseAds $fase)
    {
        return match ($fase) {
            FaseAds::Briefing => $campana->briefing ?? $campana->briefings()->create(['ciclo' => $campana->ciclo_actual, 'checklist' => []]),
            FaseAds::Configuracion => $campana->configuracion ?? $campana->configuraciones()->create(['ciclo' => $campana->ciclo_actual, 'checklist' => []]),
            FaseAds::Lanzamiento => $campana->lanzamiento ?? $campana->lanzamientos()->create(['ciclo' => $campana->ciclo_actual, 'checklist' => []]),
            FaseAds::Reporte => $campana->reporteActual ?? $campana->reportes()->create(['ciclo' => $campana->ciclo_actual, 'checklist' => []]),
            FaseAds::Cerrada => throw new \InvalidArgumentException('La campaña está cerrada.'),
        };
    }

    private function checklistKeys(FaseAds $fase): array
    {
        return match ($fase) {
            FaseAds::Briefing => AdsBriefing::CHECKLIST,
            FaseAds::Configuracion => AdsConfiguracion::CHECKLIST,
            FaseAds::Lanzamiento => AdsLanzamiento::CHECKLIST,
            FaseAds::Reporte => AdsReporte::CHECKLIST,
            FaseAds::Cerrada => [],
        };
    }

    private function booleanFields(FaseAds $fase): array
    {
        return match ($fase) {
            FaseAds::Configuracion => ['pixel_ok', 'utms_ok'],
            FaseAds::Reporte => ['continua_campana'],
            default => [],
        };
    }

    private function checklistCompleto($registro, FaseAds $fase): bool
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
