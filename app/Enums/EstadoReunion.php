<?php

namespace App\Enums;

enum EstadoReunion: string
{
    case Confirmada = 'confirmada';
    case Pendiente = 'pendiente';
    case Completada = 'completada';
    case NoShow = 'no_show';
    case Cancelada = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Confirmada => 'Confirmada',
            self::Pendiente => 'Pendiente',
            self::Completada => 'Completada',
            self::NoShow => 'No asistió',
            self::Cancelada => 'Cancelada',
        };
    }

    /** Una reunión ocupa su horario mientras no esté cancelada. */
    public function ocupaHorario(): bool
    {
        return $this !== self::Cancelada;
    }
}
