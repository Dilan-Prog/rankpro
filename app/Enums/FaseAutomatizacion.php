<?php

namespace App\Enums;

/**
 * Automatizaciones is a recurring service, same shape as FaseSeo/FaseAds:
 * Diagnóstico/Diseño de Flujo/Implementación/Reporte cycle indefinitely via
 * AutomatizacionFaseController::nuevoCiclo(). "Cerrada" is a separate
 * terminal state reached only via the explicit "Cerrar Proyecto" action from
 * the Reporte phase — never through siguiente(). El motor de automatización
 * en sí (n8n) vive fuera del sistema; este módulo solo lleva el registro del
 * proceso de venta/entrega y las estadísticas de impacto.
 */
enum FaseAutomatizacion: string
{
    case Diagnostico = 'diagnostico';
    case DisenoFlujo = 'diseno_flujo';
    case Implementacion = 'implementacion';
    case Reporte = 'reporte';
    case Cerrada = 'cerrada';

    public function siguiente(): ?self
    {
        return match ($this) {
            self::Diagnostico => self::DisenoFlujo,
            self::DisenoFlujo => self::Implementacion,
            self::Implementacion => self::Reporte,
            self::Reporte, self::Cerrada => null,
        };
    }

    public function anterior(): ?self
    {
        return match ($this) {
            self::Diagnostico, self::Cerrada => null,
            self::DisenoFlujo => self::Diagnostico,
            self::Implementacion => self::DisenoFlujo,
            self::Reporte => self::Implementacion,
        };
    }

    public function orden(): int
    {
        return match ($this) {
            self::Diagnostico => 1,
            self::DisenoFlujo => 2,
            self::Implementacion => 3,
            self::Reporte => 4,
            self::Cerrada => 5,
        };
    }
}
