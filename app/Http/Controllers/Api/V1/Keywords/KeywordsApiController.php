<?php

namespace App\Http\Controllers\Api\V1\Keywords;

use App\Enums\EstadoKeyword;
use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\Keyword;
use App\Models\KeywordMedicion;
use App\Support\Api\{ConsultaOpciones, Respuesta};
use App\Support\Reglas\Keywords as ReglasKeywords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KeywordsApiController extends ControladorApi
{
    public function index(Request $request)
    {
        return $this->listar(Keyword::query(), $request, new ConsultaOpciones(
            buscarEn: ['keyword'],
            filtrosExactos: ['cliente_id', 'lista_id', 'estado', 'tipo', 'intencion'],
            ordenables: ['id', 'keyword', 'posicion_actual', 'volumen_busqueda', 'created_at', 'updated_at'],
            incluibles: ['cliente', 'lista'],
        ));
    }

    public function show(Keyword $keyword)
    {
        return Respuesta::recurso($keyword->load('cliente', 'lista')->toRow());
    }

    /**
     * Igual que KeywordsController::store() (web): una keyword que nace con
     * posición nace con su primera medición, para que el histórico no
     * arranque huérfano de su punto de partida.
     */
    public function store(Request $request)
    {
        $data = $request->validate(ReglasKeywords::guardar($request));
        $posicion = $data['posicion_actual'] ?? null;
        unset($data['posicion_actual'], $data['posicion_anterior']);

        $keyword = DB::transaction(function () use ($data, $posicion) {
            $keyword = Keyword::create($data);

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

        return Respuesta::recurso($keyword->fresh(['cliente', 'lista'])->toRow(), 201);
    }

    public function update(Request $request, Keyword $keyword)
    {
        $data = $request->validate(ReglasKeywords::guardar($request, $keyword));

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
                    'posicion' => $posicion ?: null,
                    'url' => $keyword->url_asignada,
                    'registrado_por' => Auth::id(),
                ]
            );

            $keyword->sincronizarPosiciones();
        });

        return Respuesta::recurso($keyword->fresh(['cliente', 'lista'])->toRow());
    }

    public function destroy(Keyword $keyword)
    {
        $keyword->delete();

        return Respuesta::eliminado();
    }

    /** A diferencia de KeywordListasController::bulkDescartar (web), esto descarta KEYWORDS sueltas, no listas. */
    public function bulkDescartar(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:keywords,id'],
        ]);

        Keyword::whereIn('id', $data['ids'])->update(['estado' => EstadoKeyword::Descartada]);

        return Respuesta::coleccion(
            Keyword::whereIn('id', $data['ids'])->with(['cliente', 'lista'])->get()->map(fn (Keyword $k) => $k->toRow())
        );
    }
}
