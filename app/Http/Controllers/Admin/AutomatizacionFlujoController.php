<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AutomatizacionFlujo;
use App\Models\AutomatizacionProyecto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutomatizacionFlujoController extends Controller
{
    public function store(Request $request, AutomatizacionProyecto $proyecto): JsonResponse
    {
        $data = $this->validated($request);

        $flujo = $proyecto->flujos()->create($data);

        return response()->json($flujo, 201);
    }

    public function update(Request $request, AutomatizacionFlujo $flujo): JsonResponse
    {
        $data = $this->validated($request);

        $flujo->update($data);

        return response()->json($flujo->fresh());
    }

    public function destroy(AutomatizacionFlujo $flujo): JsonResponse
    {
        $flujo->delete();

        return response()->json(['deleted' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'in:whatsapp,crm,email,notificaciones,otro'],
            'complejidad' => ['required', 'in:basico,intermedio,avanzado'],
            'integraciones' => ['nullable', 'array'],
            'integraciones.*' => ['string', 'max:100'],
            'horas_ahorradas_mes' => ['nullable', 'numeric', 'min:0'],
            'mensajes_gestionados_mes' => ['nullable', 'integer', 'min:0'],
            'estado' => ['required', 'in:activo,pausado,inactivo'],
            'fecha_implementado' => ['nullable', 'date'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
