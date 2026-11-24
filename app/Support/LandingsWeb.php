<?php

namespace App\Support;

/**
 * Landings de desarrollo web por ciudad o sector: /servicios/desarrollo-web/{slug}.
 *
 * Fuente única de la ruta, el sitemap, llms.txt y los enlaces de la página
 * padre: una landing existe solo cuando está en todos(). Los precios salen de
 * Servicios::PRECIOS_WEB, nunca escritos a mano.
 *
 * Todo el texto se escribe para cada landing (ver CiudadesSeo: compartir
 * bloques dejó más de la mitad de las frases repetidas). No se declara oficina,
 * cliente ni cifra local sin confirmar, ni dónde está un cliente anonimizado.
 */
class LandingsWeb
{
    /** Slug del servicio padre en el catálogo. */
    public const SERVICIO = 'desarrollo-web';

    /**
     * `actualizado` es el lastmod del sitemap: subirlo a mano al cambiar texto.
     * En una FAQ, `r` es idéntico en la página y en el FAQPage.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function todos(): array
    {
        $p = array_map(
            static fn (int $n): string => '$'.number_format($n),
            Servicios::PRECIOS_WEB + ['sencilla' => Servicios::PRECIO_LANDING_SENCILLA],
        );

        return [
            'cancun' => [
                'slug' => 'cancun',
                'nombre' => 'Cancún',
                'enlace' => 'Diseño de páginas web en Cancún',
                'actualizado' => '2026-11-23',
                'meta_title' => 'Diseño de Páginas Web en Cancún | RankPro',
                'meta_description' => "Diseño y desarrollo de páginas web en Cancún para hoteles, tours y negocios turísticos. Sitios rápidos, bilingües y con reservas. Precio desde {$p['sencilla']}.",
                'h1' => 'Diseño de páginas web en Cancún',
                'schema_nombre' => 'Desarrollo web y diseño de páginas web en Cancún',
                'area' => ['@type' => 'City', 'name' => 'Cancún'],
                'intro' => 'Diseño de páginas web en Cancún y la Riviera Maya: desarrollamos sitios rápidos, en español e inglés, con reservas y pagos en línea, hechos para que el visitante pase de ver a reservar.',
                'precio' => "Landing desde {$p['sencilla']} MXN + IVA · landing de campaña desde {$p['landing']} · sitio corporativo desde {$p['corporativo']} · e-commerce desde {$p['ecommerce']}.",
                'necesidad' => [
                    'titulo' => 'Qué necesita una página web en Cancún',
                    'texto' => 'En Cancún muchos clientes buscan desde el celular, a veces desde otro país y en otro idioma. Una página que carga lento, no está traducida o no deja reservar se pierde en segundos. Por eso priorizamos velocidad en móvil, versión en inglés y un camino corto hasta la reserva o la cotización.',
                ],
                'publico' => [
                    'titulo' => 'Para qué empresas de Cancún lo hacemos',
                    'puntos' => [
                        'Hoteles, hostales y rentas vacacionales que quieren más reservas directas',
                        'Tours, traslados, clubes de playa y experiencias',
                        'Restaurantes y bares con carta, reservas y pedidos',
                        'Inmobiliarias, constructoras y servicios para residentes extranjeros',
                        'Servicios profesionales: salud, legal, eventos y bodas',
                    ],
                ],
                'proyectos' => [
                    'titulo' => 'Desarrollo de páginas web en Cancún: qué podemos construir',
                    'puntos' => [
                        ['t' => 'Sitio bilingüe (español/inglés)', 'd' => 'Estructura con una URL por idioma y etiquetas hreflang para que cada visitante vea su versión.'],
                        ['t' => 'Reservas y agenda', 'd' => 'Calendario de disponibilidad y confirmación automática.'],
                        ['t' => 'Pagos en línea', 'd' => 'Cobro con tarjeta en pesos mexicanos mediante una pasarela de pagos.'],
                        ['t' => 'Landing de campaña', 'd' => 'Para temporada alta, promociones o un tour específico.'],
                        ['t' => 'Tienda en línea', 'd' => 'Souvenirs, experiencias o paquetes.'],
                    ],
                ],
                'cuidados' => [
                    'titulo' => 'Lo que cuidamos en el desarrollo web en Cancún',
                    'puntos' => [
                        ['t' => 'Velocidad en móvil', 'd' => 'Es lo que más nota quien busca mientras viaja.'],
                        ['t' => 'Idiomas', 'd' => 'Contenido real en inglés, no solo traducción automática.'],
                        ['t' => 'Temporada alta', 'd' => 'Elegimos el hosting contigo según las visitas que esperas en los meses fuertes.'],
                        ['t' => 'SEO local', 'd' => 'Ficha de Google Business Profile coherente con el sitio.', 'guia' => 'seo-local'],
                    ],
                ],
                'trabajo' => [
                    'titulo' => 'Cómo trabajamos contigo',
                    'texto' => 'Demo navegable desde la semana 2, entregas semanales en un ambiente de pruebas y el código en un repositorio de GitHub a tu nombre desde el primer commit.',
                ],
                'casos' => [
                    'titulo' => 'Resultados que ya mostramos',
                    'texto' => 'Los citamos por el método; no los presentamos como clientes de Cancún.',
                    'puntos' => [
                        ['slug' => 'hotel-fratelli', 't' => 'Hotel Fratelli (Aguascalientes)', 'd' => 'Ocupación de 10 % a 50 % con un proyecto que sumó sistema de gestión, SEO y anuncios. Fuente: datos del cliente.'],
                        ['slug' => 'equiterm-industries', 't' => 'Equiterm Industries, sitio a la medida', 'd' => '+790 % en clics en tres meses; partía de una base pequeña. Fuente: Google Search Console.'],
                        ['slug' => 'industrial-b2b', 't' => 'Industrial B2B, cliente anonimizado', 'd' => '8.6x de retorno en Google Ads, de enero a septiembre de 2026. Fuente: reporte de Google Ads y CRM.'],
                    ],
                ],
                'faqs' => [
                    [
                        'p' => '¿Hacen páginas web bilingües para negocios turísticos?',
                        'r' => 'Sí. Se arma con una URL por idioma, textos revisados y las etiquetas necesarias para que Google muestre la versión correcta.',
                    ],
                    [
                        'p' => '¿Se pueden recibir reservas y pagos en la página?',
                        'r' => 'Sí, con un módulo de reservas y una pasarela de pagos. Se define en el alcance y se refleja en el rango del configurador.',
                    ],
                    [
                        'p' => '¿Necesito estar en Cancún para trabajar contigo?',
                        'r' => 'No. Trabajamos de forma remota con negocios de todo México.',
                    ],
                    [
                        'p' => '¿Cuánto cuesta una página web?',
                        'r' => "Una landing sencilla parte desde {$p['sencilla']}, una landing de campaña desde {$p['landing']}, un sitio corporativo desde {$p['corporativo']}, un e-commerce desde {$p['ecommerce']} y una plataforma desde {$p['plataforma']} MXN + IVA. En el configurador ves el rango y las semanas de entrega; la landing sencilla se cotiza aparte. También se puede acordar un plan de pagos.",
                    ],
                    [
                        'p' => '¿Trabajan de forma remota?',
                        'r' => 'Sí. Las juntas son por videollamada, con demo desde la semana 2 y entregas semanales. El código queda en un repositorio a tu nombre.',
                    ],
                ],
                'cierre' => [
                    'titulo' => '¿Hablamos de tu proyecto en Cancún?',
                    'texto' => 'Arma tu cotización y mira rango, semanas y cronograma al instante.',
                ],
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function encontrar(string $slug): ?array
    {
        return self::todos()[$slug] ?? null;
    }

    /**
     * Enlaces a las landings publicadas, sin la indicada.
     *
     * @return list<array{label: string, url: string}>
     */
    public static function enlaces(?string $excepto = null): array
    {
        $landings = array_filter(self::todos(), static fn (array $l): bool => $l['slug'] !== $excepto);

        return array_values(array_map(static fn (array $l): array => [
            'label' => $l['enlace'],
            'url' => route('servicios.web', $l['slug']),
        ], $landings));
    }
}
