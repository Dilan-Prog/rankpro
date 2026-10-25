<?php

namespace App\Enums;

/**
 * Hacia dónde está "lo bueno" de una métrica: llamadas suben, costo por lead baja.
 */
enum SentidoMetrica: string
{
    case Mayor = 'mayor';
    case Menor = 'menor';

    public function label(): string
    {
        return match ($this) {
            self::Mayor => 'Más es mejor',
            self::Menor => 'Menos es mejor',
        };
    }
}
