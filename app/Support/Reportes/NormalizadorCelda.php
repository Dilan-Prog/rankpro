<?php

namespace App\Support\Reportes;

/**
 * Normaliza el valor de una celda al tipo declarado de su columna.
 *
 * Vive aquí, y no dentro del controlador, porque hay DOS puertas de entrada al
 * mismo dato —el pegado masivo y el autosave del editor— y antes solo la
 * primera normalizaba: un `1,5` pegado se guardaba como 1.5 y el mismo `1,5`
 * tecleado se guardaba como la cadena "1,5", que el Armador acababa leyendo
 * como 15. Un único camino para el dato evita que el valor dependa de cómo
 * entró.
 *
 * Decisiones, tomadas para que un pegado de Excel/GSC en español entre sin
 * limpieza manual previa:
 *
 * - Separadores: si la celda trae `.` y `,` a la vez, el ÚLTIMO en aparecer
 *   es el decimal y el otro es de millares. Si solo trae uno y aparece
 *   varias veces, es de millares. Si solo trae uno y aparece una vez, es
 *   ambiguo (`1.068` puede ser mil sesenta y ocho o uno coma cero sesenta y
 *   ocho): se resuelve por el tipo de columna — en una columna `numero`
 *   (enteros: clics, impresiones) con exactamente tres dígitos detrás se lee
 *   como millares; en cualquier otro caso, como decimal.
 * - Porcentaje: el signo `%` es la ÚNICA señal de que el número viene en
 *   escala 0-100. `3,09%` -> 0.0309 y `0.0309` -> 0.0309, porque un export
 *   de Search Console ya trae la fracción. Se guarda siempre la fracción
 *   decimal para que renderizadores y totales `ctr` no tengan que adivinar
 *   la escala.
 * - Moneda: se descartan símbolos y letras (`$`, `€`, `USD`) antes de leer.
 * - Vacío: en columnas numéricas pasa a null (una celda en blanco no es un
 *   cero); en columnas de texto se queda como '' para que las reglas
 *   `required` del esquema lo rechacen y la fila salga reportada.
 */
class NormalizadorCelda
{
    /** Tipos cuyo valor se lee como número; el resto se deja tal cual. */
    private const NUMERICOS = ['numero', 'decimal', 'porcentaje', 'moneda'];

    /**
     * @param  string  $tipo  uno de EsquemaSeccion::FORMATOS, más `clave` para
     *                        las columnas de vocabulario cerrado (la prioridad
     *                        de un plan), que se comparan en minúsculas.
     */
    public static function celda(mixed $valor, string $tipo): mixed
    {
        if ($tipo === 'clave') {
            return mb_strtolower(trim((string) self::escalar($valor)));
        }

        if (! in_array($tipo, self::NUMERICOS, true)) {
            // `texto`, `fecha` y `nivel` se guardan tal cual: son cadenas
            // libres y cualquier "limpieza" aquí destruiría datos legítimos.
            return is_string($valor) ? trim($valor) : $valor;
        }

        if ($valor === null || $valor === '' || is_array($valor) || is_bool($valor)) {
            return null;
        }

        // Un valor que ya llega como número (el editor manda JSON tipado) no
        // pasa por la desambiguación de separadores: `1.068` en un float ya
        // es uno coma cero sesenta y ocho, y leerlo como millares lo
        // multiplicaría por mil en cada autosave.
        if (is_int($valor) || is_float($valor)) {
            return $tipo === 'numero' ? (int) round($valor) : $valor;
        }

        $texto = trim((string) $valor);

        if ($texto === '') {
            return null;
        }

        $esPorcentaje = str_contains($texto, '%');
        $limpio = preg_replace('/[^0-9,.\-]/u', '', $texto) ?? '';

        if ($limpio === '' || ! preg_match('/\d/', $limpio)) {
            return null;
        }

        $ultimoPunto = strrpos($limpio, '.');
        $ultimaComa = strrpos($limpio, ',');

        if ($ultimoPunto !== false && $ultimaComa !== false) {
            $decimal = $ultimoPunto > $ultimaComa ? '.' : ',';
            $millares = $decimal === '.' ? ',' : '.';
            $limpio = str_replace($millares, '', $limpio);
            $limpio = str_replace($decimal, '.', $limpio);
        } elseif ($ultimoPunto !== false || $ultimaComa !== false) {
            $sep = $ultimoPunto !== false ? '.' : ',';
            $partes = explode($sep, $limpio);
            $cola = end($partes);

            $esMillares = count($partes) > 2
                || ($tipo === 'numero' && strlen($cola) === 3);

            $limpio = $esMillares
                ? implode('', $partes)
                : implode('.', $partes);
        }

        if (! is_numeric($limpio)) {
            return null;
        }

        $numero = (float) $limpio;

        if ($esPorcentaje) {
            // Se redondea para no dejar ruido binario (3,09% -> 0.0308999...)
            // guardado tal cual en el JSON de la sección.
            return round($numero / 100, 6);
        }

        // Las columnas `numero` son enteras (clics, impresiones, páginas) y la
        // regla `integer` de Laravel rechaza un float, aunque sea 3.0.
        return $tipo === 'numero' ? (int) round($numero) : $numero;
    }

    /** Evita que un array o un objeto revienten el casteo a cadena. */
    private static function escalar(mixed $valor): string|int|float|bool|null
    {
        return is_scalar($valor) || $valor === null ? $valor : '';
    }
}
