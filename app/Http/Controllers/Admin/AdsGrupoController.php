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

        return response()->json($grupo, 201);
    }

    public function update(Request $request, AdsGrupo $grupo): JsonResponse
    {
        $data = $this->validated($request);

        $grupo->update($data);

        return response()->json($grupo->fresh());
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
            'keywords' => ['nullable', 'string', 'max:2000'],
            'estado' => ['required', 'in:activo,pausado'],
        ]);

        // Keywords arrive as a comma-separated string from the form; stored as a JSON array.
        $data['keywords'] = filled($data['keywords'] ?? null)
            ? array_values(array_filter(array_map('trim', explode(',', $data['keywords']))))
            : [];
        $data['presupuesto'] = $data['presupuesto'] ?? 0;

        return $data;
    }
}
