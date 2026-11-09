<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KeywordLista;
use App\Support\Keywords\ImportadorKeywords;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KeywordImportController extends Controller
{
    /**
     * Bulk-imports keywords from pasted text or an uploaded CSV/txt file into
     * a single destination list. Format per line: keyword, volumen, KD, CPC,
     * intención, URL — trailing columns optional. Malformed rows are skipped
     * (not fatal); the response reports exactly what was created vs. why a
     * given row failed, so nothing is silently dropped.
     *
     * La lógica (separador, BOM, duplicados, errores por fila, transacción) vive en
     * App\Support\Keywords\ImportadorKeywords para compartirla con el alta de cliente;
     * aquí solo se leen las líneas y se arma la respuesta.
     */
    public function store(Request $request, KeywordLista $lista, ImportadorKeywords $importador): JsonResponse
    {
        $request->validate([
            'texto' => ['nullable', 'string'],
            'archivo' => ['nullable', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $lines = ImportadorKeywords::lineas(
            $request->hasFile('archivo')
                ? file($request->file('archivo')->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
                : (string) $request->input('texto', '')
        );

        if (empty($lines)) {
            return response()->json(['message' => 'No se encontraron filas para importar.'], 422);
        }

        $resultado = $importador->importar($lista, $lines);
        $creadas = $resultado['creadas'];
        $duplicadas = $resultado['duplicadas'];
        $errores = $resultado['errores'];

        // Solo duplicadas (sin filas con error) no es un fallo: el archivo ya estaba importado.
        $estado = count($creadas) ? 201 : ($duplicadas > 0 && ! $errores ? 200 : 422);

        return response()->json([
            'creadas' => count($creadas),
            'duplicadas' => $duplicadas,
            'errores' => $errores,
            'lista' => $lista->fresh(['cliente', 'responsable', 'keywords'])->toRow(),
        ], $estado);
    }
}
