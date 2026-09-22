<?php

namespace App\Exceptions;

use Exception;

/**
 * Error de regla de negocio (no de validación de forma) que la API traduce a
 * un JSON {message, campo} en vez de un 500. Ejemplos: aprobar una fase con
 * el checklist incompleto, mandar un envío ya enviado.
 */
class ErrorDeDominio extends Exception
{
    public function __construct(string $mensaje, public readonly string $campo = 'general', public readonly int $status = 422)
    {
        parent::__construct($mensaje);
    }
}
