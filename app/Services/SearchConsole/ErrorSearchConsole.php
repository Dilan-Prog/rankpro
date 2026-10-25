<?php

namespace App\Services\SearchConsole;

use RuntimeException;

/** Error de Search Console ya traducido a español y saneado: su mensaje se puede mostrar y guardar tal cual. */
class ErrorSearchConsole extends RuntimeException
{
    public function __construct(string $mensaje, public readonly ?int $estadoHttp = null)
    {
        parent::__construct($mensaje);
    }
}
