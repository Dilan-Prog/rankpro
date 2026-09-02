<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Keyword;
use App\Models\KeywordLista;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KeywordImportController extends Controller
{
    /**
     * Bulk-imports keywords from pasted text or an uploaded CSV/txt file into
     * a single destination list. Format per line: keyword, volumen, KD, CPC,
     * intención, URL — trailing columns optional. Malformed rows are skipped
     * (not fatal); the response reports exactly what was created vs. why a
     * given row failed, so nothing is silently dropped.
     */
    public function store(Request $request, KeywordLista $lista): JsonResponse
    {
        $request->validate([
            'texto' => ['nullable', 'string'],
            'archivo' => ['nullable', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $lines = $request->hasFile('archivo')
            ? file($request->file('archivo')->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
            : preg_split('/\r\n|\r|\n/', trim((string) $request->input('texto', '')));

        $lines = array_values(array_filter($lines ?: [], fn ($l) => trim((string) $l) !== ''));

        if (empty($lines)) {
            return response()->json(['message' => 'No se encontraron filas para importar.'], 422);
        }

        $creadas = [];
        $errores = [];

        foreach ($lines as $i => $line) {
            $cols = array_map('trim', str_getcsv(trim($line)));

            $row = [
                'keyword' => $cols[0] ?? null,
                'volumen_busqueda' => ($cols[1] ?? '') !== '' ? $cols[1] : null,
                'dificultad' => ($cols[2] ?? '') !== '' ? $cols[2] : null,
                'cpc_estimado' => ($cols[3] ?? '') !== '' ? $cols[3] : null,
                'intencion' => ($cols[4] ?? '') !== '' ? $cols[4] : null,
                'url_asignada' => ($cols[5] ?? '') !== '' ? $cols[5] : null,
            ];

            $validator = Validator::make($row, [
                'keyword' => ['required', 'string', 'max:255'],
                'volumen_busqueda' => ['nullable', 'integer', 'min:0'],
                'dificultad' => ['nullable', 'integer', 'min:0', 'max:100'],
                'cpc_estimado' => ['nullable', 'numeric', 'min:0'],
                'intencion' => ['nullable', 'in:informacional,transaccional,navegacional'],
                'url_asignada' => ['nullable', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                $errores[] = [
                    'fila' => $i + 1,
                    'texto' => $line,
                    'errores' => $validator->errors()->all(),
                ];

                continue;
            }

            $creadas[] = Keyword::create($validator->validated() + [
                'cliente_id' => $lista->cliente_id,
                'lista_id' => $lista->id,
            ]);
        }

        return response()->json([
            'creadas' => count($creadas),
            'errores' => $errores,
            'lista' => $lista->fresh(['cliente', 'responsable', 'keywords'])->toRow(),
        ], count($creadas) ? 201 : 422);
    }
}
