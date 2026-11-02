<?php

namespace App\Enums;

/**
 * Tipo de actividad del SEO Plan. Las acciones on-page viejas no tienen tipo
 * (columna nullable); en el plan se tratan como «sin tipo», no como On-page,
 * para no inventar un dato que nadie capturó.
 */
enum TipoActividadSeo: string
{
    case OnPage = 'on_page';
    case Contenido = 'contenido';
    case Backlink = 'backlink';
    case Tecnica = 'tecnica';

    public function label(): string
    {
        return match ($this) {
            self::OnPage => 'On-page',
            self::Contenido => 'Contenido',
            self::Backlink => 'Backlink',
            self::Tecnica => 'Técnica',
        };
    }
}
