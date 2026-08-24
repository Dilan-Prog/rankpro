<?php

namespace App\Enums;

enum EstadoArticulo: string
{
    case Borrador = 'borrador';
    case Publicado = 'publicado';
    case Archivado = 'archivado';

    /**
     * Solo los publicados son visibles y entran en el sitemap.
     *
     * "Archivado" existe para retirar un articulo del blog sin borrarlo: la URL
     * deja de servirse y sale del sitemap, pero el texto se conserva por si hay
     * que fusionarlo con otro (el plan contempla podar y fusionar en el mes 11).
     */
    public function esPublico(): bool
    {
        return $this === self::Publicado;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Publicado => 'Publicado',
            self::Archivado => 'Archivado',
        };
    }
}
