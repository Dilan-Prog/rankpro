<?php

namespace App\Enums;

enum EstadoBug: string
{
    case Abierto = 'abierto';
    case EnProgreso = 'en_progreso';
    case Resuelto = 'resuelto';
}
