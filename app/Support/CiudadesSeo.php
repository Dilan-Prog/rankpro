<?php

namespace App\Support;

/**
 * Landings de SEO por ciudad: /servicios/seo-organico/{ciudad}.
 *
 * Fuente única: de aquí salen la ruta, el sitemap, llms.txt, el pie y los
 * enlaces de la landing padre. Una ciudad existe solo cuando está en todos(),
 * así que no hay forma de enlazar una que aún no se publica.
 *
 * El marco de la página es común, pero `contexto`, `frentes`, `revisiones` y
 * `faqs` se escriben para cada ciudad. Una landing que solo cambia el nombre es
 * contenido casi duplicado: no dar de alta una ciudad sin texto propio. Tampoco
 * se declara oficina, cliente ni cifra local que no esté confirmada.
 */
class CiudadesSeo
{
    /** Slug del servicio padre en el catálogo. */
    public const SERVICIO = 'seo-organico';

    /**
     * La clave es el slug de la URL. `actualizado` es el lastmod del sitemap:
     * subirlo a mano cuando cambie el contenido.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function todos(): array
    {
        return [
            'cdmx' => [
                'slug' => 'cdmx',
                'nombre' => 'CDMX',
                'nombre_largo' => 'Ciudad de México',
                'actualizado' => '2026-11-23',
                'meta_title' => 'Agencia SEO en CDMX: Posicionamiento Web | RankPro',
                'meta_description' => 'Agencia SEO en CDMX para empresas de la Ciudad de México. SEO técnico, de contenidos y local, medido en Search Console. Pide tu auditoría sin compromiso.',
                'h1' => 'Agencia SEO en CDMX: posiciona tu empresa en Google',
                'intro' => 'Ayudamos a empresas de la Ciudad de México a aparecer cuando sus clientes buscan lo que venden. Empezamos con una auditoría técnica, de contenido y de enlaces, y la medimos en Google Search Console.',
                'contexto' => [
                    'titulo' => 'Por qué el SEO en la Ciudad de México es distinto',
                    'texto' => 'La Ciudad de México es uno de los mercados más competidos del país en Google. Hay más empresas por categoría, más anuncios y más contenido, así que una página genérica rara vez entra a los primeros resultados. Tres cosas cambian el trabajo:',
                    'puntos' => [
                        ['t' => 'Competencia alta en términos generales', 'd' => 'Buscar «agencia», «abogados» o «clínica» en CDMX enfrenta a cientos de sitios. Conviene atacar combinaciones más específicas: servicio + alcaldía, servicio + colonia, servicio + tipo de cliente.'],
                        ['t' => 'Búsquedas locales muy marcadas', 'd' => 'Muchas personas buscan «cerca de mí» o por zona (Polanco, Roma, Condesa, Santa Fe, Coyoacán, Del Valle…). Un buen SEO local responde a esa intención.'],
                        ['t' => 'Móvil primero', 'd' => 'Muchas búsquedas se hacen desde el celular, a veces en movimiento. Por eso revisamos primero la velocidad y la experiencia en móvil.'],
                    ],
                ],
                'frentes' => [
                    ['t' => 'SEO técnico', 'd' => 'Rastreo, indexación, Core Web Vitals en móvil, redirecciones, enlaces internos, datos estructurados y sitemap.'],
                    ['t' => 'SEO de contenidos', 'd' => 'Arquitectura de contenido y páginas por servicio, zona e intención de búsqueda, sin que unas compitan con otras.'],
                    ['t' => 'SEO local', 'd' => 'Optimización de tu ficha de Google Business Profile, consistencia de nombre, dirección y teléfono, reseñas y páginas por zona. Aplica si atiendes clientes en una ubicación física o en áreas específicas de la ciudad.'],
                    ['t' => 'SEO de autoridad', 'd' => 'Perfil de enlaces frente a tus competidores directos y menciones de calidad, sin paquetes de enlaces.'],
                ],
                'pasos' => [
                    ['t' => 'Auditamos', 'd' => 'Revisamos tu sitio, tu ficha local y a tus tres competidores principales de la ciudad.'],
                    ['t' => 'Te mostramos', 'd' => 'Hallazgos priorizados por impacto, con lo que tu competencia hace mejor.'],
                    ['t' => 'Tú decides', 'd' => 'Ejecutar con nosotros o con tu equipo, sin compromiso.'],
                ],
                'modalidad' => 'Trabajamos de forma remota con reuniones por videollamada.',
                'revisiones' => [
                    ['sev' => 'critico', 't' => 'Canibalización entre páginas'],
                    ['sev' => 'critico', 't' => 'Core Web Vitals en móvil'],
                    ['sev' => 'critico', 't' => 'Ficha de Google Business Profile y consistencia NAP'],
                    ['sev' => 'critico', 't' => 'Datos estructurados ausentes'],
                    ['sev' => 'medio', 't' => 'Cobertura de intención comercial por servicio y zona'],
                    ['sev' => 'medio', 't' => 'Títulos y descripciones duplicados'],
                    ['sev' => 'medio', 't' => 'Perfil de enlaces frente al líder de tu categoría'],
                    ['sev' => 'ok', 't' => 'Estructura de URLs y sitemap'],
                ],
                // 'guia' y 'padre' añaden un enlace tras la respuesta; el texto de
                // 'r' es el que viaja al FAQPage y es idéntico al visible.
                'faqs' => [
                    [
                        'p' => '¿Cuánto tarda el SEO en dar resultados en la Ciudad de México?',
                        'r' => 'Normalmente se notan cambios entre el mes 3 y el 6. En categorías muy competidas de CDMX puede tardar más; por eso se empieza por términos específicos y se escala.',
                    ],
                    [
                        'p' => '¿Cuánto cuesta una agencia SEO en CDMX?',
                        'r' => 'Depende del tamaño del sitio, la competencia y el alcance. Con la auditoría te damos una propuesta concreta.',
                        'guia' => 'cuanto-cuesta-el-posicionamiento-seo',
                    ],
                    [
                        'p' => '¿Necesito oficina en CDMX para aparecer en búsquedas de la ciudad?',
                        'r' => 'Para el SEO local con mapa (Google Business Profile) sí se requiere una ubicación o un área de servicio válida. Para resultados orgánicos normales, no: se compite con contenido y autoridad.',
                        'guia' => 'seo-local',
                    ],
                    [
                        'p' => '¿Trabajan solo con empresas de CDMX?',
                        'r' => 'No. Atendemos empresas en todo México.',
                        'padre' => true,
                    ],
                    [
                        'p' => '¿Pueden garantizar la primera posición en Google?',
                        'r' => 'No. Google no vende posiciones. Garantizamos un diagnóstico claro, un plan priorizado y reportes con datos reales.',
                    ],
                    [
                        'p' => '¿Necesito rehacer mi sitio para hacer SEO?',
                        'r' => 'Normalmente no. En la auditoría revisamos si se resuelve con ajustes de contenido y configuración.',
                    ],
                ],
                'cierre' => 'Muchas pérdidas de tráfico en CDMX son de contenido y de técnica, no de presupuesto. La auditoría te muestra cuáles son las tuyas.',
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
        $ciudades = array_filter(self::todos(), static fn (array $c): bool => $c['slug'] !== $excepto);

        return array_values(array_map(static fn (array $c): array => [
            'label' => 'Agencia SEO en '.$c['nombre'],
            'url' => route('servicios.ciudad', $c['slug']),
        ], $ciudades));
    }
}
