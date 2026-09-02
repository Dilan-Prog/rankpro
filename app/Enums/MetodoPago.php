<?php

namespace App\Enums;

enum MetodoPago: string
{
    case Transferencia = 'transferencia';
    case Tarjeta = 'tarjeta';
    case Efectivo = 'efectivo';
    case Paypal = 'paypal';
}
