<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FaseProyecto;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Proyecto;
use App\Models\ProyectoPlaneacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DesarrolloController extends Controller
{
    public function index(): View
    {
        $proyectos = Proyecto::with('cliente')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (Proyecto $p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'cliente' => $p->cliente?->nombre ?? '—',
                'tipo' => $p->tipo,
                'fase_actual' => $p->fase_actual->value,
                'estado' => $p->estado->value,
                'porcentaje_avance' => $p->porcentaje_avance,
                'fecha_inicio' => $p->fecha_inicio?->format('Y-m-d'),
                'fecha_entrega_estimada' => $p->fecha_entrega_estimada?->format('Y-m-d'),
                'presupuesto' => (float) $p->presupuesto,
                'pagos_recibidos' => (float) $p->pagos_recibidos,
                'responsable' => $p->responsable,
            ]);

        return view('admin.desarrollo.index', [
            'pageTitle' => 'Desarrollo',
            'proyectos' => $proyectos,
            'enProceso' => $proyectos->whereNotIn('fase_actual', ['cerrado'])->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.desarrollo.create', [
            'pageTitle' => 'Nuevo Proyecto',
            'clientes' => Cliente::orderBy('nombre')->pluck('nombre', 'id'),
            'checklistPlaneacion' => ProyectoPlaneacion::CHECKLIST,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'in:web_nueva,rediseno,software,landing'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'presupuesto' => ['required', 'numeric', 'min:0'],
            'anticipo' => ['nullable', 'numeric', 'min:0'],
            'forma_pago' => ['nullable', 'in:mensual,etapas,unico'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_entrega_estimada' => ['nullable', 'date'],
            'responsable' => ['nullable', 'string', 'max:255'],
            'objetivos' => ['nullable', 'string', 'max:5000'],
            'requerimientos_funcionales' => ['nullable', 'string', 'max:5000'],
            'requerimientos_tecnicos' => ['nullable', 'string', 'max:5000'],
            'checklist' => ['nullable', 'array'],
            'checklist.*' => ['boolean'],
        ]);

        $checklistKeys = array_keys(ProyectoPlaneacion::CHECKLIST);
        $checklist = collect($checklistKeys)->mapWithKeys(fn ($key) => [$key => (bool) ($data['checklist'][$key] ?? false)])->all();

        $proyecto = DB::transaction(function () use ($data, $checklist) {
            $proyecto = Proyecto::create([
                'cliente_id' => $data['cliente_id'],
                'nombre' => $data['nombre'],
                'tipo' => $data['tipo'],
                'descripcion' => $data['descripcion'] ?? null,
                'fase_actual' => FaseProyecto::Planeacion->value,
                'porcentaje_avance' => 0,
                'presupuesto' => $data['presupuesto'],
                'anticipo' => $data['anticipo'] ?? 0,
                'pagos_recibidos' => 0,
                'forma_pago' => $data['forma_pago'] ?? null,
                'fecha_inicio' => $data['fecha_inicio'] ?? null,
                'fecha_entrega_estimada' => $data['fecha_entrega_estimada'] ?? null,
                'responsable' => $data['responsable'] ?? null,
                'estado' => 'activo',
            ]);

            $proyecto->planeacion()->create([
                'objetivos' => $data['objetivos'] ?? null,
                'requerimientos_funcionales' => $data['requerimientos_funcionales'] ?? null,
                'requerimientos_tecnicos' => $data['requerimientos_tecnicos'] ?? null,
                'checklist' => $checklist,
            ]);

            return $proyecto;
        });

        return redirect()->route('admin.desarrollo.show', $proyecto)->with('status', "Proyecto \"{$proyecto->nombre}\" creado. Comienza en fase de Planeación.");
    }

    public function show(Proyecto $proyecto): View
    {
        return view('admin.desarrollo.show', [
            'pageTitle' => $proyecto->nombre,
            'proyecto' => $proyecto->load(
                'cliente',
                'planeacion',
                'organizacion',
                'direccion',
                'control',
                'tareas',
                'bugs',
                'comunicaciones',
                'qa'
            ),
        ]);
    }

    public function edit(Proyecto $proyecto): View
    {
        return view('admin.desarrollo.edit', [
            'pageTitle' => 'Editar Proyecto',
            'proyecto' => $proyecto,
            'clientes' => Cliente::orderBy('nombre')->pluck('nombre', 'id'),
        ]);
    }

    public function update(Request $request, Proyecto $proyecto): RedirectResponse
    {
        $data = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'in:web_nueva,rediseno,software,landing'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'presupuesto' => ['required', 'numeric', 'min:0'],
            'anticipo' => ['nullable', 'numeric', 'min:0'],
            'pagos_recibidos' => ['required', 'numeric', 'min:0'],
            'forma_pago' => ['nullable', 'in:mensual,etapas,unico'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_entrega_estimada' => ['nullable', 'date'],
            'fecha_entrega_real' => ['nullable', 'date'],
            'responsable' => ['nullable', 'string', 'max:255'],
            'estado' => ['required', 'in:activo,pausado,cancelado,cerrado'],
        ]);

        $proyecto->update($data);

        return redirect()->route('admin.desarrollo.show', $proyecto)->with('status', "Proyecto \"{$proyecto->nombre}\" actualizado correctamente.");
    }

    public function destroy(Proyecto $proyecto): RedirectResponse
    {
        $proyecto->delete();

        return redirect()->route('admin.desarrollo.index')->with('status', 'Proyecto eliminado.');
    }
}
