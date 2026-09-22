<?php

namespace App\Http\Controllers\Api\V1\Automatizaciones;

use App\Models\AutomatizacionFlujo;
use App\Models\AutomatizacionProyecto;
use App\Support\Api\{Respuesta, Serializador};
use Illuminate\Http\Request;

class AutomatizacionFlujoApiController
{
    public function index(AutomatizacionProyecto $proyecto)
    {
        return Respuesta::coleccion(Serializador::coleccion($proyecto->flujos()->get()));
    }

    public function store(Request $request, AutomatizacionProyecto $proyecto)
    {
        $data = $this->validated($request);

        $flujo = $proyecto->flujos()->create($data);

        return Respuesta::recurso(Serializador::modelo($flujo), 201);
    }

    public function update(Request $request, AutomatizacionFlujo $flujo)
    {
        $data = $this->validated($request);

        $flujo->update($data);

        return Respuesta::recurso(Serializador::modelo($flujo->fresh()));
    }

    public function destroy(AutomatizacionFlujo $flujo)
    {
        $flujo->delete();

        return Respuesta::eliminado();
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
