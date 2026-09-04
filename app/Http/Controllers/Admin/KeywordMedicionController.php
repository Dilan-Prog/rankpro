<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Models\KeywordMedicion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Histórico de posiciones del banco de keywords.
 *
 * El trabajo real es mensual y por lista: se miden todas las keywords de una
 * lista de una sentada y se anota, keyword a keyword, qué se hizo ese mes. Por
 * eso la unidad de captura es la RONDA (una lista + una fecha), no la keyword
 * suelta: si cada una llevara su propia fecha, los meses no serían comparables
 * y la evolución no se podría dibujar.
 */
class KeywordMedicionController extends Controller
{
    /**
     * Matriz de la lista: una fila por keyword, una columna por ronda.
     *
     * Se sirve bajo demanda y no dentro de `KeywordLista::toRow()` a propósito:
     * el índice del banco pinta todas las listas de todos los clientes, y
     * arrastrar el histórico completo de cada una lo volvería lento sin que
     * nadie lo esté mirando.
     */
    public function index(KeywordLista $lista): JsonResponse
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

        return response()->json([
            'fechas' => $fechas->all(),
            'keywords' => $filas->values()->all(),
            // Posición media de la lista en cada ronda: es la cifra que enseña
            // el avance de un vistazo. Sólo promedia las keywords medidas y con
            // posición, para que una sin datos no cuente como cero.
            'promedios' => $fechas->mapWithKeys(function (string $fecha) use ($mediciones) {
                $posiciones = $mediciones
                    ->filter(fn (KeywordMedicion $m) => $m->fecha->format('Y-m-d') === $fecha && $m->posicion !== null)
                    ->pluck('posicion');

                return [$fecha => $posiciones->isEmpty() ? null : round($posiciones->avg(), 1)];
            })->all(),
        ]);
    }

    /**
     * Guarda una ronda completa. Volver a enviar la misma fecha corrige la
     * ronda en vez de duplicarla: medir dos veces el mismo día es una
     * corrección, no dos mediciones.
     */
    public function store(Request $request, KeywordLista $lista): JsonResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'mediciones' => ['required', 'array', 'min:1'],
            // `distinct` porque dos entradas para la misma keyword en una ronda
            // no fallaban: la segunda pisaba a la primera en silencio y el cuerpo
            // mal formado se daba por bueno.
            'mediciones.*.keyword_id' => ['required', 'integer', 'distinct'],
            'mediciones.*.posicion' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'mediciones.*.url' => ['nullable', 'string', 'max:255'],
            'mediciones.*.nota' => ['nullable', 'string', 'max:2000'],
        ]);

        $propias = $lista->keywords()->pluck('id')->all();
        $ajenas = array_diff(array_column($data['mediciones'], 'keyword_id'), $propias);

        if ($ajenas !== []) {
            return response()->json(['message' => 'Alguna keyword no pertenece a esta lista.'], 422);
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

            // Las columnas del banco son una caché de las dos últimas
            // mediciones: se recalculan aquí para que la tabla y el histórico
            // no puedan discrepar.
            Keyword::whereIn('id', array_column($data['mediciones'], 'keyword_id'))
                ->get()
                ->each(fn (Keyword $k) => $k->sincronizarPosiciones());
        });

        return response()->json([
            'fecha' => $fecha,
            'lista' => $lista->fresh(['cliente', 'keywords'])->toRow(),
        ], 201);
    }

    /** Corrige una medición suelta: la posición, la URL o la nota de qué se hizo. */
    public function update(Request $request, KeywordMedicion $medicion): JsonResponse
    {
        $data = $request->validate([
            'posicion' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'url' => ['nullable', 'string', 'max:255'],
            'nota' => ['nullable', 'string', 'max:2000'],
        ]);

        $medicion->update($data);
        $medicion->keyword?->sincronizarPosiciones();

        // Se devuelve tambien la lista: corregir una medicion puede mover la
        // `posicion_actual` de la keyword y con ella la `posicion_promedio` de
        // la lista, y sin esto la tabla del banco se quedaba mostrando la cifra
        // vieja hasta recargar la pagina.
        return response()->json([
            'medicion' => $medicion->fresh('usuario')->toRow(),
            'lista' => $medicion->lista?->fresh(['cliente', 'keywords'])->toRow(),
        ]);
    }

    public function destroy(KeywordMedicion $medicion): JsonResponse
    {
        $keyword = $medicion->keyword;
        $medicion->delete();
        $keyword?->sincronizarPosiciones();

        return response()->json(['deleted' => true]);
    }
}
