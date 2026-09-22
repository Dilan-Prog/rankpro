<?php

namespace App\Observers;

use App\Models\Archivo;

class ArchivoObserver
{
    public bool $afterCommit = true;

    public function created(Archivo $archivo): void
    {
        webhook('archivo.subido', ['archivo_id' => $archivo->id, 'cliente_id' => $archivo->cliente_id, 'nombre' => $archivo->nombre, 'tipo' => $archivo->tipo?->value]);
    }
}
