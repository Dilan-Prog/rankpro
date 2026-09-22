<?php

namespace App\Http\Controllers\Api\V1\Keywords;

use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Models\KeywordMedicion;
use App\Support\Api\Respuesta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Equivalente API de KeywordMedicionController (web) — ver ese controlador
 * para el porqué de la unidad de captura por RONDA (lista + fecha). Además
 * expone POST /keywords/mediciones/lote para que n8n vuelque mediciones de
 * cualquier keyword en batch, sin tener que agruparlas por lista primero.
 */
class KeywordMedicionesApiController extends ControladorApi
{
    public function index(KeywordLista $lista)
    {
        $keywords = $lista->keywords()->orderBy('keyword')->get();

        $mediciones = KeywordMedicion::whereIn('keyword_id', $keywords->pluck('id'))
            ->with('usuario:id,name')
            ->orderBy('fecha')
            ->get();

        $fechas = $mediciones->pluck('fecha')->map(fn ($f) => $f->format('Y-m-d'))->unique()->values();

        $filas = $keywords->map(function (Keyword $k) use ($mediciones) {
            $suyas = $mediciones->where('keyword_id', $k->id);

            return [
                'keyword_id' => $k->id,
                'keyword' => $k->keyword,
                'url_asignada' => $k->url_asignada,
                'mediciones' => $suyas->mapWithKeys(fn (KeywordMedicion $m) => [
                    $m->fecha->format('Y-m-d') => $m->toRow(),
                ])->all(),
            ];
        });

        return Respuesta::recurso([
            'fechas' => $fechas->all(),
            'keywords' => $filas->values()->all(),
            'promedios' => $fechas->mapWithKeys(function (string $fecha) use ($mediciones) {
                $posiciones = $mediciones
                    ->filter(fn (KeywordMedicion $m) => $m->fecha->format('Y-m-d') === $fecha && $m->posicion !== null)
                    ->pluck('posicion');

                return [$fecha => $posiciones->isEmpty() ? null : round($posiciones->avg(), 1)];
            })->all(),
        ]);
    }

    public function store(Request $request, KeywordLista $lista)
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'mediciones' => ['required', 'array', 'min:1'],
            'mediciones.*.keyword_id' => ['required', 'integer', 'distinct'],
            'mediciones.*.posicion' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'mediciones.*.url' => ['nullable', 'string', 'max:255'],
            'mediciones.*.nota' => ['nullable', 'string', 'max:2000'],
        ]);

        $propias = $lista->keywords()->pluck('id')->all();
        $ajenas = array_diff(array_column($data['mediciones'], 'keyword_id'), $propias);

        if ($ajenas !== []) {
            return Respuesta::mensaje('Alguna keyword no pertenece a esta lista.', 422);
        }

        $fecha = date('Y-m-d', strtotime($data['fecha']));

        DB::transaction(function () use ($data, $lista, $fecha) {
            foreach ($data['mediciones'] as $m) {
                KeywordMedicion::updateOrCreate(
                    ['keyword_id' => $m['keyword_id'], 'fecha' => $fecha],
                    [
                        'lista_id' => $lista->id,
                        'posicion' => $m['posicion'] ?? null,
                        'url' => $m['url'] ?? null,
                        'nota' => $m['nota'] ?? null,
                        'registrado_por' => Auth::id(),
                    ]
                );
            }

            Keyword::whereIn('id', array_column($data['mediciones'], 'keyword_id'))
                ->get()
                ->each(fn (Keyword $k) => $k->sincronizarPosiciones());
        });

        return Respuesta::recurso([
            'fecha' => $fecha,
            'lista' => $lista->fresh(['cliente', 'keywords'])->toRow(),
        ], 201);
    }

    public function update(Request $request, KeywordMedicion $medicion)
    {
        $data = $request->validate([
            'posicion' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'url' => ['nullable', 'string', 'max:255'],
            'nota' => ['nullable', 'string', 'max:2000'],
        ]);

        $medicion->update($data);
        $medicion->keyword?->sincronizarPosiciones();

        return Respuesta::recurso([
            'medicion' => $medicion->fresh('usuario')->toRow(),
            'lista' => $medicion->lista?->fresh(['cliente', 'keywords'])->toRow(),
        ]);
    }

    public function destroy(KeywordMedicion $medicion)
    {
        $keyword = $medicion->keyword;
        $medicion->delete();
        $keyword?->sincronizarPosiciones();

        return Respuesta::eliminado();
    }

    /**
     * Volcado en batch para n8n: a diferencia de store() (una ronda = una
     * lista + una fecha), aquí cada fila trae su propia fecha y puede tocar
     * keywords de listas distintas — pensado para reimportar históricos
     * completos de una sola llamada.
     */
    public function lote(Request $request)
    {
        $data = $request->validate([
            '*' => ['required', 'array'],
            '*.keyword_id' => ['required', 'integer', 'exists:keywords,id'],
            '*.fecha' => ['required', 'date'],
            '*.posicion' => ['nullable', 'integer', 'min:1', 'max:1000'],
            '*.url' => ['nullable', 'string', 'max:255'],
            '*.nota' => ['nullable', 'string', 'max:2000'],
        ]);

        $keywordIds = [];

        DB::transaction(function () use ($data, &$keywordIds) {
            foreach ($data as $fila) {
                $fecha = date('Y-m-d', strtotime($fila['fecha']));

                $keyword = Keyword::find($fila['keyword_id']);

                KeywordMedicion::updateOrCreate(
                    ['keyword_id' => $fila['keyword_id'], 'fecha' => $fecha],
                    [
                        'lista_id' => $keyword?->lista_id,
                        'posicion' => $fila['posicion'] ?? null,
                        'url' => $fila['url'] ?? null,
                        'nota' => $fila['nota'] ?? null,
                        'registrado_por' => Auth::id(),
                    ]
                );

                $keywordIds[$fila['keyword_id']] = true;
            }

            Keyword::whereIn('id', array_keys($keywordIds))->get()->each(fn (Keyword $k) => $k->sincronizarPosiciones());
        });

        return Respuesta::recurso(['procesadas' => count($data)], 201);
    }
}
