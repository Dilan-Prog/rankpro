<?php

namespace App\Support;

/**
 * Landings comerciales hijas de /servicios/sem-google-ads/{slug}.
 *
 * Fuente única de ruta, sitemap, llms.txt y enlace del padre. Cada una necesita
 * un ángulo distinto al del servicio para no canibalizarlo: publicidad-digital
 * habla de elegir canal, repartir inversión y medir; la auditoría y el detalle
 * de Google Ads viven solo en la página padre. Sin tarifas: no se publican.
 */
class LandingsAds
{
    public const SERVICIO = 'sem-google-ads';

    /**
     * `actualizado` es el lastmod del sitemap. En una FAQ, `r` es idéntico en la
     * página y en el FAQPage. `guias` son slug => texto, solo publicadas.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function todos(): array
    {
        return [
            'publicidad-digital' => [
                'slug' => 'publicidad-digital',
                'nombre' => 'Publicidad digital',
                'enlace' => 'Agencia de publicidad y marketing digital',
                'actualizado' => '2026-11-23',
                'meta_title' => 'Agencia de Publicidad y Marketing Digital | RankPro',
                'meta_description' => 'Agencia de publicidad y marketing digital en México: estrategia, campañas en Google Ads, medición y reportes con costo por lead y ROAS.',
                'h1' => 'Agencia de publicidad y marketing digital en México',
                'intro' => 'Somos una agencia de publicidad y marketing digital enfocada en que cada peso invertido se pueda ligar a leads o ventas. Empezamos por Google Ads, donde la intención de compra es más clara, y medimos todo con costo por lead y ROAS.',
                'guias' => [
                    'cuanto-cuesta-google-ads' => '¿Cuánto cuesta Google Ads?',
                    'google-analytics-4' => 'Google Analytics 4',
                    'google-tag-manager' => 'Google Tag Manager',
                ],
                'faqs' => [
                    [
                        'p' => '¿Qué hace una agencia de publicidad digital?',
                        'r' => 'Planea y gestiona anuncios en plataformas digitales (como Google) para conseguir leads o ventas, mide los resultados y optimiza la inversión.',
                    ],
                    [
                        'p' => '¿Gestionan también redes sociales?',
                        'r' => 'Sí, en nuestro servicio de Social Media, con campañas pagadas en Meta, Instagram, TikTok o LinkedIn según el objetivo. Esta página se enfoca en publicidad en Google.',
                    ],
                    [
                        'p' => '¿Cuánto cuesta contratar una agencia de publicidad digital?',
                        'r' => 'Se compone de la inversión en medios (lo que pagas a las plataformas) y la gestión de la agencia. La tarifa se define tras la auditoría gratuita.',
                    ],
                    [
                        'p' => '¿Cómo sé si la publicidad funciona?',
                        'r' => 'Con reportes de costo por lead y ROAS, no solo impresiones o clics, y con seguimiento de conversiones configurado.',
                    ],
                    [
                        'p' => '¿Qué canal conviene primero?',
                        'r' => 'Si ya hay personas buscando lo que vendes, la búsqueda en Google. Si tu producto es nuevo o se descubre por imagen, las redes sociales. Cuando el presupuesto no alcanza para sostener ambos, es mejor empezar por uno y sumar el segundo con datos.',
                    ],
                    [
                        'p' => '¿Qué debo tener listo antes de invertir en publicidad?',
                        'r' => 'Una oferta clara, una página o un canal de contacto que funcione en el celular, alguien que responda los mensajes y una definición de qué cuenta como conversión.',
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function encontrar(string $slug): ?array
    {
        return self::todos()[$slug] ?? null;
    }
}
