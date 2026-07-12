<?php

namespace App\Enums;

enum TipoPruebaQa: string
{
    case Funcional = 'funcional';
    case Visual = 'visual';
    case Rendimiento = 'rendimiento';
    case Seguridad = 'seguridad';
}
