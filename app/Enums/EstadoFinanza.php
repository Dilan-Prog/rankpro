<?php

namespace App\Enums;

enum EstadoFinanza: string
{
    case Pagado = 'pagado';
    case Pendiente = 'pendiente';
    case Vencido = 'vencido';
}
