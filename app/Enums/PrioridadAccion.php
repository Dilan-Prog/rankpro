<?php

namespace App\Enums;

/**
 * P0 bloqueante técnico, P1 alto retorno en el sprint, P2 mejora acumulativa,
 * P3 exploratorio.
 */
enum PrioridadAccion: string
{
    case P0 = 'p0';
    case P1 = 'p1';
    case P2 = 'p2';
    case P3 = 'p3';

    public function orden(): int
    {
        return match ($this) {
            self::P0 => 0,
            self::P1 => 1,
            self::P2 => 2,
            self::P3 => 3,
        };
    }
}
