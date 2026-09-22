<?php

namespace App\Observers;

use App\Models\Servicio;

class ServicioObserver
{
    public bool $afterCommit = true;

    public function created(Servicio $servicio): void
    {
        webhook('servicio.creado', ['servicio_id' => $servicio->id, 'cliente_id' => $servicio->cliente_id, 'tipo' => $servicio->tipo?->value]);
    }

    public function updated(Servicio $servicio): void
    {
        if ($servicio->wasChanged('estado')) {
            webhook('servicio.estado_cambiado', ['servicio_id' => $servicio->id, 'cliente_id' => $servicio->cliente_id, 'estado' => $servicio->estado?->value]);
        }
    }
}
