<?php

namespace App\Http\Controllers\Api\V1\Ads;

use App\Models\AdsCampana;
use App\Models\AdsOptimizacion;
use App\Support\Api\{Respuesta, Serializador};
use Illuminate\Http\Request;

class AdsOptimizacionApiController
{
    public function index(AdsCampana $campana)
    {
        return Respuesta::coleccion(Serializador::coleccion($campana->optimizaciones()->get()));
    }

    public function store(Request $request, AdsCampana $campana)
    {
        $data = $this->validated($request);

        $optimizacion = $campana->optimizaciones()->create($data);

        return Respuesta::recurso(Serializador::modelo($optimizacion), 201);
    }

    public function update(Request $request, AdsOptimizacion $optimizacion)
    {
        $data = $this->validated($request);

        $optimizacion->update($data);

        return Respuesta::recurso(Serializador::modelo($optimizacion->fresh()));
    }

    public function destroy(AdsOptimizacion $optimizacion)
    {
        $optimizacion->delete();

        return Respuesta::eliminado();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'fecha' => ['required', 'date'],
            'tipo' => ['required', 'in:puja,audiencia,creativo,presupuesto,keyword'],
            'descripcion' => ['required', 'string', 'max:2000'],
            'resultado' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
