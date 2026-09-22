<?php

namespace App\Observers;

use App\Models\Cliente;

class ClienteObserver
{
    public bool $afterCommit = true;

    public function created(Cliente $cliente): void
    {
        webhook('cliente.creado', ['cliente_id' => $cliente->id, 'nombre' => $cliente->nombre, 'empresa' => $cliente->empresa, 'email' => $cliente->email]);
    }

    public function updated(Cliente $cliente): void
    {
        webhook('cliente.actualizado', ['cliente_id' => $cliente->id, 'cambios' => array_keys($cliente->getChanges())]);
    }
}
