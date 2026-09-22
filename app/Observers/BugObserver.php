<?php

namespace App\Observers;

use App\Models\Bug;

class BugObserver
{
    public bool $afterCommit = true;

    public function created(Bug $bug): void
    {
        webhook('bug.creado', ['bug_id' => $bug->id, 'proyecto_id' => $bug->proyecto_id, 'titulo' => $bug->titulo, 'prioridad' => $bug->prioridad]);
    }

    public function updated(Bug $bug): void
    {
        if ($bug->wasChanged('estado') && $bug->estado?->value === 'resuelto') {
            webhook('bug.resuelto', ['bug_id' => $bug->id, 'proyecto_id' => $bug->proyecto_id, 'titulo' => $bug->titulo]);
        }
    }
}
