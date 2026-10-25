<?php

namespace App\Support\SearchConsole;

/**
 * Todo texto que viene de Google (o de una excepción) pasa por aquí antes de guardarse o mostrarse:
 * sin llaves PEM, sin rutas absolutas del servidor y con largo acotado, para que un fallo nunca filtre secretos.
 */
class Saneador
{
    public const LARGO = 300;

    /** @param  array<int, string>  $secretos  textos exactos que no deben aparecer (p. ej. la llave privada) */
    public static function mensaje(?string $texto, int $max = self::LARGO, array $secretos = []): string
    {
        $texto = (string) $texto;

        $texto = preg_replace('/-----BEGIN [A-Z ]+-----.*?(-----END [A-Z ]+-----|$)/s', '[llave omitida]', $texto) ?? '';

        foreach ($secretos as $secreto) {
            if (is_string($secreto) && strlen($secreto) >= 8) {
                $texto = str_replace($secreto, '[omitido]', $texto);
            }
        }

        // Rutas de Windows (C:\laragon\...) y de Unix con al menos dos segmentos (/var/www/...); las URL no se tocan.
        $texto = preg_replace('/[A-Za-z]:\\\\[^\s\'"]+/', '[ruta]', $texto) ?? '';
        $texto = preg_replace('#(?<![\w:/.])/(?:[\w.\-]+/)+[\w.\-]*#', '[ruta]', $texto) ?? '';

        $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? '');

        return mb_strlen($texto) > $max ? rtrim(mb_substr($texto, 0, $max - 1)).'…' : $texto;
    }
}
