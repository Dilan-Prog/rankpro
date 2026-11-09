<?php

namespace App\Support\Clientes;

/**
 * Normaliza el sitio web del cliente (`clientes.sitio_web`), que es la fuente desde la que se PRECARGAN los demás
 * formularios (SEO, Search Console, reportes). Aquí solo se interpreta el texto; no se migra ningún dato existente.
 */
class Sitio
{
    /** "hotelx.com" -> "https://hotelx.com"; una URL con esquema se conserva; vacío -> null. */
    public static function normalizar(?string $texto): ?string
    {
        $texto = trim((string) $texto);
        if ($texto === '') {
            return null;
        }

        return preg_match('#^https?://#i', $texto) ? $texto : 'https://'.$texto;
    }

    /** Un sitio es válido si normalizado es una URL http(s) con un dominio con punto. */
    public static function esValido(?string $texto): bool
    {
        $url = self::normalizar($texto);
        if ($url === null) {
            return true;
        }
        $host = parse_url($url, PHP_URL_HOST);

        return filter_var($url, FILTER_VALIDATE_URL) !== false && is_string($host) && str_contains($host, '.');
    }

    /** Propiedad de prefijo de URL como la pide Search Console: con esquema y barra final, sin ruta. */
    public static function urlSearchConsole(string $texto): string
    {
        $url = self::normalizar($texto);
        $host = parse_url((string) $url, PHP_URL_HOST);
        $esquema = parse_url((string) $url, PHP_URL_SCHEME) ?: 'https';

        return $esquema.'://'.$host.'/';
    }
}
