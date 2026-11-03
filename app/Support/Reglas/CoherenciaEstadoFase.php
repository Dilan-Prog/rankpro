<?php

namespace App\Support\Reglas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * El estado de una campaña/proyecto por fases (SEO, Ads, Automatizaciones)
 * debe ser coherente con su fase: `cerrar()` de la máquina pone fase Cerrada
 * + estado Finalizada a la vez, pero la edición manual del estado permitía
 * "activa" con la fase Cerrada o "finalizada" sin haber cerrado la fase, y
 * los dos campos quedaban contando historias distintas.
 */
class CoherenciaEstadoFase
{
    /**
     * @param  Model  $modelo  con `fase_actual` (enum con case Cerrada)
     * @param  string  $estado  valor ya validado: activa|pausada|finalizada
     *
     * @throws ValidationException
     */
    public static function validar(Model $modelo, string $estado): void
    {
        $cerrada = $modelo->fase_actual->value === 'cerrada';

        if ($estado === 'activa' && $cerrada) {
            throw ValidationException::withMessages([
                'estado' => 'La fase está cerrada: no puede quedar en estado activa.',
            ]);
        }

        if ($estado === 'finalizada' && ! $cerrada) {
            throw ValidationException::withMessages([
                'estado' => 'Para finalizar, primero aprueba el reporte y cierra la fase; no se puede marcar finalizada con la fase abierta.',
            ]);
        }
    }
}
