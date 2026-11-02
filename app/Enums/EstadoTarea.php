<?php

namespace App\Enums;

enum EstadoTarea: string
{
    case Pendiente = 'pendiente';
    case EnProgreso = 'en_progreso';
    case EnRevision = 'en_revision';
    case Completada = 'completada';
}
