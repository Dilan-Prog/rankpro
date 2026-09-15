<?php

namespace App\Support;

/**
 * Casos de éxito publicados en las landings de servicio.
 *
 * Fuente única, como Servicios.php: los mismos casos pueden ir en la página de
 * SEO y en la de Google Ads sin duplicar cifras que luego se desincronizan.
 *
 * Criterio editorial, acordado antes de publicar (ver la nota de E-E-A-T en
 * pages/index.blade.php, donde se retiraron testimonios y cifras no
 * verificables):
 *
 *   - Se nombra y enlaza solo a los clientes que han dado consentimiento
 *     (`url` no nulo). Sin consentimiento, el caso va por sector, sin nombre
 *     ni enlace.
 *   - Cada cifra tiene que poder sostenerse. Una métrica sin dato se deja en
 *     null y la vista la omite: no se inventa ni se rellena con estimaciones.
 *   - Los porcentajes se expresan de forma que un lector atento no los vea
 *     inflados: "del 10 % al 50 %" antes que "+400 %".
 */
class CasosExito
{
    public const SERVICIOS = [
        'sitio_web' => 'Sitio web desarrollado con RankPro',
        'seo' => 'SEO hecho con RankPro',
        'ads' => 'Ads hecho con RankPro',
    ];

    public const SECTORES = [
        'hotelero' => ['label' => 'Sector hotelero', 'tono' => 'teal'],
        'industrial' => ['label' => 'Sector industrial B2B', 'tono' => 'ink'],
        'mixto' => ['label' => 'Industrial y hotelero', 'tono' => 'brand'],
    ];

    /**
     * @return array<int, array{
     *   id: string, nombre: ?string, url: ?string, sector: string,
     *   titulo: string, servicios: array<int, string>,
     *   estrella: array{valor: string, label: string},
     *   metricas: array<int, array{valor: ?string, label: string}>,
     *   descripcion: string, cita: ?string
     * }>
     */
    public static function todos(): array
    {
        return [
            // El caso con la cifra de negocio más sólida va primero: inversión
            // declarada, ingresos declarados y el ROI que sale de dividirlos.
            [
                'id' => 'industrial-b2b',
                'nombre' => null,
                'url' => null,
                'sector' => 'industrial',
                'titulo' => 'De cero presencia digital a que Google sea su principal fuente de clientes',
                'servicios' => ['seo', 'ads'],
                'estrella' => ['valor' => '10x', 'label' => 'retorno sobre la inversión en tres años'],
                'metricas' => [
                    ['valor' => '$3 M MXN', 'label' => 'en ingresos generados con $300 mil invertidos'],
                    ['valor' => '251 mil', 'label' => 'impresiones en Google acumuladas'],
                    ['valor' => '5,520', 'label' => 'clics orgánicos acumulados'],
                    // Pendientes de dato: la vista omite las que quedan en null.
                    ['valor' => null, 'label' => 'de aumento en tráfico'],
                    ['valor' => null, 'label' => 'de aumento en ingresos'],
                ],
                'descripcion' => 'Comercializadora industrial que empezó sin ningún cliente desde internet. Hoy la mayoría de sus clientes nuevos llegan a través de Google, y sigue creciendo en ingresos recurrentes.',
                'cita' => 'Empezamos desde cero en digital. Hoy, la mayoría de nuestros clientes nuevos llegan a través de Google.',
            ],
            [
                'id' => 'hotel-fratelli',
                'nombre' => 'Hotel Fratelli',
                'url' => 'https://hotelfratelli.com.mx/',
                'sector' => 'hotelero',
                'titulo' => 'De ocupar 1 de cada 10 habitaciones a ocupar 5',
                'servicios' => ['seo', 'ads'],
                'estrella' => ['valor' => '10 % → 50 %', 'label' => 'de tasa de ocupación, sostenida'],
                'metricas' => [
                    ['valor' => '1,330', 'label' => 'impresiones en Google en el periodo'],
                    ['valor' => '3.1 %', 'label' => 'de CTR en resultados de búsqueda'],
                    ['valor' => null, 'label' => 'de aumento en tráfico'],
                    ['valor' => null, 'label' => 'de aumento en ingresos'],
                ],
                'descripcion' => 'Hotel en Aguascalientes que no tenía visibilidad digital. El posicionamiento y la publicidad le dieron una ocupación que se mantiene constante.',
                'cita' => 'Pasamos de ocupar 1 de cada 10 habitaciones a ocupar 5. El SEO y los Ads nos dieron la visibilidad que nunca habíamos tenido.',
            ],
            [
                'id' => 'equiterm',
                'nombre' => 'Equiterm Industries',
                'url' => 'https://equitermindustries.com.mx/',
                'sector' => 'mixto',
                'titulo' => 'Contactos de empresas que antes no lo encontraban, con inversión controlada',
                'servicios' => ['seo', 'ads'],
                'estrella' => ['valor' => 'mes a mes', 'label' => 'crecimiento en clientes potenciales'],
                'metricas' => [
                    ['valor' => '6,950', 'label' => 'impresiones en Google acumuladas'],
                    ['valor' => '211', 'label' => 'clics orgánicos, con curva ascendente'],
                    ['valor' => null, 'label' => 'de aumento en tráfico'],
                    ['valor' => null, 'label' => 'de aumento en ingresos'],
                ],
                'descripcion' => 'Comercializadora de productos industriales y hoteleros en expansión, adoptando el canal digital de forma progresiva y con presupuesto conservador.',
                'cita' => 'Con una inversión controlada, empezamos a recibir contactos de empresas que antes no nos encontraban.',
            ],
        ];
    }

    /**
     * Sectores presentes, para el strip bajo el hero. Se derivan de los casos
     * para que el strip nunca anuncie un sector del que no hay caso.
     *
     * @return array<int, array{label: string, tono: string}>
     */
    public static function sectores(): array
    {
        $claves = array_unique(array_column(self::todos(), 'sector'));

        return array_values(array_map(fn ($c) => self::SECTORES[$c], $claves));
    }
}
