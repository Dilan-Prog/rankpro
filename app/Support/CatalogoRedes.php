<?php

namespace App\Support;

/**
 * Redes que se pueden elegir en Configuración > Redes sociales.
 *
 * Los iconos son SVG en línea (24x24, stroke currentColor, como el resto del sitio
 * público): el mismo que se elige en el panel es el que sale en el pie.
 *
 * `dominios` valida que el enlace sea de esa red (vacío = cualquiera). `perfil`
 * indica si va al sameAs del schema: un chat de WhatsApp no identifica a la empresa.
 */
class CatalogoRedes
{
    /** @return array<string, array{nombre: string, icon: string, dominios: list<string>, perfil: bool, ejemplo: string}> */
    public static function todas(): array
    {
        return [
            'facebook' => self::red('Facebook', '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>', ['facebook.com', 'fb.com'], 'https://www.facebook.com/tu-pagina'),
            'instagram' => self::red('Instagram', '<rect width="20" height="20" x="2" y="2" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><path d="M17.5 6.5h.01"></path>', ['instagram.com'], 'https://www.instagram.com/tu-cuenta'),
            'tiktok' => self::red('TikTok', '<path d="M21 7.917v4.034a9.948 9.948 0 0 1-5-1.951v4.5a6.5 6.5 0 1 1-8-6.326v4.326a2.5 2.5 0 1 0 4 2v-11.5h4.083a6.005 6.005 0 0 0 4.917 4.917z"></path>', ['tiktok.com'], 'https://www.tiktok.com/@tu-cuenta'),
            'linkedin' => self::red('LinkedIn', '<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6z"></path><rect width="4" height="12" x="2" y="9"></rect><circle cx="4" cy="4" r="2"></circle>', ['linkedin.com'], 'https://www.linkedin.com/company/tu-empresa'),
            'youtube' => self::red('YouTube', '<path d="M2.5 17a24.12 24.12 0 0 1 0-10 2 2 0 0 1 1.4-1.4 49.56 49.56 0 0 1 16.2 0A2 2 0 0 1 21.5 7a24.12 24.12 0 0 1 0 10 2 2 0 0 1-1.4 1.4 49.55 49.55 0 0 1-16.2 0A2 2 0 0 1 2.5 17"></path><path d="m10 15 5-3-5-3z"></path>', ['youtube.com', 'youtu.be'], 'https://www.youtube.com/@tu-canal'),
            'x' => self::red('X (Twitter)', '<path d="M4 4l11.733 16H20L8.267 4z"></path><path d="M4 20l6.768-6.768"></path><path d="M13.228 10.772 20 4"></path>', ['x.com', 'twitter.com'], 'https://x.com/tu-cuenta'),
            'threads' => self::red('Threads', '<path d="M19 7.5c-1.333-3-3.667-4.5-7-4.5-5 0-8 2.5-8 9s3.5 9 8 9 7-3 7-5-1-5-7-5c-2.5 0-3 1.25-3 2.5 0 1.5 1 2.5 2.5 2.5 2.5 0 3.5-1.5 3.5-5s-2-4-3-4-1.833.333-2.5 1"></path>', ['threads.net', 'threads.com'], 'https://www.threads.net/@tu-cuenta'),
            'pinterest' => self::red('Pinterest', '<circle cx="12" cy="12" r="9"></circle><path d="M8 20l4-9"></path><path d="M10.7 14c.437 1.263 1.43 2 2.55 2 2.071 0 3.75-1.554 3.75-4a5 5 0 1 0-9.7 1.7"></path>', ['pinterest.com', 'pinterest.com.mx', 'pin.it'], 'https://www.pinterest.com/tu-cuenta'),
            'github' => self::red('GitHub', '<path d="M9 19c-4.3 1.4-4.3-2.5-6-3m12 5v-3.5c0-1 .1-1.4-.5-2 2.8-.3 5.5-1.4 5.5-6a4.6 4.6 0 0 0-1.3-3.2 4.2 4.2 0 0 0-.1-3.2s-1.1-.3-3.5 1.3a12.3 12.3 0 0 0-6.2 0C6.5 2.8 5.4 3.1 5.4 3.1a4.2 4.2 0 0 0-.1 3.2A4.6 4.6 0 0 0 4 9.5c0 4.6 2.7 5.7 5.5 6-.6.6-.6 1.2-.5 2V21"></path>', ['github.com'], 'https://github.com/tu-cuenta'),
            'google' => self::red('Google (ficha)', '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle>', ['google.com', 'google.com.mx', 'goo.gl', 'g.page', 'g.co'], 'https://maps.app.goo.gl/tu-ficha'),
            'whatsapp' => self::red('WhatsApp', '<path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21"></path><path d="M9 10a.5.5 0 0 0 1 0V9a.5.5 0 0 0-1 0v1a5 5 0 0 0 5 5h1a.5.5 0 0 0 0-1h-1a.5.5 0 0 0 0 1"></path>', ['wa.me', 'whatsapp.com'], 'https://wa.me/525512345678', false),
            'telegram' => self::red('Telegram', '<path d="M15 10l-4 4 6 6 4-16-18 7 4 2 2 6 3-4"></path>', ['t.me', 'telegram.me'], 'https://t.me/tu-canal', false),
            'enlace' => self::red('Otro enlace', '<circle cx="12" cy="12" r="10"></circle><path d="M2 12h20"></path><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>', [], 'https://ejemplo.com', false),
        ];
    }

    /** ¿El host es de la red (exacto o subdominio)? */
    public static function admiteUrl(string $red, string $url): bool
    {
        $dominios = self::todas()[$red]['dominios'] ?? null;

        if ($dominios === null) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        foreach ($dominios as $dominio) {
            if ($host === $dominio || str_ends_with($host, '.'.$dominio)) {
                return true;
            }
        }

        return $dominios === [];
    }

    /** @return array{nombre: string, icon: string, dominios: list<string>, perfil: bool, ejemplo: string} */
    private static function red(string $nombre, string $icon, array $dominios, string $ejemplo, bool $perfil = true): array
    {
        return compact('nombre', 'icon', 'dominios', 'perfil', 'ejemplo');
    }
}
