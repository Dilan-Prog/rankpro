<?php

namespace App\Enums;

/**
 * Shared status set for clientes.estado and servicios.estado.
 */
enum EstadoClienteServicio: string
{
    case Activo = 'activo';
    case Pausado = 'pausado';
    case Cancelado = 'cancelado';
}
