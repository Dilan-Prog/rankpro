<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdsCampana;
use App\Models\AdsOptimizacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdsOptimizacionController extends Controller
{
    public function store(Request $request, AdsCampana $campana): JsonResponse
    {
        $data = $this->validated($request);

        $optimizacion = $campana->optimizaciones()->create($data);

        return response()->json($optimizacion, 201);
    }

    public function update(Request $request, AdsOptimizacion $optimizacion): JsonResponse
    {
        $data = $this->validated($request);

        $optimizacion->update($data);

        return response()->json($optimizacion->fresh());
    }

    public function destroy(AdsOptimizacion $optimizacion): JsonResponse
    {
        $optimizacion->delete();

        return response()->json(['deleted' => true]);
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
