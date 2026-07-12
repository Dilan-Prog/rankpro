<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoProyecto;
use App\Enums\FaseProyecto;
use App\Http\Controllers\Controller;
use App\Models\Proyecto;
use App\Models\ProyectoControl;
use App\Models\ProyectoDireccion;
use App\Models\ProyectoOrganizacion;
use App\Models\ProyectoPlaneacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProyectoFaseController extends Controller
{
    /**
     * Autosave endpoint for the current phase's panel — called from the
     * checklist checkboxes (immediately on change) and the descriptive
     * fields (debounced on input) so nothing is lost before the user
     * clicks "Aprobar fase". Accepts a partial payload; whatever keys are
     * present get merged into the phase record.
     */
    public function guardar(Request $request, Proyecto $proyecto): JsonResponse
    {
        $fase = $proyecto->fase_actual;

        if ($fase === FaseProyecto::Cerrado) {
            return response()->json(['message' => 'Este proyecto ya está cerrado.'], 422);
        }

        $registro = $this->registroFase($proyecto, $fase);

        $data = match ($fase) {
            FaseProyecto::Planeacion => $request->validate([
                'objetivos' => ['nullable', 'string', 'max:5000'],
                'requerimientos_funcionales' => ['nullable', 'string', 'max:5000'],
                'requerimientos_tecnicos' => ['nullable', 'string', 'max:5000'],
                'checklist' => ['nullable', 'array'],
                'checklist.*' => ['boolean'],
            ]),
            FaseProyecto::Organizacion => $request->validate([
                'stack_tecnologico' => ['nullable', 'string', 'max:255'],
                'arquitectura' => ['nullable', 'string', 'max:5000'],
                'herramientas' => ['nullable', 'string', 'max:500'],
                'url_repositorio' => ['nullable', 'string', 'max:255'],
                'url_staging' => ['nullable', 'string', 'max:255'],
                'equipo_nombre' => ['nullable', 'array'],
                'equipo_nombre.*' => ['nullable', 'string', 'max:255'],
                'equipo_rol' => ['nullable', 'array'],
                'equipo_rol.*' => ['nullable', 'string', 'max:255'],
                'checklist' => ['nullable', 'array'],
                'checklist.*' => ['boolean'],
            ]),
            FaseProyecto::Direccion => $request->validate([
                'porcentaje_avance' => ['nullable', 'integer', 'min:0', 'max:100'],
                'pagos_recibidos_fase' => ['nullable', 'numeric', 'min:0'],
                'checklist' => ['nullable', 'array'],
                'checklist.*' => ['boolean'],
            ]),
            FaseProyecto::Control => $request->validate([
                'url_produccion' => ['nullable', 'string', 'max:255'],
                'credenciales_entregadas' => ['nullable', 'boolean'],
                'manual_entregado' => ['nullable', 'boolean'],
                'capacitacion_realizada' => ['nullable', 'boolean'],
                'pago_final_recibido' => ['nullable', 'boolean'],
                'monto_pago_final' => ['nullable', 'numeric', 'min:0'],
                'fecha_entrega_real' => ['nullable', 'date'],
                'satisfaccion_cliente' => ['nullable', 'integer', 'min:1', 'max:5'],
                'notas_cierre' => ['nullable', 'string', 'max:5000'],
                'checklist' => ['nullable', 'array'],
                'checklist.*' => ['boolean'],
            ]),
            default => [],
        };

        if ($fase === FaseProyecto::Organizacion && ($request->has('equipo_nombre') || $request->has('equipo_rol'))) {
            $nombres = $request->input('equipo_nombre', []);
            $roles = $request->input('equipo_rol', []);
            $data['equipo'] = collect($nombres)
                ->map(fn ($nombre, $i) => ['nombre' => $nombre, 'rol' => $roles[$i] ?? null])
                ->filter(fn ($m) => filled($m['nombre']))
                ->values()
                ->all();
            unset($data['equipo_nombre'], $data['equipo_rol']);
        }

        if (array_key_exists('checklist', $data)) {
            $keys = $this->checklistKeys($fase);
            $data['checklist'] = collect($keys)
                ->mapWithKeys(fn ($label, $key) => [$key => (bool) ($data['checklist'][$key] ?? $registro->checklist[$key] ?? false)])
                ->all();
        }

        $registro->update($data);

        return response()->json([
            'checklist' => $registro->fresh()->checklist,
            'completo' => $this->checklistCompleto($registro->fresh(), $fase),
        ]);
    }

    public function aprobar(Proyecto $proyecto): RedirectResponse
    {
        $fase = $proyecto->fase_actual;

        if ($fase === FaseProyecto::Cerrado) {
            return back()->withErrors(['fase' => 'Este proyecto ya está cerrado.']);
        }

        $registro = $this->registroFase($proyecto, $fase);

        if (! $this->checklistCompleto($registro, $fase)) {
            return back()->withErrors(['checklist' => 'Completa todo el checklist antes de aprobar esta fase.']);
        }

        $registro->update(['aprobado' => true, 'fecha_aprobacion' => now()]);

        $siguiente = $fase->siguiente();
        $proyecto->fase_actual = $siguiente;

        if ($siguiente === FaseProyecto::Cerrado) {
            $proyecto->estado = EstadoProyecto::Cerrado;
            $proyecto->porcentaje_avance = 100;
            if ($registro instanceof ProyectoControl && $registro->fecha_entrega_real) {
                $proyecto->fecha_entrega_real = $registro->fecha_entrega_real;
            }
        } else {
            $this->registroFase($proyecto, $siguiente);

            if ($fase === FaseProyecto::Direccion && $registro instanceof ProyectoDireccion) {
                $proyecto->porcentaje_avance = $registro->porcentaje_avance;
            }
        }

        $proyecto->save();

        return redirect()->route('admin.desarrollo.show', $proyecto)
            ->with('status', $siguiente === FaseProyecto::Cerrado
                ? 'Fase de Control aprobada. El proyecto quedó cerrado.'
                : 'Fase aprobada. El proyecto avanzó a la siguiente etapa.');
    }

    public function retroceder(Proyecto $proyecto): RedirectResponse
    {
        $fase = $proyecto->fase_actual;
        $anterior = $fase->anterior();

        if ($anterior === null) {
            return back()->withErrors(['fase' => 'El proyecto ya está en la primera fase.']);
        }

        $registroAnterior = $this->registroFase($proyecto, $anterior);
        $registroAnterior->update(['aprobado' => false, 'fecha_aprobacion' => null]);

        $proyecto->fase_actual = $anterior;
        $proyecto->save();

        return redirect()->route('admin.desarrollo.show', $proyecto)->with('status', 'El proyecto retrocedió a la fase anterior.');
    }

    /** Finds (or lazily creates) the phase record for $fase, keyed off its dedicated model/table. */
    private function registroFase(Proyecto $proyecto, FaseProyecto $fase)
    {
        return match ($fase) {
            FaseProyecto::Planeacion => $proyecto->planeacion()->firstOrCreate([], ['checklist' => []]),
            FaseProyecto::Organizacion => $proyecto->organizacion()->firstOrCreate([], ['checklist' => [], 'equipo' => []]),
            FaseProyecto::Direccion => $proyecto->direccion()->firstOrCreate([], ['checklist' => []]),
            FaseProyecto::Control => $proyecto->control()->firstOrCreate([], ['checklist' => []]),
            FaseProyecto::Cerrado => throw new \InvalidArgumentException('El proyecto ya está cerrado.'),
        };
    }

    private function checklistKeys(FaseProyecto $fase): array
    {
        return match ($fase) {
            FaseProyecto::Planeacion => ProyectoPlaneacion::CHECKLIST,
            FaseProyecto::Organizacion => ProyectoOrganizacion::CHECKLIST,
            FaseProyecto::Direccion => ProyectoDireccion::CHECKLIST,
            FaseProyecto::Control => ProyectoControl::CHECKLIST,
            FaseProyecto::Cerrado => [],
        };
    }

    private function checklistCompleto($registro, FaseProyecto $fase): bool
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
