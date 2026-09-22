<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Evento genérico de dominio -> webhook. Un solo Listener (EmitirWebhook)
 * lo traduce a Despachador::emitir(); los tests usan Event::fake() para
 * comprobar que se disparó sin depender del transporte HTTP real.
 */
class EventoWebhook
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $datos
     */
    public function __construct(public readonly string $evento, public readonly array $datos)
    {
    }
}
