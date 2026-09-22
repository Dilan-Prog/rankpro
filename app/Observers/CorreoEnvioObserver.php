<?php

namespace App\Observers;

use App\Models\CorreoEnvio;

class CorreoEnvioObserver
{
    public bool $afterCommit = true;

    public function updated(CorreoEnvio $envio): void
    {
        if (! $envio->wasChanged('estado')) {
            return;
        }

        $datos = ['envio_id' => $envio->id, 'asunto' => $envio->asunto, 'plantilla_id' => $envio->plantilla_id];

        if ($envio->estado?->value === 'programado') {
            webhook('envio.programado', $datos + ['programado_para' => optional($envio->programado_para)->toIso8601String()]);
        } elseif ($envio->estado?->value === 'enviado') {
            webhook('correo.enviado', $datos);
        }
    }
}
