<?php

namespace App\Enums;

/**
 * Tipos de sección que puede contener un reporte. Cada tipo tiene tres
 * implementaciones paralelas: un editor (resources/views/admin/reportes/
 * secciones/_{tipo}.blade.php), un renderizador PDF (resources/views/pdf/
 * reporte/_{tipo}.blade.php) y una rama en RenderizadorXlsx.
 *
 * La forma del JSON de cada tipo vive en App\Support\Reportes\EsquemaSeccion.
 */
enum TipoSeccion: string
{
    case Kpis = 'kpis';
    case Hallazgos = 'hallazgos';
    case Serie = 'serie';
    case Tabla = 'tabla';
    case Ficha = 'ficha';
    case Plan = 'plan';
    case Texto = 'texto';

    public function label(): string
    {
        return match ($this) {
            self::Kpis => 'Indicadores',
            self::Hallazgos => 'Hallazgos',
            self::Serie => 'Serie temporal',
            self::Tabla => 'Tabla de datos',
            self::Ficha => 'Fichas por URL',
            self::Plan => 'Plan de acción',
            self::Texto => 'Texto',
        };
    }

    /** Tipos cuyo editor acepta pegado masivo desde una hoja de cálculo. */
    public function admitePegado(): bool
    {
        return in_array($this, [self::Serie, self::Tabla, self::Ficha, self::Plan], true);
    }
}
