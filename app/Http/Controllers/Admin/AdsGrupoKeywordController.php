<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdsGrupo;
use App\Models\AdsGrupoKeyword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdsGrupoKeywordController extends Controller
{
    public function store(Request $request, AdsGrupo $grupo): JsonResponse
    {
        $data = $this->validated($request);

        $keyword = $grupo->keywords()->create($data);

        return response()->json($keyword, 201);
    }

    public function update(Request $request, AdsGrupoKeyword $keyword): JsonResponse
    {
        $data = $this->validated($request);

        $keyword->update($data);

        return response()->json($keyword->fresh());
    }

    public function destroy(AdsGrupoKeyword $keyword): JsonResponse
    {
        $keyword->delete();

        return response()->json(['deleted' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'keyword' => ['required', 'string', 'max:255'],
            'volumen_busqueda' => ['nullable', 'integer', 'min:0'],
            'competencia' => ['nullable', 'in:baja,media,alta'],
            'cpc' => ['nullable', 'numeric', 'min:0'],
            'datos_personalizados' => ['nullable', 'array'],
            'datos_personalizados.*' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
