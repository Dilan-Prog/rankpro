<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdsConversionColumna;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Columnas personalizadas del módulo global de Conversiones — a diferencia
 * de AdsKeywordColumna (que es por grupo de anuncios), aquí son globales
 * porque la tabla de Conversiones ya mezcla todos los clientes en una sola
 * vista.
 */
class AdsConversionColumnaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
        ]);

        $columna = AdsConversionColumna::create($data);

        return response()->json($columna, 201);
    }

    public function update(Request $request, AdsConversionColumna $columna): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
        ]);

        $columna->update($data);

        return response()->json($columna->fresh());
    }

    public function destroy(AdsConversionColumna $columna): JsonResponse
    {
        $columna->delete();

        return response()->json(['deleted' => true]);
    }
}
