<?php

namespace App\Observers;

use App\Models\AdsConversion;

class AdsConversionObserver
{
    public bool $afterCommit = true;

    public function created(AdsConversion $conversion): void
    {
        webhook('conversion.registrada', [
            'conversion_id' => $conversion->id,
            'cliente_id' => $conversion->cliente_id,
            'tipo' => $conversion->tipo?->value,
            'valor' => $conversion->valor,
        ]);
    }
}
