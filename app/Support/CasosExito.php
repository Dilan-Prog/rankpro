<?php

namespace App\Support;

/**
 * Casos de éxito del sitio público: alimentan el listado (/casos-de-exito), la
 * página de detalle de cada caso y el teaser de las landings de servicio.
 * Fuente única, como Servicios.php, para que una cifra viva en un solo sitio.
 *
 * Criterio editorial, acordado antes de publicar (ver la nota de E-E-A-T en
 * pages/index.blade.php, donde se retiraron testimonios y cifras no
 * verificables):
 *
 *   - Se nombra y enlaza solo a los clientes con consentimiento (`nombre` y
 *     `url` no nulos). Sin consentimiento, el caso va por sector, sin nombre,
 *     sin logo y sin cita: en su lugar se explica cómo se verifican las cifras.
 *   - Toda cifra lleva `fuente`. Sin fuente no se publica.
 *   - Cada bloque narrativo (reto, solución, resultados, evolución) se imprime
 *     solo si tiene contenido REAL. La maqueta de diseño traía relleno
 *     verosímil —series mensuales, tácticas por servicio— que NO venía del
 *     cliente; nada de eso se copió aquí. Un bloque vacío se omite: es
 *     preferible un caso corto a un caso inventado.
 *   - Los porcentajes se expresan de forma que no parezcan inflados: "del 10 %
 *     al 50 %" antes que "+400 %".
 */
class CasosExito
{
    public const SERVICIOS = [
        'desarrollo' => 'Desarrollo web',
        'seo' => 'SEO orgánico',
        'ads' => 'Google Ads',
        'automatizacion' => 'Automatización',
    ];

    public const SECTORES = [
        'hotelero' => ['label' => 'Hotelero', 'tono' => 'teal'],
        'industrial' => ['label' => 'Industrial B2B', 'tono' => 'ink'],
        'mixto' => ['label' => 'Industrial y hotelero', 'tono' => 'brand'],
    ];

    public const FUENTES = [
        'gsc' => 'Google Search Console',
        'ads' => 'Google Ads',
        'cliente' => 'datos del cliente',
        // Lo que construyó RankPro y puede enseñar: módulos, tablas, flujos.
        'rankpro' => 'proyecto RankPro',
    ];

