<?php

namespace App\Http\Controllers\Api\V1\Keywords;

use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Support\Api\Respuesta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Equivalente API de KeywordImportController (web), pero con payload JSON en
 * vez de texto pegado/CSV: así n8n puede mandar filas ya estructuradas desde
 * cualquier fuente (Semrush, Ahrefs, Google Keyword Planner...) sin tener que
 * armar un CSV a mano.
 */
class KeywordImportApiController extends ControladorApi
{
    public function store(Request $request, KeywordLista $lista)
    {
        $payload = $request->validate([
            'keywords' => ['required', 'array', 'min:1'],
            'modo' => ['nullable', 'in:saltar_duplicados'],
        ]);

        $existentes = ($payload['modo'] ?? null) === 'saltar_duplicados'
            ? $lista->keywords()->pluck('keyword')->map(fn ($k) => mb_strtolower(trim($k)))->all()
            : [];

        $creadas = 0;
        $duplicadas = 0;
        $errores = [];
        $vistas = [];

        foreach ($payload['keywords'] as $indice => $fila) {
            $validador = Validator::make(is_array($fila) ? $fila : [], [
                'keyword' => ['required', 'string', 'max:255'],
                'tipo' => ['nullable', 'in:principal,secundaria,long_tail,lsi'],
                'volumen_busqueda' => ['nullable', 'integer', 'min:0'],
                'dificultad' => ['nullable', 'integer', 'min:0', 'max:100'],
                'cpc_estimado' => ['nullable', 'numeric', 'min:0'],
                'intencion' => ['nullable', 'in:informacional,transaccional,navegacional'],
                'idioma' => ['nullable', 'string', 'max:10'],
                'pais' => ['nullable', 'string', 'max:10'],
                'herramienta_origen' => ['nullable', 'in:semrush,ahrefs,google_kp,otro'],
                'url_asignada' => ['nullable', 'string', 'max:255'],
                'estado' => ['nullable', 'in:en_uso,seguimiento,descartada'],
                'notas' => ['nullable', 'string', 'max:2000'],
            ]);

            if ($validador->fails()) {
                $errores[] = ['indice' => $indice, 'mensajes' => $validador->errors()->all()];

                continue;
            }

            $row = $validador->validated();
            $clave = mb_strtolower(trim($row['keyword']));

            if (($payload['modo'] ?? null) === 'saltar_duplicados' && (in_array($clave, $existentes, true) || in_array($clave, $vistas, true))) {
                $duplicadas++;

                continue;
            }

            $vistas[] = $clave;

            Keyword::create($row + [
                'cliente_id' => $lista->cliente_id,
                'lista_id' => $lista->id,
            ]);
            $creadas++;
        }

        return Respuesta::recurso([
            'creadas' => $creadas,
            'duplicadas' => $duplicadas,
            'errores' => $errores,
        ], $creadas ? 201 : 422);
    }
}
