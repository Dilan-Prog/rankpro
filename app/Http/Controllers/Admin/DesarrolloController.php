<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoBug;
use App\Enums\FaseProyecto;
use App\Http\Controllers\Controller;
use App\Models\Bug;
use App\Models\Cliente;
use App\Models\Proyecto;
use App\Models\ProyectoPlaneacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DesarrolloController extends Controller
{
    public function index(): View
    {
        $proyectos = Proyecto::with(['cliente', 'organizacion', 'bugs' => fn ($q) => $q->select('id', 'proyecto_id', 'estado')])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (Proyecto $p) => $this->toRow($p));

        $bugs = Bug::with('proyecto:id,nombre')
            ->whereHas('proyecto')
            ->latest('created_at')
            ->get()
            ->map(fn (Bug $b) => $this->bugToRow($b));

        return view('admin.desarrollo.index', [
            'pageTitle' => 'Módulo de Desarrollo',
            'proyectos' => $proyectos,
            'bugs' => $bugs,
            'enProceso' => $proyectos->where('fase_actual', '!=', 'cerrado')->count(),
            'clientes' => Cliente::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request): JsonResponse
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
        ]);

        $proyecto = DB::transaction(function () use ($data) {
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
                'checklist' => collect(array_keys(ProyectoPlaneacion::CHECKLIST))->mapWithKeys(fn ($key) => [$key => false])->all(),
            ]);

            return $proyecto;
        });

        return response()->json($this->toRow($proyecto->fresh(['cliente', 'organizacion', 'bugs'])), 201);
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

    public function update(Request $request, Proyecto $proyecto): JsonResponse
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

        return response()->json($this->toRow($proyecto->fresh(['cliente', 'organizacion', 'bugs'])));
    }

    public function destroy(Proyecto $proyecto): JsonResponse
    {
        $proyecto->delete();

        return response()->json(['deleted' => true]);
    }

    /** Shared shape for index()'s server-rendered cards and store()/update()'s AJAX responses. */
    private function toRow(Proyecto $p): array
    {
        $pendiente = max(0, (float) $p->presupuesto - (float) $p->pagos_recibidos);

        return [
            'id' => $p->id,
            'cliente_id' => $p->cliente_id,
            'cliente' => $p->cliente?->nombre ?? '—',
            'nombre' => $p->nombre,
            'tipo' => $p->tipo,
            'descripcion' => $p->descripcion,
            'fase_actual' => $p->fase_actual->value,
            'fase_orden' => $p->fase_actual->orden(),
            'estado' => $p->estado->value,
            'porcentaje_avance' => $p->porcentaje_avance,
            'presupuesto' => (float) $p->presupuesto,
            'anticipo' => (float) $p->anticipo,
            'pagos_recibidos' => (float) $p->pagos_recibidos,
            'pendiente' => $pendiente,
            'forma_pago' => $p->forma_pago?->value,
            'fecha_inicio' => $p->fecha_inicio?->format('Y-m-d'),
            'fecha_entrega_estimada' => $p->fecha_entrega_estimada?->format('Y-m-d'),
            'fecha_entrega_real' => $p->fecha_entrega_real?->format('Y-m-d'),
            'responsable' => $p->responsable,
            'bugs_abiertos_count' => $p->bugs->whereIn('estado', [EstadoBug::Abierto, EstadoBug::EnProgreso])->count(),
            'url_repositorio' => $p->organizacion?->url_repositorio,
            'url_staging' => $p->organizacion?->url_staging,
            'show_url' => route('admin.desarrollo.show', $p->id),
        ];
    }

    /** Shared shape for index()'s embedded bug rows — kept in sync with BugController::toRow(). */
    private function bugToRow(Bug $b): array
    {
        return [
            'id' => $b->id,
            'proyecto_id' => $b->proyecto_id,
            'proyecto_nombre' => $b->proyecto->nombre,
            'titulo' => $b->titulo,
            'descripcion' => $b->descripcion,
            'prioridad' => $b->prioridad,
            'estado' => $b->estado->value,
            'fecha_resolucion' => $b->fecha_resolucion?->format('Y-m-d'),
            'created_at' => $b->created_at->format('Y-m-d'),
            'dias_abierto' => $b->estado === EstadoBug::Resuelto ? null : $b->created_at->diffInDays(now()),
        ];
    }
}
