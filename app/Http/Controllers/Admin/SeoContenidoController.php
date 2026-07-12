<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoCampana;
use App\Models\SeoContenido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeoContenidoController extends Controller
{
    public function store(Request $request, SeoCampana $campana): JsonResponse
    {
        $data = $this->validated($request);

        $contenido = $campana->contenido()->create($data);

        return response()->json($contenido, 201);
    }

    public function update(Request $request, SeoContenido $contenido): JsonResponse
    {
        $data = $this->validated($request);

        $contenido->update($data);

        return response()->json($contenido->fresh());
    }

    public function destroy(SeoContenido $contenido): JsonResponse
    {
        $contenido->delete();

        return response()->json(['deleted' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'keyword_objetivo' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:255'],
            'trafico_generado' => ['nullable', 'integer', 'min:0'],
            'estado' => ['required', 'in:borrador,publicado,actualizar'],
        ]);
    }
}
