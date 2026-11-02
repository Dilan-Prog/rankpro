<?php

namespace App\Enums;

/**
 * Estados de una acción on-page / actividad del SEO Plan. `Pendiente` y
 * `EnRevision` se añadieron con el kanban del SEO Plan; el módulo SEO original
 * solo conocía los otros tres.
 */
enum EstadoOnPageAccion: string
{
    case Pendiente = 'pendiente';
    case EnProgreso = 'en_progreso';
    case EnRevision = 'en_revision';
    case Completada = 'completada';
    case Pausada = 'pausada';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnProgreso => 'En curso',
            self::EnRevision => 'En revisión',
            self::Completada => 'Hecha',
            self::Pausada => 'Pausada',
        };
    }
}
