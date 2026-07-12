<?php

namespace App\Enums;

enum EstadoProyecto: string
{
    case Activo = 'activo';
    case Pausado = 'pausado';
    case Cancelado = 'cancelado';
    case Cerrado = 'cerrado';
}
