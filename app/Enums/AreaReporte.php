<?php

namespace App\Enums;

/**
 * Área de servicio que cubre un reporte entregable. Cada área trae su propia
 * plantilla de secciones (ver App\Support\Reportes\Plantillas) pero comparte
 * el mismo motor de armado y los mismos renderizadores.
 */
enum AreaReporte: string
{
    case Seo = 'seo';
    case Ads = 'ads';
    case Web = 'web';

    public function label(): string
    {
        return match ($this) {
            self::Seo => 'SEO',
            self::Ads => 'Publicidad',
            self::Web => 'Sitio Web',
        };
    }

    /** Prefijo del folio del entregable: REP-SEO-2026-0001. */
    public function prefijo(): string
    {
        return 'REP-'.mb_strtoupper($this->value);
    }
}
