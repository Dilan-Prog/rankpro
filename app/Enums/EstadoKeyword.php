<?php

namespace App\Enums;

enum EstadoKeyword: string
{
    case EnUso = 'en_uso';
    case Seguimiento = 'seguimiento';
    case Descartada = 'descartada';
}
