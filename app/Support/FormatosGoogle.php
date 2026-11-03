<?php

namespace App\Support;

/**
 * Formatos válidos de los códigos de Google. Es la lista blanca de TODO lo que se imprime en el
 * HTML público: un valor que no encaje aquí no se emite, así que un dato raro en la BD o en el
 * .env nunca termina dentro de un <script>.
 */
class FormatosGoogle
{
    public const GTM = '/^GTM-[A-Z0-9]{4,10}$/';

    public const GA4 = '/^G-[A-Z0-9]{6,12}$/';

    public const ADS = '/^AW-\d{6,15}$/';

    public const CONVERSION = '/^AW-\d{6,15}\/[A-Za-z0-9_-]{6,40}$/';

    /** Ejemplos para mostrar en el panel y en los mensajes de error. */
    public static function ejemplos(): array
    {
        return [
            'gtm' => 'GTM-ABC1234',
            'ga4' => 'G-ABCDE12345',
            'ads' => 'AW-123456789',
            'conversion' => 'AW-123456789/AbC_dEfGhIj',
        ];
    }

    /** Trim + mayúsculas; devuelve el ID solo si tiene el formato, si no null. */
    public static function gtm(?string $valor): ?string
    {
        return self::id($valor, self::GTM);
    }

    public static function ga4(?string $valor): ?string
    {
        return self::id($valor, self::GA4);
    }

    public static function ads(?string $valor): ?string
    {
        return self::id($valor, self::ADS);
    }

    /** La parte posterior a «/» distingue mayúsculas de minúsculas: solo se normaliza el «AW-…». */
    public static function conversion(?string $valor): ?string
    {
        $valor = trim((string) $valor);
        if (! str_contains($valor, '/')) {
            return null;
        }

        [$cuenta, $etiqueta] = explode('/', $valor, 2);
        $normalizada = strtoupper(trim($cuenta)).'/'.trim($etiqueta);

        return preg_match(self::CONVERSION, $normalizada) === 1 ? $normalizada : null;
    }

    /** Trim + mayúsculas sin validar (para volver a validar o mostrar el valor); vacío => null. */
    public static function normalizarId(?string $valor): ?string
    {
        $valor = strtoupper(trim((string) $valor));

        return $valor === '' ? null : $valor;
    }

    /** Igual que normalizarId pero para una etiqueta de conversión (la etiqueta conserva mayúsculas/minúsculas). */
    public static function normalizarConversion(?string $valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }
        if (! str_contains($valor, '/')) {
            return $valor;
        }

        [$cuenta, $etiqueta] = explode('/', $valor, 2);

        return strtoupper(trim($cuenta)).'/'.trim($etiqueta);
    }

    private static function id(?string $valor, string $formato): ?string
    {
        $valor = strtoupper(trim((string) $valor));

        return preg_match($formato, $valor) === 1 ? $valor : null;
    }
}
