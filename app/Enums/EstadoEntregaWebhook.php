<?php

namespace App\Enums;

enum EstadoEntregaWebhook: string
{
    case Pendiente = 'pendiente';
    case Entregado = 'entregado';
    case Fallido = 'fallido';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Entregado => 'Entregado',
            self::Fallido => 'Fallido',
        };
    }
}
