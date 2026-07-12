<?php

namespace App\Enums;

enum FormaPagoProyecto: string
{
    case Mensual = 'mensual';
    case Etapas = 'etapas';
    case Unico = 'unico';
}
