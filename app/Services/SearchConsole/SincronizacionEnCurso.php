<?php

namespace App\Services\SearchConsole;

use RuntimeException;

/** Otra sincronización de la misma propiedad (comando o botón) tiene el candado; el llamador decide si responde 409 o salta. */
class SincronizacionEnCurso extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Ya hay una sincronización en curso para esta propiedad. Espera a que termine e inténtalo de nuevo.');
    }
}
