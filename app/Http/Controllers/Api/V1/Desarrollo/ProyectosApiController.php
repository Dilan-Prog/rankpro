<?php

namespace App\Http\Controllers\Api\V1\Desarrollo;

use App\Enums\EstadoBug;
use App\Enums\FaseProyecto;
use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\Proyecto;
use App\Models\ProyectoPlaneacion;
use App\Support\Api\Consulta;
use App\Support\Api\ConsultaOpciones;
use App\Support\Api\Respuesta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CRUD de proyectos de Desarrollo. Mismas reglas y misma forma de fila que
 * Admin\DesarrolloController (ver su toRow() privado, replicado aquí porque
 * Proyecto no tiene un toRow() propio en el modelo).
 */
class ProyectosApiController extends ControladorApi
{
    /**
     * No usa $this->listar(): esa ayuda serializa con Serializador::modelo(),
     * que cae a toArray() porque Proyecto no tiene toRow(). Se aplica
     * Consulta::aplicar() igual, pero el transform es el toRow() de abajo,
     * para que el listado tenga la misma forma que show/store/update.
     */
    public function index(Request $request): JsonResponse
    {
        $paginador = Consulta::aplicar(Proyecto::query()->with(['cliente', 'organizacion', 'bugs']), $request, new ConsultaOpciones(
            buscarEn: ['nombre', 'responsable'],
            filtrosExactos: ['cliente_id', 'fase_actual', 'estado', 'tipo'],
            ordenables: ['id', 'nombre', 'fecha_inicio', 'fecha_entrega_estimada', 'created_at', 'updated_at'],
            incluibles: ['tareas', 'comunicaciones', 'qa', 'planeacion', 'direccion', 'control'],
        ));

        return Respuesta::paginada($paginador, fn (Proyecto $p) => $this->toRow($p));
    }

    public function show(Request $request, Proyecto $proyecto): JsonResponse
    {
        $incluibles = ['cliente', 'planeacion', 'organizacion', 'direccion', 'control', 'tareas', 'bugs', 'comunicaciones', 'qa'];
        $incluir = array_values(array_intersect(array_filter(explode(',', (string) $request->string('incluir'))), $incluibles));

        return Respuesta::recurso($this->toRow($proyecto->load(array_values(array_unique(array_merge(['cliente', 'organizacion', 'bugs'], $incluir))))));
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

        return Respuesta::recurso($this->toRow($proyecto->fresh(['cliente', 'organizacion', 'bugs'])), 201);
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

        return Respuesta::recurso($this->toRow($proyecto->fresh(['cliente', 'organizacion', 'bugs'])));
    }

    public function destroy(Proyecto $proyecto): JsonResponse
    {
        $proyecto->delete();

        return Respuesta::eliminado();
    }

    /** Misma forma que Admin\DesarrolloController::toRow() (privado ahí, replicado aquí). */
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
            'bugs_abiertos_count' => $p->relationLoaded('bugs') ? $p->bugs->whereIn('estado', [EstadoBug::Abierto, EstadoBug::EnProgreso])->count() : null,
            'url_repositorio' => $p->organizacion?->url_repositorio,
            'url_staging' => $p->organizacion?->url_staging,
            'planeacion' => $p->relationLoaded('planeacion') ? $p->planeacion : null,
            'organizacion' => $p->relationLoaded('organizacion') ? $p->organizacion : null,
            'direccion' => $p->relationLoaded('direccion') ? $p->direccion : null,
            'control' => $p->relationLoaded('control') ? $p->control : null,
            'tareas' => $p->relationLoaded('tareas') ? $p->tareas : null,
            'bugs' => $p->relationLoaded('bugs') ? $p->bugs : null,
            'comunicaciones' => $p->relationLoaded('comunicaciones') ? $p->comunicaciones : null,
            'qa' => $p->relationLoaded('qa') ? $p->qa : null,
        ];
    }
}
