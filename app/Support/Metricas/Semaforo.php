<?php

namespace App\Support\Metricas;

use App\Enums\SentidoMetrica;

/**
 * Color de una métrica contra su meta. Función pura, sin BD, para poder
 * probarla y reutilizarla (reportes) sin arrastrar modelos.
 */
class Semaforo
{
    public const VERDE = 'verde';
    public const AMARILLO = 'amarillo';
    public const ROJO = 'rojo';
    public const SIN_META = 'sin_meta';
    public const SIN_DATO = 'sin_dato';

    /**
     * @param  float|int|string|null  $valor
     * @param  float|int|string|null  $meta
     */
    public static function evaluar($valor, $meta, SentidoMetrica|string $sentido, int $umbral = 80): string
    {
        if ($valor === null || $valor === '') {
            return self::SIN_DATO;
        }
        if ($meta === null || $meta === '') {
            return self::SIN_META;
        }

        $valor = (float) $valor;
        $meta = (float) $meta;
        $sentido = $sentido instanceof SentidoMetrica ? $sentido : SentidoMetrica::from($sentido);
        $umbral = max(0, min(100, $umbral));

        // Se compara contra límites calculados, nunca valor/meta: así meta 0 no divide entre cero.
        if ($sentido === SentidoMetrica::Mayor) {
            if ($valor >= $meta) {
                return self::VERDE;
            }

            return $valor >= $meta * $umbral / 100 ? self::AMARILLO : self::ROJO;
        }

        if ($valor <= $meta) {
            return self::VERDE;
        }

        // Espejo del umbral: con 80 % se tolera hasta 120 % de la meta antes de pasar a rojo.
        return $valor <= $meta * (200 - $umbral) / 100 ? self::AMARILLO : self::ROJO;
    }
}
