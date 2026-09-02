<?php

namespace App\Enums;

enum EstadoOnPageAccion: string
{
    case EnProgreso = 'en_progreso';
    case Completada = 'completada';
    case Pausada = 'pausada';
}
