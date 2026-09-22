<?php

use App\Events\EventoWebhook;

if (! function_exists('webhook')) {
    /**
     * Dispara un evento de webhook. Punto único de emisión desde Observers y
     * servicios: `webhook('cliente.creado', ['cliente_id' => $cliente->id, ...])`.
     *
     * @param  array<string, mixed>  $datos
     */
    function webhook(string $evento, array $datos): void
    {
        event(new EventoWebhook($evento, $datos));
    }
}
