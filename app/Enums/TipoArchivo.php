<?php

namespace App\Enums;

enum TipoArchivo: string
{
    case Contrato = 'contrato';
    case Propuesta = 'propuesta';
    case Diseno = 'diseno';
    case Reporte = 'reporte';
    case Datos = 'datos';
    case Entregable = 'entregable';
    case Otro = 'otro';
}
