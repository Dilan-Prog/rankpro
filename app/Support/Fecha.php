<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Throwable;

/**
 * Formato único para MOSTRAR fechas en el panel: dd/mm/aaaa (es-MX).
 *
 * Existía una mezcla (2026-12-31 en tablas, dd/mm/aa en tarjetas, dd/mm/aaaa en
 * otras) y un 12/05/2026 ambiguo; con un solo punto de formato no se vuelve a
 * mezclar. Es solo para texto visible: lo que se guarda, se compara o viaja en
 * `value`/`data-*` sigue en ISO (Y-m-d).
 */
class Fecha
{
    /**
     * Devuelve la fecha como dd/mm/aaaa (con `H:i` si $conHora). Tolera null,
     * vacío, Carbon, DateTime y strings ISO; un valor ilegible da `$vacio`
     * porque una vista nunca debe romperse por una fecha mala.
     */
    public static function mx(mixed $valor, bool $conHora = false, string $vacio = '—'): string
    {
        $fecha = self::aCarbon($valor);

        if ($fecha === null) {
            return $vacio;
        }

        return $fecha->format($conHora ? 'd/m/Y H:i' : 'd/m/Y');
    }

    /**
     * Reescribe las fechas ISO que van dentro de un texto armado en otro lado
     * ("2026-07-21 — 2026-09-09", "act. 2026-09-03"); lo demás queda igual.
     */
    public static function enTexto(?string $texto): string
    {
        return preg_replace_callback(
            '/\b\d{4}-\d{2}-\d{2}(?: \d{2}:\d{2}(?::\d{2})?)?\b/',
            fn (array $m) => self::mx($m[0], strlen($m[0]) > 10, $m[0]),
            (string) $texto
        ) ?? (string) $texto;
    }

    private static function aCarbon(mixed $valor): ?CarbonInterface
    {
        try {
            if ($valor instanceof DateTimeInterface) {
                return Carbon::instance($valor);
            }

            if (! is_string($valor)) {
                return null;
            }

            $texto = trim($valor);

            // Ya en dd/mm/aaaa (p. ej. valores armados en un controlador): se lee explícito, no con parse().
            if (preg_match('#^(\d{2})/(\d{2})/(\d{4})(?: (\d{2}):(\d{2}))?$#', $texto, $d)) {
                return checkdate((int) $d[2], (int) $d[1], (int) $d[3])
                    ? Carbon::create((int) $d[3], (int) $d[2], (int) $d[1], (int) ($d[4] ?? 0), (int) ($d[5] ?? 0))
                    : null;
            }

            // Solo ISO: Carbon::parse('05/12/2026') leería mm/dd y daría otro día.
            if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2})(?::\d{2}(?:\.\d+)?)?)?\s*(Z|[+-]\d{2}:?\d{2})?$/', $texto, $m)) {
                return null;
            }

            // checkdate: Carbon desbordaría 2026-02-30 a marzo en silencio.
            if (! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                return null;
            }

            $fecha = Carbon::parse($texto);

            // Con zona explícita se pasa a la de la app para no mostrar el día de UTC.
            return ! empty($m[6]) ? $fecha->setTimezone(config('app.timezone')) : $fecha;
        } catch (Throwable) {
            return null;
        }
    }
}
