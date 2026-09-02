<?php

namespace App\Enums;

enum FormaPago: string
{
    case Mensual = 'mensual';
    case Trimestral = 'trimestral';
    case Anual = 'anual';
}
