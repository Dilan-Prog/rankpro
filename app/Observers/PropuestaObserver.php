<?php

namespace App\Observers;

use App\Models\Propuesta;

class PropuestaObserver
{
    public bool $afterCommit = true;

    public function updated(Propuesta $propuesta): void
    {
        if ($propuesta->wasChanged('estado')) {
            webhook('propuesta.estado_cambiado', ['propuesta_id' => $propuesta->id, 'cliente_id' => $propuesta->cliente_id, 'estado' => $propuesta->estado?->value]);
        }
    }
}
