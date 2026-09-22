<?php

namespace App\Observers;

use App\Models\KeywordMedicion;

class KeywordMedicionObserver
{
    public bool $afterCommit = true;

    public function created(KeywordMedicion $medicion): void
    {
        webhook('keyword.medicion_registrada', [
            'keyword_id' => $medicion->keyword_id,
            'lista_id' => $medicion->lista_id,
            'fecha' => optional($medicion->fecha)->format('Y-m-d'),
            'posicion' => $medicion->posicion,
        ]);
    }
}
