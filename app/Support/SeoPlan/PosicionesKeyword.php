<?php

namespace App\Support\SeoPlan;

use App\Models\Keyword;
use App\Models\KeywordMedicion;
use Illuminate\Support\Collection;

/**
 * Posición actual y anterior de cada keyword, de solo lectura.
 *
 * Se toman de las dos últimas KeywordMedicion (la fuente real) y, si una
 * keyword no tiene mediciones, de las columnas `posicion_actual/anterior` del
 * banco: son una caché derivada de ese histórico (ver Keyword::sincronizarPosiciones)
 * y así no se pierde el dato de keywords capturadas antes de que existiera el histórico.
 * Aquí no se recalcula ni se escribe nada.
 */
class PosicionesKeyword
{
    /**
     * @param  Collection<int, Keyword>  $keywords
     * @return array<int, array{actual: ?int, anterior: ?int}>
     */
    public static function para(Collection $keywords): array
    {
        if ($keywords->isEmpty()) {
            return [];
        }

        $mediciones = KeywordMedicion::query()
            ->whereIn('keyword_id', $keywords->pluck('id'))
            ->orderByDesc('fecha')->orderByDesc('id')
            ->get(['keyword_id', 'fecha', 'posicion'])
            ->groupBy('keyword_id');

        $resultado = [];
        foreach ($keywords as $keyword) {
            $ultimas = $mediciones->get($keyword->id, collect())->take(2)->values();

            $resultado[$keyword->id] = $ultimas->isNotEmpty()
                ? ['actual' => $ultimas[0]->posicion, 'anterior' => $ultimas[1]->posicion ?? null]
                : ['actual' => $keyword->posicion_actual, 'anterior' => $keyword->posicion_anterior];
        }

        return $resultado;
    }
}
