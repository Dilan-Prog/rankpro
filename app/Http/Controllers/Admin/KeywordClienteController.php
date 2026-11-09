<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoKeyword;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Models\KeywordMedicion;
use Illuminate\Contracts\View\View;

/**
 * Vista de solo lectura del banco de keywords de UN cliente: KPI, datos de contacto y sus listas apiladas.
 *
 * Vive aparte de KeywordsController para no tocar el CRUD. Todo se calcula en memoria con dos consultas
 * (keywords y mediciones) para que el número de consultas no crezca con las listas.
 */
class KeywordClienteController extends Controller
{
    private const TOP = 10;

    public function show(Cliente $cliente): View
    {
        $listas = KeywordLista::where('cliente_id', $cliente->id)->with('responsable')->orderBy('nombre')->get();

        // Las descartadas no cuentan: son ruido que el equipo ya decidió no trabajar.
        $keywords = Keyword::where('cliente_id', $cliente->id)
            ->where('estado', '!=', EstadoKeyword::Descartada->value)
            ->orderBy('keyword')
            ->get();

        // Las más recientes primero: la primera de cada keyword es la actual y la segunda la anterior.
        $mediciones = $keywords->isEmpty()
            ? collect()
            : KeywordMedicion::whereIn('keyword_id', $keywords->pluck('id'))
                ->orderByDesc('fecha')->orderByDesc('id')
                ->get(['keyword_id', 'posicion', 'fecha'])
                ->groupBy('keyword_id');

        $filas = $keywords->map(function (Keyword $k) use ($mediciones) {
            $ultimas = $mediciones->get($k->id, collect())->whereNotNull('posicion')->values();
            $hayMedicion = $ultimas->isNotEmpty();

            return [
                'keyword' => $k,
                // Sin medición se usa la caché del banco; si tampoco hay, queda null (se pinta guion).
                'actual' => $hayMedicion ? $ultimas[0]->posicion : $k->posicion_actual,
                'anterior' => $hayMedicion ? ($ultimas[1]->posicion ?? null) : $k->posicion_anterior,
            ];
        });

        $conPosicion = $filas->filter(fn ($f) => $f['actual'] !== null && $f['actual'] > 0);

        $kpi = [
            'keywords' => $filas->count(),
            'listas' => $listas->count(),
            'top10' => $conPosicion->filter(fn ($f) => $f['actual'] <= self::TOP)->count(),
            'posicion_media' => $conPosicion->isEmpty() ? null : round($conPosicion->avg('actual'), 1),
        ];

        // Fecha de la última ronda medida (la del dato, no la de edición de la keyword); sin mediciones queda null.
        $ultimaMedicion = $mediciones->flatten(1)->whereNotNull('posicion')->max('fecha');

        $porLista = $filas->groupBy(fn ($f) => $f['keyword']->lista_id ?? 0);

        return view('admin.keywords.cliente', [
            'pageTitle' => 'Keywords — '.($cliente->nombre_comercial),
            'cliente' => $cliente,
            'kpi' => $kpi,
            'ultimaMedicion' => $ultimaMedicion,
            'listas' => $listas,
            'filasPorLista' => $porLista,
            'sinLista' => $porLista->get(0, collect()),
        ]);
    }
}
