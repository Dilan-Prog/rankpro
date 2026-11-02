<?php

namespace App\Enums;

/** Tipos del Calendario; el color de cada uno vive en el CSS (data-tipo). */
enum TipoEventoCalendario: string
{
    case Reunion = 'reunion';
    case Entrega = 'entrega';
    case Pago = 'pago';
    case Campana = 'campana';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Reunion => 'Reunión',
            self::Entrega => 'Entrega',
            self::Pago => 'Pago',
            self::Campana => 'Campaña',
            self::Otro => 'Otro',
        };
    }
}
