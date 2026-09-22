<?php

namespace App\Listeners;

use App\Events\EventoWebhook;
use App\Services\Webhooks\Despachador;

class EmitirWebhook
{
    public function __construct(private Despachador $despachador)
    {
    }

    public function handle(EventoWebhook $event): void
    {
        $this->despachador->emitir($event->evento, $event->datos);
    }
}
