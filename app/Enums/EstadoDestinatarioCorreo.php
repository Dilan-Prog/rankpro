<?php

namespace App\Enums;

enum EstadoDestinatarioCorreo: string
{
    case Pendiente = 'pendiente';
    case Enviado = 'enviado';
    case Fallido = 'fallido';
}
