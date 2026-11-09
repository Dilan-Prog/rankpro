<?php

namespace App\Support\Portal;

use Carbon\CarbonInterface;

/** Nombre de mes en español sin depender del locale del servidor (el hosting compartido no siempre lo trae). */
class Meses
{
    private const NOMBRES = [1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    public static function etiqueta(CarbonInterface $fecha): string
    {
        return self::NOMBRES[(int) $fecha->format('n')].' '.$fecha->format('Y');
    }
}
