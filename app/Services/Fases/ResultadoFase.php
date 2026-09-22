<?php

namespace App\Services\Fases;

use Illuminate\Database\Eloquent\Model;

/**
 * Resultado de una transición de fase: el modelo ya refrescado y un mensaje
 * para el flash de sesión (web) o para incluir en la respuesta JSON (API).
 */
final class ResultadoFase
{
    public function __construct(
        public readonly Model $modelo,
        public readonly string $mensaje,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'mensaje' => $this->mensaje,
            'fase_actual' => $this->modelo->fase_actual->value,
            'ciclo_actual' => $this->modelo->ciclo_actual,
            'estado' => $this->modelo->estado->value,
        ];
    }
}
