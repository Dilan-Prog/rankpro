<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoKeyword;
use App\Http\Controllers\Controller;
use App\Models\KeywordLista;
use App\Support\Reglas\KeywordListas as ReglasKeywordListas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KeywordListasController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $lista = KeywordLista::create($this->validated($request));

        return response()->json($lista->fresh(['cliente', 'responsable', 'keywords'])->toRow(), 201);
    }

    public function update(Request $request, KeywordLista $lista): JsonResponse
    {
        $lista->update($this->validated($request));

        return response()->json($lista->fresh(['cliente', 'responsable', 'keywords'])->toRow());
    }

    public function destroy(KeywordLista $lista): JsonResponse
    {
        $lista->delete();

        return response()->json(['deleted' => true]);
    }

    public function bulkDescartar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:keyword_listas,id'],
        ]);

        $listas = KeywordLista::whereIn('id', $data['ids'])->get();
        $listas->each(fn (KeywordLista $l) => $l->update(['estado' => EstadoKeyword::Descartada]));

        return response()->json([
            'listas' => $listas->fresh(['cliente', 'responsable', 'keywords'])->map(fn (KeywordLista $l) => $l->toRow()),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate(ReglasKeywordListas::guardar());
    }
}
