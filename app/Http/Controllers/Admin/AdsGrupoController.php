<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdsCampana;
use App\Models\AdsGrupo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdsGrupoController extends Controller
{
    public function store(Request $request, AdsCampana $campana): JsonResponse
    {
        $data = $this->validated($request);

        $grupo = $campana->grupos()->create($data);

        return response()->json($grupo->load('keywords', 'columnasPersonalizadas'), 201);
    }

    public function update(Request $request, AdsGrupo $grupo): JsonResponse
    {
        $data = $this->validated($request);

        $grupo->update($data);

        return response()->json($grupo->fresh()->load('keywords', 'columnasPersonalizadas'));
    }

    public function destroy(AdsGrupo $grupo): JsonResponse
    {
        $grupo->delete();

        return response()->json(['deleted' => true]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'audiencia' => ['nullable', 'string', 'max:255'],
            'presupuesto' => ['nullable', 'numeric', 'min:0'],
            'estado' => ['required', 'in:activo,pausado'],
        ]);

        $data['presupuesto'] = $data['presupuesto'] ?? 0;

        return $data;
    }
}
