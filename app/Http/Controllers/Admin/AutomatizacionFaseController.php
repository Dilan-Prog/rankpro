<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoCampana;
use App\Enums\FaseAutomatizacion;
use App\Http\Controllers\Controller;
use App\Models\AutomatizacionFaseDiagnostico;
use App\Models\AutomatizacionFaseDiseno;
use App\Models\AutomatizacionFaseImplementacion;
use App\Models\AutomatizacionProyecto;
use App\Models\AutomatizacionReporte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AutomatizacionFaseController extends Controller
{
    /**
     * Autosave endpoint for the current phase's panel. Every phase row is
     * cycle-scoped (automatizacion_proyectos.ciclo_actual), y las relaciones
     * *Actual en AutomatizacionProyecto (ofMany 'ciclo','max') ya resuelven
     * a la fila del ciclo vigente, así que nunca hace falta filtrar por
     * ciclo manualmente.
     */
    public function guardar(Request $request, AutomatizacionProyecto $proyecto): JsonResponse
    {
        $fase = $proyecto->fase_actual;

        if ($fase === FaseAutomatizacion::Cerrada) {
            return response()->json(['message' => 'Este proyecto está cerrado.'], 422);
        }

        $data = match ($fase) {
            FaseAutomatizacion::Diagnostico => $request->validate([
                'objetivo_cliente' => ['nullable', 'string', 'max:5000'],
                'procesos_actuales' => ['nullable', 'string', 'max:5000'],
                'herramientas_actuales' => ['nullable', 'string', 'max:255'],
                'volumen_mensual_estimado' => ['nullable', 'integer', 'min:0'],
                'viable' => ['nullable', 'boolean'],
                'notas' => ['nullable', 'string', 'max:2000'],
                'checklist' => ['nullable', 'array'],
                'checklist.*' => ['boolean'],
            ]),
            FaseAutomatizacion::DisenoFlujo => $request->validate([
                'flujos_planeados' => ['nullable', 'string', 'max:5000'],
                'integraciones_planeadas' => ['nullable', 'string', 'max:255'],
                'diagrama_url' => ['nullable', 'string', 'max:255'],
                'cronograma' => ['nullable', 'string', 'max:5000'],
                'notas' => ['nullable', 'string', 'max:2000'],
                'checklist' => ['nullable', 'array'],
                'checklist.*' => ['boolean'],
            ]),
            FaseAutomatizacion::Implementacion => $request->validate([
                'porcentaje_avance' => ['nullable', 'integer', 'min:0', 'max:100'],
                'flujos_construidos' => ['nullable', 'integer', 'min:0'],
                'pruebas_realizadas' => ['nullable', 'boolean'],
                'cliente_capacitado' => ['nullable', 'boolean'],
                'notas' => ['nullable', 'string', 'max:2000'],
                'checklist' => ['nullable', 'array'],
                'checklist.*' => ['boolean'],
            ]),
            FaseAutomatizacion::Reporte => $request->validate([
                'flujos_activos_total' => ['nullable', 'integer', 'min:0'],
                'horas_ahorradas_mes' => ['nullable', 'numeric', 'min:0'],
                'mensajes_gestionados_mes' => ['nullable', 'integer', 'min:0'],
                'tareas_automatizadas_mes' => ['nullable', 'integer', 'min:0'],
                'incidencias' => ['nullable', 'string', 'max:5000'],
                'conclusiones' => ['nullable', 'string', 'max:5000'],
                'recomendaciones' => ['nullable', 'string', 'max:5000'],
                'satisfaccion_cliente' => ['nullable', 'integer', 'min:1', 'max:5'],
                'continua_proyecto' => ['nullable', 'boolean'],
                'notas_cierre' => ['nullable', 'string', 'max:2000'],
                'checklist' => ['nullable', 'array'],
                'checklist.*' => ['boolean'],
            ]),
        };

        $registro = $this->registroFase($proyecto, $fase);

        if (array_key_exists('checklist', $data)) {
            $keys = $this->checklistKeys($fase);
            $data['checklist'] = collect($keys)
                ->mapWithKeys(fn ($label, $key) => [$key => (bool) ($data['checklist'][$key] ?? $registro->checklist[$key] ?? false)])
                ->all();
        }

        // Los campos booleanos solo llegan en $data cuando el checkbox está marcado (HTML omite los desmarcados), así que necesitan un fallback explícito a false vía $request->boolean().
        foreach ($this->booleanFields($fase) as $field) {
            $data[$field] = $request->boolean($field);
        }

        $registro->update($data);

        return response()->json([
            'checklist' => $registro->fresh()->checklist,
            'completo' => $this->checklistCompleto($registro->fresh(), $fase),
        ]);
    }

    public function aprobar(AutomatizacionProyecto $proyecto): RedirectResponse
    {
        $fase = $proyecto->fase_actual;

        if ($fase === FaseAutomatizacion::Cerrada) {
            return back()->withErrors(['fase' => 'Este proyecto está cerrado.']);
        }

        $registro = $this->registroFase($proyecto, $fase);

        if (! $this->checklistCompleto($registro, $fase)) {
            return back()->withErrors(['checklist' => 'Completa todo el checklist antes de aprobar esta fase.']);
        }

        $registro->update(['aprobado' => true, 'fecha_aprobacion' => now()]);

        $siguiente = $fase->siguiente();

        if ($siguiente === null) {
            // Reporte aprobado: se queda en "reporte" hasta que se elija Nuevo Ciclo / Cerrar / Pausar.
            return redirect()->route('admin.automatizaciones.show', $proyecto)->with('status', 'Reporte aprobado. Elige cómo continuar el proyecto.');
        }

        $proyecto->fase_actual = $siguiente;
        $proyecto->save();

        return redirect()->route('admin.automatizaciones.show', $proyecto)->with('status', 'Fase aprobada. El proyecto avanzó a la siguiente etapa.');
    }

    public function retroceder(AutomatizacionProyecto $proyecto): RedirectResponse
    {
        $fase = $proyecto->fase_actual;
        $anterior = $fase->anterior();

        if ($anterior === null) {
            return back()->withErrors(['fase' => 'El proyecto ya está en la primera fase o está cerrado.']);
        }

        $this->registroFase($proyecto, $anterior)->update(['aprobado' => false, 'fecha_aprobacion' => null]);

        $proyecto->fase_actual = $anterior;
        $proyecto->save();

        return redirect()->route('admin.automatizaciones.show', $proyecto)->with('status', 'El proyecto retrocedió a la fase anterior.');
    }

    /** Archiva el ciclo actual y arranca uno nuevo desde Fase 1 — el historial queda consultable vía proyecto->diagnosticos()/disenos()/implementaciones()/reportes(). */
    public function nuevoCiclo(AutomatizacionProyecto $proyecto): RedirectResponse
    {
        if (! $this->reporteListoParaCerrarCiclo($proyecto)) {
            return back()->withErrors(['fase' => 'Aprueba el reporte del ciclo actual antes de iniciar uno nuevo.']);
        }

        $nuevo = $proyecto->ciclo_actual + 1;

        $proyecto->diagnosticos()->create(['ciclo' => $nuevo, 'checklist' => []]);
        $proyecto->disenos()->create(['ciclo' => $nuevo, 'checklist' => []]);
        $proyecto->implementaciones()->create(['ciclo' => $nuevo, 'checklist' => []]);
        $proyecto->reportes()->create(['ciclo' => $nuevo, 'checklist' => []]);

        $proyecto->fase_actual = FaseAutomatizacion::Diagnostico;
        $proyecto->ciclo_actual = $nuevo;
        $proyecto->save();

        return redirect()->route('admin.automatizaciones.show', $proyecto)->with('status', "Ciclo {$nuevo} iniciado. El proyecto volvió a fase de Diagnóstico.");
    }

    public function cerrar(AutomatizacionProyecto $proyecto): RedirectResponse
    {
        if (! $this->reporteListoParaCerrarCiclo($proyecto)) {
            return back()->withErrors(['fase' => 'Aprueba el reporte del ciclo actual antes de cerrar el proyecto.']);
        }

        $proyecto->fase_actual = FaseAutomatizacion::Cerrada;
        $proyecto->estado = EstadoCampana::Finalizada;
        $proyecto->save();

        return redirect()->route('admin.automatizaciones.show', $proyecto)->with('status', 'Proyecto cerrado.');
    }

    public function pausar(AutomatizacionProyecto $proyecto): RedirectResponse
    {
        if (! $this->reporteListoParaCerrarCiclo($proyecto)) {
            return back()->withErrors(['fase' => 'Aprueba el reporte del ciclo actual antes de pausar el proyecto.']);
        }

        $proyecto->estado = EstadoCampana::Pausada;
        $proyecto->save();

        return redirect()->route('admin.automatizaciones.show', $proyecto)->with('status', 'Proyecto pausado.');
    }

    private function reporteListoParaCerrarCiclo(AutomatizacionProyecto $proyecto): bool
    {
        return $proyecto->fase_actual === FaseAutomatizacion::Reporte && (bool) $proyecto->reporteActual?->aprobado;
    }

    private function registroFase(AutomatizacionProyecto $proyecto, FaseAutomatizacion $fase)
    {
        return match ($fase) {
            FaseAutomatizacion::Diagnostico => $proyecto->faseDiagnostico ?? $proyecto->diagnosticos()->create(['ciclo' => $proyecto->ciclo_actual, 'checklist' => []]),
            FaseAutomatizacion::DisenoFlujo => $proyecto->faseDiseno ?? $proyecto->disenos()->create(['ciclo' => $proyecto->ciclo_actual, 'checklist' => []]),
            FaseAutomatizacion::Implementacion => $proyecto->faseImplementacion ?? $proyecto->implementaciones()->create(['ciclo' => $proyecto->ciclo_actual, 'checklist' => []]),
            FaseAutomatizacion::Reporte => $proyecto->reporteActual ?? $proyecto->reportes()->create(['ciclo' => $proyecto->ciclo_actual, 'checklist' => []]),
            FaseAutomatizacion::Cerrada => throw new \InvalidArgumentException('El proyecto está cerrado.'),
        };
    }

    private function checklistKeys(FaseAutomatizacion $fase): array
    {
        return match ($fase) {
            FaseAutomatizacion::Diagnostico => AutomatizacionFaseDiagnostico::CHECKLIST,
            FaseAutomatizacion::DisenoFlujo => AutomatizacionFaseDiseno::CHECKLIST,
            FaseAutomatizacion::Implementacion => AutomatizacionFaseImplementacion::CHECKLIST,
            FaseAutomatizacion::Reporte => AutomatizacionReporte::CHECKLIST,
            FaseAutomatizacion::Cerrada => [],
        };
    }

    private function booleanFields(FaseAutomatizacion $fase): array
    {
        return match ($fase) {
            FaseAutomatizacion::Diagnostico => ['viable'],
            FaseAutomatizacion::Implementacion => ['pruebas_realizadas', 'cliente_capacitado'],
            FaseAutomatizacion::Reporte => ['continua_proyecto'],
            default => [],
        };
    }

    private function checklistCompleto($registro, FaseAutomatizacion $fase): bool
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
