<?php

namespace App\Support\Reportes\Precarga;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Meses en español sin depender del locale de Carbon (el servidor compartido no lo trae).
 */
class Periodo
{
    private const MESES = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    private const CORTOS = [1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    /** "agosto 2026". */
    public static function etiqueta(CarbonInterface $fecha): string
    {
        return self::MESES[$fecha->month].' '.$fecha->year;
    }

    /** "ago 2026": para el eje X de las gráficas. */
    public static function corta(CarbonInterface $fecha): string
    {
        return self::CORTOS[$fecha->month].' '.$fecha->year;
    }

    /** "Agosto 2026": para títulos. */
    public static function titulo(CarbonInterface $fecha): string
    {
        return mb_convert_case(self::etiqueta($fecha), MB_CASE_TITLE, 'UTF-8');
    }

    /** Acepta "2026-08"; cualquier otra cosa da null. */
    public static function desdeTexto(mixed $valor): ?Carbon
    {
        if (! is_string($valor) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $valor)) {
            return null;
        }

        return Carbon::createFromFormat('!Y-m', $valor);
    }
}
