<?php

namespace App\Enums;

/**
 * Ads campaigns are recurring like SEO: Briefing/Configuración are approved
 * once (foundational setup), then Lanzamiento/Reporte cycle indefinitely via
 * AdsFaseController::nuevoCiclo(). "Cerrada" is a terminal state reached only
 * through the explicit "Cerrar Campaña" action from the Reporte phase — never
 * through siguiente(). Pausing doesn't touch this enum; it flips
 * ads_campanas.estado to 'pausada'.
 */
enum FaseAds: string
{
    case Briefing = 'briefing';
    case Configuracion = 'configuracion';
    case Lanzamiento = 'lanzamiento';
    case Reporte = 'reporte';
    case Cerrada = 'cerrada';

    public function siguiente(): ?self
    {
        return match ($this) {
            self::Briefing => self::Configuracion,
            self::Configuracion => self::Lanzamiento,
            self::Lanzamiento => self::Reporte,
            self::Reporte, self::Cerrada => null,
        };
    }

    public function anterior(): ?self
    {
        return match ($this) {
            self::Briefing, self::Cerrada => null,
            self::Configuracion => self::Briefing,
            self::Lanzamiento => self::Configuracion,
            self::Reporte => self::Lanzamiento,
        };
    }

    public function orden(): int
    {
        return match ($this) {
            self::Briefing => 1,
            self::Configuracion => 2,
            self::Lanzamiento => 3,
            self::Reporte => 4,
            self::Cerrada => 5,
        };
    }
}
