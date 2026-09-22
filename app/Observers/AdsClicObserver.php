<?php

namespace App\Observers;

use App\Models\AdsClic;

class AdsClicObserver
{
    public bool $afterCommit = true;

    public function created(AdsClic $clic): void
    {
        webhook('clic.registrado', ['clic_id' => $clic->id, 'cliente_id' => $clic->cliente_id, 'ads_campana_id' => $clic->ads_campana_id, 'visitor_id' => $clic->visitor_id]);
    }
}
