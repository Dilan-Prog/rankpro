<?php

namespace App\Enums;

enum EstadoEnvioCorreo: string
{
    case Borrador = 'borrador';
    case Programado = 'programado';
    case Enviando = 'enviando';
    case Enviado = 'enviado';
    case Fallido = 'fallido';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Programado => 'Programado',
            self::Enviando => 'Enviando',
            self::Enviado => 'Enviado',
            self::Fallido => 'Fallido',
            self::Cancelado => 'Cancelado',
        };
    }

    /** Solo un borrador o un programado se pueden editar o cancelar. */
    public function editable(): bool
    {
        return in_array($this, [self::Borrador, self::Programado], true);
    }
}
