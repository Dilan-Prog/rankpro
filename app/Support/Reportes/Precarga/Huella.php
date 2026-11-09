<?php

namespace App\Support\Reportes\Precarga;

/**
 * Huella de lo que la precarga sembró. "Actualizar datos" solo reemplaza lo que
 * sigue igual a su huella; lo que alguien tocó a mano ya no coincide y se respeta.
 *
 * La forma se canonicaliza (números como números, sin importar si llegaron "12" o
 * 12.0, null = '') porque el autosave del editor normaliza celdas numéricas: sin
 * esto, guardar el título de la sección ya cambiaría la huella de datos intactos.
 */
class Huella
{
    public static function de(mixed $valor): string
    {
        return md5((string) json_encode(self::canonico($valor), JSON_UNESCAPED_UNICODE));
    }

    private static function canonico(mixed $valor): mixed
    {
        if (is_array($valor)) {
            $salida = array_map(fn ($v) => self::canonico($v), $valor);
            if (! array_is_list($salida)) {
                ksort($salida);
            }

            return $salida;
        }

        if ($valor === null || $valor === '') {
            return '';
        }

        if (is_bool($valor)) {
            return $valor ? '1' : '0';
        }

        if (is_numeric($valor)) {
            return (string) round((float) $valor, 4);
        }

        return trim((string) $valor);
    }
}
