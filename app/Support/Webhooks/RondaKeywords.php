<?php

namespace App\Support\Webhooks;

use App\Models\KeywordLista;
use App\Models\KeywordMedicion;

/**
 * Un solo aviso por ronda guardada: el evento por medición manda 50 webhooks
 * a n8n por cada ronda de una lista.
 */
class RondaKeywords
{
    public static function emitir(int $listaId, string $fecha): void
    {
        $lista = KeywordLista::withTrashed()->with('cliente')->find($listaId);
        if (! $lista) {
            return;
        }

        $mediciones = KeywordMedicion::where('lista_id', $listaId)->whereDate('fecha', $fecha);
        $posiciones = (clone $mediciones)->whereNotNull('posicion')->pluck('posicion');

        webhook('keyword.ronda_registrada', [
            'lista_id' => $lista->id,
            'cliente_id' => $lista->cliente_id,
            'cliente' => $lista->cliente?->nombre,
            'fecha' => $fecha,
            'total_mediciones' => $mediciones->count(),
            'con_posicion' => $posiciones->count(),
            'posicion_promedio' => $posiciones->isEmpty() ? null : round($posiciones->avg(), 1),
        ]);
    }
}
