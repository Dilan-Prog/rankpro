<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Keyword;
use App\Models\KeywordLista;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class KeywordImportController extends Controller
{
    /**
     * Bulk-imports keywords from pasted text or an uploaded CSV/txt file into
     * a single destination list. Format per line: keyword, volumen, KD, CPC,
     * intención, URL — trailing columns optional. Malformed rows are skipped
     * (not fatal); the response reports exactly what was created vs. why a
     * given row failed, so nothing is silently dropped.
     *
     * El separador (coma, punto y coma o tabulador) se detecta solo: Excel en
     * español exporta CSV con «;», y el BOM UTF-8 que antepone se descarta o
     * la primera keyword llegaría con tres bytes invisibles. Las keywords que
     * ya existen en la lista (o repetidas en el mismo archivo) se omiten y se
     * cuentan en `duplicadas`: importar dos veces el mismo archivo no duplica.
     * Todo el lote va en una transacción para que un fallo a medias no deje
     * la lista a medio importar.
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
        if ($lines) {
            $lines[0] = str_starts_with($lines[0], "\xEF\xBB\xBF") ? substr($lines[0], 3) : $lines[0];
        }

        if (empty($lines)) {
            return response()->json(['message' => 'No se encontraron filas para importar.'], 422);
        }

        $separador = $this->detectarSeparador($lines);

        $creadas = [];
        $errores = [];
        $duplicadas = 0;
        $existentes = Keyword::where('lista_id', $lista->id)->pluck('keyword')
            ->mapWithKeys(fn ($k) => [mb_strtolower(trim($k)) => true])->all();

        DB::transaction(function () use ($lines, $separador, $lista, &$creadas, &$errores, &$duplicadas, &$existentes) {
            foreach ($lines as $i => $line) {
                $cols = array_map('trim', str_getcsv(trim($line), $separador));

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
                    'url_asignada' => ['nullable', 'string', 'max:255', \App\Support\Reglas\Keywords::reglaUrlAsignada()],
                ]);

                if ($validator->fails()) {
                    $errores[] = [
                        'fila' => $i + 1,
                        'texto' => $line,
                        'errores' => $validator->errors()->all(),
                    ];

                    continue;
                }

                $clave = mb_strtolower(trim($validator->validated()['keyword']));
                if (isset($existentes[$clave])) {
                    $duplicadas++;

                    continue;
                }
                $existentes[$clave] = true;

                $creadas[] = Keyword::create($validator->validated() + [
                    'cliente_id' => $lista->cliente_id,
                    'lista_id' => $lista->id,
                ]);
            }
        });

        // Solo duplicadas (sin filas con error) no es un fallo: el archivo ya estaba importado.
        $estado = count($creadas) ? 201 : ($duplicadas > 0 && ! $errores ? 200 : 422);

        return response()->json([
            'creadas' => count($creadas),
            'duplicadas' => $duplicadas,
            'errores' => $errores,
            'lista' => $lista->fresh(['cliente', 'responsable', 'keywords'])->toRow(),
        ], $estado);
    }

    /** Coma, punto y coma o tabulador: gana el que más aparece en todo el texto (coma si no hay ninguno). */
    private function detectarSeparador(array $lines): string
    {
        $texto = implode("
", $lines);
        $cuentas = [',' => substr_count($texto, ','), ';' => substr_count($texto, ';'), "	" => substr_count($texto, "	")];
        arsort($cuentas);

        return reset($cuentas) > 0 ? array_key_first($cuentas) : ',';
    }
}
