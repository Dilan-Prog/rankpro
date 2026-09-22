<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FaseAutomatizacion;
use App\Exceptions\ErrorDeFase;
use App\Http\Controllers\Controller;
use App\Models\AutomatizacionProyecto;
use App\Services\Fases\MaquinaAutomatizacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AutomatizacionFaseController extends Controller
{
    public function __construct(private MaquinaAutomatizacion $maquina)
    {
    }

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

        $registro = $this->maquina->registro($proyecto, $fase);

        if (array_key_exists('checklist', $data)) {
            $data['checklist'] = $this->maquina->fusionarChecklist($registro, $fase, $data['checklist']);
        }

        // Los campos booleanos solo llegan en $data cuando el checkbox está marcado (HTML omite los desmarcados), así que necesitan un fallback explícito a false vía $request->boolean().
        foreach ($this->booleanFields($fase) as $field) {
            $data[$field] = $request->boolean($field);
        }

        $registro->update($data);

        return response()->json([
            'checklist' => $registro->fresh()->checklist,
            'completo' => $this->maquina->checklistCompleto($registro->fresh(), $fase),
        ]);
    }

    public function aprobar(AutomatizacionProyecto $proyecto): RedirectResponse
    {
        try {
            $r = $this->maquina->aprobar($proyecto);
        } catch (ErrorDeFase $e) {
            return back()->withErrors([$e->campo => $e->getMessage()]);
        }

        return redirect()->route('admin.automatizaciones.show', $proyecto)->with('status', $r->mensaje);
    }

    public function retroceder(AutomatizacionProyecto $proyecto): RedirectResponse
    {
        try {
            $r = $this->maquina->retroceder($proyecto);
        } catch (ErrorDeFase $e) {
            return back()->withErrors([$e->campo => $e->getMessage()]);
        }

        return redirect()->route('admin.automatizaciones.show', $proyecto)->with('status', $r->mensaje);
    }

    public function nuevoCiclo(AutomatizacionProyecto $proyecto): RedirectResponse
    {
        try {
            $r = $this->maquina->nuevoCiclo($proyecto);
        } catch (ErrorDeFase $e) {
            return back()->withErrors([$e->campo => $e->getMessage()]);
        }

        return redirect()->route('admin.automatizaciones.show', $proyecto)->with('status', $r->mensaje.' El proyecto volvió a fase de Diagnóstico.');
    }

    public function cerrar(AutomatizacionProyecto $proyecto): RedirectResponse
    {
        try {
            $this->maquina->cerrar($proyecto);
        } catch (ErrorDeFase $e) {
            return back()->withErrors([$e->campo => $e->getMessage()]);
        }

        return redirect()->route('admin.automatizaciones.show', $proyecto)->with('status', 'Proyecto cerrado.');
    }

    public function pausar(AutomatizacionProyecto $proyecto): RedirectResponse
    {
        try {
            $this->maquina->pausar($proyecto);
        } catch (ErrorDeFase $e) {
            return back()->withErrors([$e->campo => $e->getMessage()]);
        }

        return redirect()->route('admin.automatizaciones.show', $proyecto)->with('status', 'Proyecto pausado.');
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
}
