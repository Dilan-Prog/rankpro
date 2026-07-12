<?php

namespace App\Enums;

/**
 * SEO is a recurring service: Auditoría/Estrategia/Ejecución/Reporte cycle
 * indefinitely via SeoFaseController::nuevoCiclo(). "Cerrada" is a separate
 * terminal state reached only via the explicit "Cerrar Campaña" action from
 * the Reporte phase (SeoFaseController::cerrar()) — never through siguiente().
 * Pausing is unrelated to this enum entirely; it just flips
 * seo_campanas.estado to 'pausada' without touching fase_actual.
 */
enum FaseSeo: string
{
    case Auditoria = 'auditoria';
    case Estrategia = 'estrategia';
    case Ejecucion = 'ejecucion';
    case Reporte = 'reporte';
    case Cerrada = 'cerrada';

    public function siguiente(): ?self
    {
        return match ($this) {
            self::Auditoria => self::Estrategia,
            self::Estrategia => self::Ejecucion,
            self::Ejecucion => self::Reporte,
            self::Reporte, self::Cerrada => null,
        };
    }

    public function anterior(): ?self
    {
        return match ($this) {
            self::Auditoria, self::Cerrada => null,
            self::Estrategia => self::Auditoria,
            self::Ejecucion => self::Estrategia,
            self::Reporte => self::Ejecucion,
        };
    }

    public function orden(): int
    {
        return match ($this) {
            self::Auditoria => 1,
            self::Estrategia => 2,
            self::Ejecucion => 3,
            self::Reporte => 4,
            self::Cerrada => 5,
        };
    }
}
