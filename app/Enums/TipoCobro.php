<?php

namespace App\Enums;

/**
 * Cómo se cobra un servicio. Solo `Mensual` es ingreso recurrente (MRR): el pago
 * único y lo gratuito no se repiten cada mes y sumarlos como "precio mensual" inflaba la cifra.
 */
enum TipoCobro: string
{
    case Mensual = 'mensual';
    case Unico = 'unico';
    case Gratuito = 'gratuito';

    public function label(): string
    {
        return match ($this) {
            self::Mensual => 'Mensual',
            self::Unico => 'Pago único',
            self::Gratuito => 'Gratuito',
        };
    }
}
