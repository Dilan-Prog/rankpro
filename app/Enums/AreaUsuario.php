<?php

namespace App\Enums;

enum AreaUsuario: string
{
    case Direccion = 'direccion';
    case Seo = 'seo';
    case Ads = 'ads';
    case Social = 'social';
    case Desarrollo = 'desarrollo';
    case Administracion = 'administracion';
    case Externo = 'externo';
}
