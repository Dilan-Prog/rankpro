<?php

namespace App\Enums;

/**
 * Shared status set for seo_campanas.estado and ads_campanas.estado.
 */
enum EstadoCampana: string
{
    case Activa = 'activa';
    case Pausada = 'pausada';
    case Finalizada = 'finalizada';
}
