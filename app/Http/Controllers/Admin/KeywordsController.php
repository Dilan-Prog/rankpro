<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Models\KeywordMedicion;
use App\Models\User;
use App\Support\Reglas\Keywords as ReglasKeywords;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class KeywordsController extends Controller
{
    public function index(): View
    {
        $listas = KeywordLista::with(['cliente', 'responsable', 'keywords' => fn ($q) => $q->orderBy('keyword')])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (KeywordLista $l) => $l->toRow());

        $keywordsSinLista = Keyword::whereNull('lista_id')
            ->with('cliente')
            ->orderBy('keyword')
            ->get()
            ->map(fn (Keyword $k) => $k->toRow());

        return view('admin.keywords.index', [
            'pageTitle' => 'Banco de Keywords',
            'listas' => $listas,
            'keywordsSinLista' => $keywordsSinLista,
            'totalKeywords' => $listas->sum('keywords_count') + $keywordsSinLista->count(),
            'clientes' => Cliente::orderBy('nombre')->pluck('nombre', 'id'),
            'usuarios' => User::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $posicion = $data['posicion_actual'] ?? null;
        unset($data['posicion_actual'], $data['posicion_anterior']);

        $keyword = DB::transaction(function () use ($data, $posicion) {
            $keyword = Keyword::create($data);

            // Una keyword que nace con posición nace con su primera medición:
            // si sólo se escribiera la columna, esa posición quedaría fuera del
            // histórico y el primer avance que se midiera no tendría contra qué
            // compararse.
            if ($posicion) {
                KeywordMedicion::create([
                    'keyword_id' => $keyword->id,
                    'lista_id' => $keyword->lista_id,
                    'fecha' => now()->toDateString(),
                    'posicion' => $posicion,
                    'url' => $keyword->url_asignada,
                    'registrado_por' => Auth::id(),
                ]);

                $keyword->sincronizarPosiciones();
            }

            return $keyword;
        });

        return response()->json($keyword->fresh(['cliente', 'lista'])->toRow(), 201);
    }

    public function update(Request $request, Keyword $keyword): JsonResponse
    {
        $data = $this->validated($request, $keyword);

        // Editar la posición desde la ficha registra una medición con fecha de
        // hoy en vez de escribir la columna a mano. `posicion_actual` y
        // `posicion_anterior` pasaron a ser una caché de las dos últimas
        // mediciones (ver Keyword::sincronizarPosiciones), así que tocarlas
        // directamente dejaría la tabla del banco contando una cosa y el
        // histórico otra.
        $cambiaPosicion = array_key_exists('posicion_actual', $data)
            && (int) ($data['posicion_actual'] ?? 0) !== (int) ($keyword->posicion_actual ?? 0);

        $posicion = $data['posicion_actual'] ?? null;
        unset($data['posicion_actual'], $data['posicion_anterior']);

        DB::transaction(function () use ($keyword, $data, $cambiaPosicion, $posicion) {
            $keyword->update($data);

            if (! $cambiaPosicion) {
                return;
            }

            KeywordMedicion::updateOrCreate(
                ['keyword_id' => $keyword->id, 'fecha' => now()->toDateString()],
                [
                    'lista_id' => $keyword->lista_id,
                    // El banco admitía 0 como "sin dato"; en el histórico eso es
                    // null, que es lo que significa.
                    'posicion' => $posicion ?: null,
                    'url' => $keyword->url_asignada,
                    'registrado_por' => Auth::id(),
                ]
            );

            $keyword->sincronizarPosiciones();
        });

        return response()->json($keyword->fresh(['cliente', 'lista'])->toRow());
    }

    public function destroy(Keyword $keyword): JsonResponse
    {
        $keyword->delete();

        return response()->json(['deleted' => true]);
    }

    private function validated(Request $request, ?Keyword $keyword = null): array
    {
        return $request->validate(ReglasKeywords::guardar($request, $keyword));
    }
}
