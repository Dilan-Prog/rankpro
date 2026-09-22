<?php

namespace App\Http\Controllers\Api\V1\Desarrollo;

use App\Http\Controllers\Controller;
use App\Models\Proyecto;
use App\Models\ProyectoQa;
use App\Support\Api\Respuesta;
use App\Support\Api\Serializador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Pruebas de QA de un proyecto. Mismas reglas que Admin\QaController. */
class QaApiController extends Controller
{
    public function store(Request $request, Proyecto $proyecto): JsonResponse
    {
        $qa = $proyecto->qa()->create($this->validated($request));

        return Respuesta::recurso(Serializador::modelo($qa), 201);
    }

    public function update(Request $request, ProyectoQa $qa): JsonResponse
    {
        $qa->update($this->validated($request));

        return Respuesta::recurso(Serializador::modelo($qa->fresh()));
    }

    public function destroy(ProyectoQa $qa): JsonResponse
    {
        $qa->delete();

        return Respuesta::eliminado();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'tipo_prueba' => ['required', 'in:funcional,visual,rendimiento,seguridad'],
            'resultado' => ['required', 'in:aprobado,fallido'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
