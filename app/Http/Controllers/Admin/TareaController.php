<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Proyecto;
use App\Models\Tarea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TareaController extends Controller
{
    public function store(Request $request, Proyecto $proyecto): JsonResponse
    {
        $data = $this->validated($request);

        $tarea = $proyecto->tareas()->create($data);

        return response()->json($tarea, 201);
    }

    public function update(Request $request, Tarea $tarea): JsonResponse
    {
        $data = $this->validated($request);

        $tarea->update($data);

        return response()->json($tarea->fresh());
    }

    public function destroy(Tarea $tarea): JsonResponse
    {
        $tarea->delete();

        return response()->json(['deleted' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'responsable' => ['nullable', 'string', 'max:255'],
            'prioridad' => ['required', 'in:alta,media,baja'],
            'estado' => ['required', 'in:pendiente,en_progreso,completada'],
            'fecha_limite' => ['nullable', 'date'],
        ]);
    }
}