    /**
     * Forma de cada caso:
     *
     *   slug, nombre (?), url (?), iniciales (?), logo (?), sector, ubicacion (?),
     *   duracion (?), titulo, resumen, servicios[],
     *   kpis[3]: {valor, label, fuente},
     *   reto: {titulo, parrafos[]} | null,
     *   solucion: [servicio => bullets[] | null]   (null = "no formó parte"),
     *   resultados: {titulo, parrafo, puntos[{titulo, texto}]} | null,
     *   evolucion: [{titulo, subtitulo, fuente, serie[{label, valor}]}],
     *   cita: {texto, autor, cargo (?)} | null,
     *   cta: {titulo, texto}
     *
     * @return array<int, array<string, mixed>>
     */
    public static function todos(): array
    {
        return [
            // El caso con la cifra de negocio más sólida va primero: inversión y
            // retorno declarados por el cliente.
            [
                'slug' => 'industrial-b2b',
                'nombre' => null,
                'url' => null,
                'iniciales' => null,
                'sector' => 'industrial',
                'ubicacion' => null,
                'duracion' => '3 años',
                'titulo' => 'De cero presencia digital a la mayoría de clientes nuevos por Google',
                'resumen' => 'Comercializadora industrial B2B que empezó sin ningún cliente desde internet. Tres años de SEO y Google Ads con presupuesto creciente sujeto a resultados.',
                'servicios' => ['seo', 'ads'],
                'kpis' => [
                    ['valor' => '10x', 'label' => 'Retorno sobre la inversión', 'fuente' => 'cliente'],
                    ['valor' => '$3 M', 'label' => 'MXN generados con $300 mil invertidos', 'fuente' => 'cliente'],
                    ['valor' => '251 mil', 'label' => 'Impresiones y 5,520 clics acumulados', 'fuente' => 'gsc'],
                ],
                'reto' => [
                    'titulo' => 'Ningún cliente llegaba desde internet',
                    'parrafos' => [
                        'La empresa no tenía presencia digital: ni posiciones orgánicas, ni campañas, ni consultas que entraran por su sitio. En un sector donde el comprador investiga antes de pedir cotización, no existía.',
                    ],
                ],
                // Las tácticas concretas por servicio no vienen documentadas por el
                // cliente: se omiten hasta tenerlas. `null` = no formó parte.
                'solucion' => [
                    'seo' => [],
                    'ads' => [],
                    'desarrollo' => null,
                ],
                'resultados' => [
                    'titulo' => 'Hoy la mayoría de sus clientes nuevos llegan por Google',
                    'parrafo' => 'Con $300 mil pesos invertidos a lo largo de tres años, el canal digital generó más de $3 millones en negocio: un retorno de diez a uno. En búsqueda, el acumulado llegó a 251 mil impresiones y 5,520 clics, y la empresa sigue creciendo en clientes e ingresos recurrentes.',
                    'puntos' => [
                        ['titulo' => '+300 % en clientes potenciales', 'texto' => 'generados digitalmente respecto al arranque del proyecto.'],
                        ['titulo' => 'El canal cambió:', 'texto' => 'de vender solo por cartera a que Google sea la entrada principal.'],
                    ],
                ],
                'evolucion' => [],
                'cita' => null,
                'cta' => [
                    'titulo' => '¿Vendes B2B y no llegas por Google?',
                    'texto' => 'Te decimos en una llamada si tu sector tiene volumen de búsqueda suficiente para que el canal valga la pena.',
                ],
            ],
            [
                'slug' => 'hotel-fratelli',
                'nombre' => 'Hotel Fratelli',
                'url' => 'https://hotelfratelli.com.mx/hoteles-en-aguascalientes',
                'iniciales' => 'HF',
                // Se enlaza desde el servidor del cliente, sin copia local, por
                // decision del equipo. Si el hotel renombra el fichero, aqui se
                // cae al recuadro de iniciales (onerror en la vista).
                'logo' => 'https://hotelfratelli.com.mx/images/logotipo/hotel-fratelli-logo-blanco-color.png',
                'sector' => 'hotelero',
                'ubicacion' => 'Aguascalientes, México',
                'duracion' => null,
                'titulo' => 'De ocupar 1 de cada 10 habitaciones a ocupar 5',
                'resumen' => 'Hotel independiente en Aguascalientes que partía de una ocupación del 10 %. Digitalizamos su operación con un PMS y combinamos SEO con Google Ads.',
                'servicios' => ['desarrollo', 'seo', 'ads'],
                'kpis' => [
                    ['valor' => '10 % → 50 %', 'label' => 'Ocupación sostenida', 'fuente' => 'cliente'],
                    ['valor' => '1,330', 'label' => 'Impresiones en búsqueda', 'fuente' => 'gsc'],
                    ['valor' => '3.1 %', 'label' => 'CTR en búsqueda', 'fuente' => 'gsc'],
                ],
                'reto' => [
                    'titulo' => 'Un hotel con nueve de cada diez habitaciones vacías',
                    'parrafos' => [
                        'La ocupación promedio rondaba el 10 % y el hotel no tenía visibilidad digital: no aparecía en las búsquedas de hospedaje de la ciudad ni tenía campañas activas.',
                    ],
                ],
                'solucion' => [
                    'desarrollo' => [
                        'Digitalización de los datos del hotel con un PMS (sistema de gestión hotelera): reservas, habitaciones y huéspedes en un solo sistema.',
                        'Fichas de habitación por tipo, con precio visible, sobre el sitio del hotel.',
                    ],
                    'seo' => [
                        'Landing de hoteles en Aguascalientes y página propia para la demanda de la Feria de San Marcos, la mayor intención de búsqueda sin explotar.',
                        'Presencia en las funciones de IA generativa de Google: 64 impresiones en el periodo medido.',
                    ],
                    'ads' => [
                        'Estrategia de anuncios de pago en Google para atraer tráfico inmediato y calificado mientras el posicionamiento orgánico maduraba.',
                    ],
                ],
                'resultados' => [
                    'titulo' => 'Cinco de cada diez habitaciones, y se mantiene',
                    'parrafo' => 'La ocupación subió del 10 % al 50 % y se sostiene constante. En búsqueda orgánica, el hotel pasó de no aparecer a 1,330 impresiones con un CTR del 3.1 % en el periodo medido.',
                    'puntos' => [
                        ['titulo' => 'Ocupación estable en 50 %', 'texto' => 'y constante desde que se alcanzó.'],
                        ['titulo' => 'Operación digitalizada:', 'texto' => 'el PMS centraliza reservas y huéspedes; el sitio y los anuncios le traen la demanda.'],
                        ['titulo' => '64 impresiones en IA generativa:', 'texto' => 'el hotel ya aparece en las respuestas con IA de Google.'],
                    ],
                ],
                'evolucion' => [],
                'cita' => [
                    'texto' => 'Pasamos de ocupar 1 de cada 10 habitaciones a ocupar 5. El SEO y los Ads nos dieron la visibilidad que nunca habíamos tenido.',
                    'autor' => 'Hotel Fratelli',
                    'cargo' => null,
                ],
                'cta' => [
                    'titulo' => '¿Tu hotel depende de las OTAs?',
                    'texto' => 'Revisamos tu Search Console contigo y te decimos qué reservas podrías estar captando directo.',
                ],
            ],
            [
                'slug' => 'equiterm-industries',
                'nombre' => 'Equiterm Industries',
                'url' => 'https://equitermindustries.com.mx/',
                'iniciales' => 'EQ',
                'logo' => 'https://equitermindustries.com.mx/images/logo/equiterm-logo-blanco-color-3x.png',
                'sector' => 'mixto',
                'ubicacion' => null,
                'duracion' => null,
                'titulo' => 'Un sitio hecho a la medida que en tres meses multiplicó por nueve sus clics',
                'resumen' => 'Comercializadora de calderas, calentadores y tratamiento de agua. Sitio y panel a la medida, SEO preparado para la IA de Google y automatizaciones.',
                'servicios' => ['desarrollo', 'seo', 'ads', 'automatizacion'],
                'kpis' => [
                    ['valor' => '+790 %', 'label' => 'Clics en los últimos 3 meses', 'fuente' => 'gsc'],
                    ['valor' => '+1,156 %', 'label' => 'Impresiones en los últimos 3 meses', 'fuente' => 'gsc'],
                    ['valor' => '889', 'label' => 'Impresiones en funciones de IA generativa de Google', 'fuente' => 'gsc'],
                ],
                'reto' => [
                    'titulo' => 'Crecer en digital sin comprometer el presupuesto',
                    'parrafos' => [
                        'Empresa en expansión, con productos y servicios industriales y hoteleros, que quería empezar a recibir contactos desde internet con una inversión conservadora y avanzando de forma progresiva.',
                        'No bastaba con una página: necesitaba una plataforma que sostuviera la operación —cotizaciones, catálogo, servicios— y que además estuviera bien construida para que Google, y ahora la IA de Google, la entendieran.',
                    ],
                ],
                'solucion' => [
                    'desarrollo' => [
                        'Sitio y panel de administración a la medida: más de 30 módulos y más de 160 tablas, con ecommerce, servicios, ERP, administración y estadísticas.',
                        'Gestión de cotizaciones integrada, con folio, estado y vigencia por cliente.',
                        'Estructura de datos y marcado preparados para SEO y para los motores de respuesta con IA (AEO), y medición lista para campañas de Google Ads.',
                    ],
                    'seo' => [
                        'Páginas de servicio por línea de producto: calderas, calentadores y tratamiento de agua, incluido el mantenimiento como página propia.',
                        'Presencia en las funciones de IA generativa de Google: 889 impresiones en el periodo medido.',
                    ],
                    'ads' => [],
                    'automatizacion' => [
                        'Automatización de procesos administrativos con n8n, conectada al panel y al sitio.',
                        'Envíos automáticos de correos y mensajes: confirmaciones, seguimiento de cotizaciones y avisos internos.',
                        'Generación y envío de reportes sin intervención manual.',
                        'Orquestación de procesos digitales complejos, con varios pasos y sistemas encadenados.',
                    ],
                ],
                'resultados' => [
                    'titulo' => 'Crecimiento sostenido, con la curva hacia arriba',
                    'parrafo' => 'En los últimos tres meses los clics crecieron un 790 % y las impresiones un 1,156 %. Desde el inicio acumula 6,950 impresiones y 211 clics, con una curva ascendente clara en ambos, y ya aparece en las respuestas con IA de Google. Los contactos de empresas que antes no la encontraban llegan mes a mes.',
                    'puntos' => [
                        ['titulo' => '+30 módulos y +160 tablas', 'texto' => 'de plataforma propia sosteniendo la operación, no solo la web.'],
                        ['titulo' => '889 impresiones en IA generativa:', 'texto' => 'la estructura del sitio ya la entienden los motores de respuesta.'],
                        ['titulo' => 'Página de mantenimiento de calderas', 'texto' => 'entre las de mayor crecimiento en clics (+200 % en el periodo).'],
                    ],
                ],
                'evolucion' => [],
                'cita' => [
                    'texto' => 'Con una inversión controlada, empezamos a recibir contactos de empresas que antes no nos encontraban.',
                    'autor' => 'Equiterm Industries',
                    'cargo' => null,
                ],
                'cta' => [
                    'titulo' => '¿Necesitas más que una página web?',
                    'texto' => 'Si tu operación pide cotizaciones, catálogo y procesos, construimos la plataforma y la hacemos visible en Google y en su IA.',
                ],
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function porSlug(string $slug): ?array
    {
        foreach (self::todos() as $caso) {
            if ($caso['slug'] === $slug) {
                return $caso;
            }
        }

        return null;
    }

    /**
     * Otros casos distintos del dado, para el bloque "Otros casos" del detalle.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function otros(string $slug): array
    {
        return array_values(array_filter(self::todos(), fn ($c) => $c['slug'] !== $slug));
    }

    /**
     * Conteo por sector y por servicio para los filtros del listado. El diseño
     * pide que los contadores digan la verdad ("Desarrollo web 0") en vez de
     * ocultar los filtros vacíos.
     *
     * @return array{sectores: array<string, int>, servicios: array<string, int>}
     */
    public static function conteos(): array
    {
        $sectores = array_fill_keys(array_keys(self::SECTORES), 0);
        $servicios = array_fill_keys(array_keys(self::SERVICIOS), 0);

        foreach (self::todos() as $caso) {
            $sectores[$caso['sector']]++;
            foreach ($caso['servicios'] as $s) {
                $servicios[$s]++;
            }
        }

        return ['sectores' => $sectores, 'servicios' => $servicios];
    }

    /**
     * Sectores presentes, para el strip de las landings de servicio.
     *
     * @return array<int, array{label: string, tono: string}>
     */
    public static function sectores(): array
    {
        $claves = array_unique(array_column(self::todos(), 'sector'));

        return array_values(array_map(fn ($c) => self::SECTORES[$c], $claves));
    }
}
