<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Proyecto;
use App\Models\ProyectoQa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QaController extends Controller
{
    public function store(Request $request, Proyecto $proyecto): JsonResponse
    {
        $data = $this->validated($request);

        $qa = $proyecto->qa()->create($data);

        return response()->json($qa, 201);
    }

    public function update(Request $request, ProyectoQa $qa): JsonResponse
    {
        $data = $this->validated($request);

        $qa->update($data);

        return response()->json($qa->fresh());
    }

    public function destroy(ProyectoQa $qa): JsonResponse
    {
        $qa->delete();

        return response()->json(['deleted' => true]);
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
