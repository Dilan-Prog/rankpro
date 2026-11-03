<?php

namespace App\Observers;

use App\Models\Tarea;

class TareaObserver
{
    public bool $afterCommit = true;

    public function created(Tarea $tarea): void
    {
        // Las tareas personales (sin proyecto) son privadas: no salen a webhooks externos.
        if ($tarea->proyecto_id === null) {
            return;
        }

        webhook('tarea.creada', ['tarea_id' => $tarea->id, 'proyecto_id' => $tarea->proyecto_id, 'titulo' => $tarea->titulo]);
    }

    public function updated(Tarea $tarea): void
    {
        if ($tarea->proyecto_id === null) {
            return;
        }

        if ($tarea->wasChanged('estado') && $tarea->estado?->value === 'completada') {
            webhook('tarea.completada', ['tarea_id' => $tarea->id, 'proyecto_id' => $tarea->proyecto_id, 'titulo' => $tarea->titulo]);
        }
    }
}
