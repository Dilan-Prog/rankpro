<?php

namespace App\Enums;

enum TipoConversion: string
{
    case Formulario = 'formulario';
    case Whatsapp = 'whatsapp';
    case Llamada = 'llamada';
    case Compra = 'compra';
}
