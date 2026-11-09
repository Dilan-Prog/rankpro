<?php

namespace App\Support\Keywords;

use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Support\Reglas\Keywords as ReglasKeywords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Lógica de la importación masiva de keywords a una lista, extraída de KeywordImportController para que la use
 * también el asistente de alta de cliente (keywords semilla) sin duplicar reglas.
 *
 * Formato por línea: keyword, volumen, KD, CPC, intención, URL (columnas finales opcionales). El separador (coma,
 * punto y coma o tabulador) se detecta solo y el BOM UTF-8 se descarta. Las filas malformadas se saltan y se
 * reportan una por una; las keywords que ya existen en la lista (o repetidas en el lote) se cuentan en `duplicadas`.
 * Todo el lote va en una transacción para no dejar la lista a medio importar.
 */
class ImportadorKeywords
{
    /**
     * Parte un texto en líneas no vacías y quita el BOM de la primera.
     *
     * @return array<int, string>
     */
    public static function lineas(string|array|false|null $origen): array
    {
        $lineas = is_array($origen) ? $origen : preg_split('/\r\n|\r|\n/', trim((string) $origen));
        $lineas = array_values(array_filter($lineas ?: [], fn ($l) => trim((string) $l) !== ''));
        if ($lineas) {
            $lineas[0] = str_starts_with($lineas[0], "\xEF\xBB\xBF") ? substr($lineas[0], 3) : $lineas[0];
        }

        return $lineas;
    }

    /**
     * @param  array<int, string>  $lineas
     * @return array{creadas: array<int, Keyword>, duplicadas: int, errores: array<int, array{fila: int, texto: string, errores: array<int, string>}>}
     */
    public function importar(KeywordLista $lista, array $lineas): array
    {
        $separador = $this->detectarSeparador($lineas);

        $creadas = [];
        $errores = [];
        $duplicadas = 0;
        $existentes = Keyword::where('lista_id', $lista->id)->pluck('keyword')
            ->mapWithKeys(fn ($k) => [mb_strtolower(trim($k)) => true])->all();

        DB::transaction(function () use ($lineas, $separador, $lista, &$creadas, &$errores, &$duplicadas, &$existentes) {
            foreach ($lineas as $i => $line) {
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
                    'url_asignada' => ['nullable', 'string', 'max:255', ReglasKeywords::reglaUrlAsignada()],
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

        return ['creadas' => $creadas, 'duplicadas' => $duplicadas, 'errores' => $errores];
    }

    /** Coma, punto y coma o tabulador: gana el que más aparece en todo el texto (coma si no hay ninguno). */
    public function detectarSeparador(array $lineas): string
    {
        $texto = implode("\n", $lineas);
        $cuentas = [',' => substr_count($texto, ','), ';' => substr_count($texto, ';'), "\t" => substr_count($texto, "\t")];
        arsort($cuentas);

        return reset($cuentas) > 0 ? array_key_first($cuentas) : ',';
    }
}
