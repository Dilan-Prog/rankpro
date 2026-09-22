<?php

namespace App\Observers;

use App\Models\Finanza;

class FinanzaObserver
{
    public bool $afterCommit = true;

    public function created(Finanza $finanza): void
    {
        webhook('finanza.creada', ['finanza_id' => $finanza->id, 'cliente_id' => $finanza->cliente_id, 'concepto' => $finanza->concepto, 'monto' => $finanza->monto, 'tipo' => $finanza->tipo]);
    }

    public function updated(Finanza $finanza): void
    {
        if (! $finanza->wasChanged('estado')) {
            return;
        }

        $datos = ['finanza_id' => $finanza->id, 'cliente_id' => $finanza->cliente_id, 'monto' => $finanza->monto];

        if ($finanza->estado?->value === 'pagado') {
            webhook('finanza.pagada', $datos);
        } elseif ($finanza->estado?->value === 'vencido') {
            webhook('finanza.vencida', $datos);
        }
    }
}
