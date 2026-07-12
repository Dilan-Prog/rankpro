<?php

namespace App\Enums;

/**
 * SEO is a recurring service, not a one-off delivery project: Auditoría
 * and Estrategia are approved once (foundational setup), then the
 * campaign cycles between Ejecución and Reporte indefinitely — there is
 * no terminal "cerrado" state like FaseProyecto has. See
 * SeoFaseController::siguienteCiclo() for the loop-back transition.
 */
enum FaseSeo: string
{
    case Auditoria = 'auditoria';
    case Estrategia = 'estrategia';
    case Ejecucion = 'ejecucion';
    case Reporte = 'reporte';

    public function siguiente(): ?self
    {
        return match ($this) {
            self::Auditoria => self::Estrategia,
            self::Estrategia => self::Ejecucion,
            self::Ejecucion => self::Reporte,
            self::Reporte => null,
        };
    }

    public function anterior(): ?self
    {
        return match ($this) {
            self::Auditoria => null,
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
        };
    }
}
