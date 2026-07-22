<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdsGrupo;
use App\Models\AdsKeywordColumna;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdsKeywordColumnaController extends Controller
{
    public function store(Request $request, AdsGrupo $grupo): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
        ]);

        $columna = $grupo->columnasPersonalizadas()->create($data);

        return response()->json($columna, 201);
    }

    public function update(Request $request, AdsKeywordColumna $columna): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
        ]);

        $columna->update($data);

        return response()->json($columna->fresh());
    }

    public function destroy(AdsKeywordColumna $columna): JsonResponse
    {
        $columna->delete();

        return response()->json(['deleted' => true]);
    }
}
