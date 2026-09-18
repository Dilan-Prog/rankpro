<?php

namespace App\Enums;

enum CategoriaPlantillaCorreo: string
{
    case Reportes = 'reportes';
    case Facturacion = 'facturacion';
    case Propuestas = 'propuestas';
    case Onboarding = 'onboarding';
    case Otros = 'otros';

    public function label(): string
    {
        return match ($this) {
            self::Reportes => 'Reportes',
            self::Facturacion => 'Facturación',
            self::Propuestas => 'Propuestas',
            self::Onboarding => 'Onboarding',
            self::Otros => 'Otros',
        };
    }
}
