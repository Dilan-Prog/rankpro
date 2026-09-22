<?php

namespace App\Http\Controllers\Api\V1\Desarrollo;

use App\Enums\EstadoTarea;
use App\Http\Controllers\Controller;
use App\Models\Proyecto;
use App\Models\Tarea;
use App\Support\Api\Respuesta;
use App\Support\Api\Serializador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Tareas de un proyecto. Mismas reglas que Admin\TareaController. */
class TareasApiController extends Controller
{
    public function store(Request $request, Proyecto $proyecto): JsonResponse
    {
        $tarea = $proyecto->tareas()->create($this->validated($request));

        return Respuesta::recurso(Serializador::modelo($tarea), 201);
    }

    public function update(Request $request, Tarea $tarea): JsonResponse
    {
        $tarea->update($this->validated($request));

        return Respuesta::recurso(Serializador::modelo($tarea->fresh()));
    }

    public function destroy(Tarea $tarea): JsonResponse
    {
        $tarea->delete();

        return Respuesta::eliminado();
    }

    /** Atajo del contrato: marca la tarea como completada sin mandar el resto del payload. */
    public function completar(Tarea $tarea): JsonResponse
    {
        $tarea->update(['estado' => EstadoTarea::Completada->value]);

        return Respuesta::recurso(Serializador::modelo($tarea->fresh()));
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
