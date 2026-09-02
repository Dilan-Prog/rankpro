<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoBug;
use App\Http\Controllers\Controller;
use App\Models\Bug;
use App\Models\Proyecto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BugController extends Controller
{
    /** Global cross-project bug list — complements the per-project nested view in Proyecto::show(), same table, no schema change (mirrors ConversionesController's relationship to IntegracionesController::conversiones()). */
    public function index(Request $request): JsonResponse
    {
        $query = Bug::with('proyecto:id,nombre')
            ->whereHas('proyecto')
            ->latest('created_at');

        if ($request->filled('proyecto_id')) {
            $query->where('proyecto_id', $request->integer('proyecto_id'));
        }
        if ($request->filled('prioridad')) {
            $query->where('prioridad', $request->string('prioridad'));
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->string('estado'));
        }

        return response()->json($query->get()->map(fn (Bug $b) => $this->toRow($b)));
    }

    public function store(Request $request, Proyecto $proyecto): JsonResponse
    {
        $bug = $proyecto->bugs()->create($this->validated($request));

        return response()->json($this->toRow($bug->fresh()->load('proyecto:id,nombre')), 201);
    }

    public function update(Request $request, Bug $bug): JsonResponse
    {
        $bug->update($this->validated($request));

        return response()->json($this->toRow($bug->fresh()->load('proyecto:id,nombre')));
    }

    public function destroy(Bug $bug): JsonResponse
    {
        $bug->delete();

        return response()->json(['deleted' => true]);
    }

    /** Shared shape for index()'s global list and store()/update()'s AJAX responses — kept in sync with DesarrolloController::bugToRow(). */
    private function toRow(Bug $b): array
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

    private function validated(Request $request): array
    {
        return $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'prioridad' => ['required', 'in:alta,media,baja'],
            'estado' => ['required', 'in:abierto,en_progreso,resuelto'],
            'fecha_resolucion' => ['nullable', 'date'],
        ]);
    }
}
