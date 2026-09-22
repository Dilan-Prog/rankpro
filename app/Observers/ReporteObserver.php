<?php

namespace App\Observers;

use App\Models\Reporte;

class ReporteObserver
{
    public bool $afterCommit = true;

    public function updated(Reporte $reporte): void
    {
        if ($reporte->wasChanged('estado') && $reporte->estado?->value === 'entregado') {
            webhook('reporte.entregado', ['reporte_id' => $reporte->id, 'cliente_id' => $reporte->cliente_id, 'titulo' => $reporte->titulo]);
        }
    }
}
