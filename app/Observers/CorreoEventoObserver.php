<?php

namespace App\Observers;

use App\Models\CorreoEvento;

class CorreoEventoObserver
{
    public bool $afterCommit = true;

    public function created(CorreoEvento $evento): void
    {
        $destinatario = $evento->destinatario;
        $datos = [
            'destinatario_id' => $evento->destinatario_id,
            'envio_id' => $destinatario?->envio_id,
            'email' => $destinatario?->email,
            'url' => $evento->url,
        ];

        if ($evento->tipo === 'apertura') {
            webhook('correo.abierto', $datos);
        } elseif ($evento->tipo === 'clic') {
            webhook('correo.clic', $datos);
        }
    }
}
