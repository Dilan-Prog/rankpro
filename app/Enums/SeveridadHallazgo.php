<?php

namespace App\Enums;

enum SeveridadHallazgo: string
{
    case Critico = 'critico';
    case Alto = 'alto';
    case Medio = 'medio';
    case Informativo = 'informativo';

    /** Menor es más grave: ordena la lista de hallazgos del reporte. */
    public function orden(): int
    {
        return match ($this) {
            self::Critico => 1,
            self::Alto => 2,
            self::Medio => 3,
            self::Informativo => 4,
        };
    }
}
