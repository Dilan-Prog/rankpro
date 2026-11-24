<?php

namespace App\Support;

/**
 * Landings de redes sociales por ciudad: /servicios/social-media/{slug}.
 *
 * Fuente única de ruta, sitemap y llms.txt. La página padre las enlaza desde
 * `landings` en Servicios (texto => ruta). Texto único por ciudad, 750 palabras
 * visibles como mínimo y menos de 10 % de frases iguales (LandingsSocialTest).
 * Sin precios, clientes, oficinas ni resultados locales sin confirmar.
 */
class LandingsSocial
{
    public const SERVICIO = 'social-media';

    /**
     * `actualizado` es el lastmod del sitemap. En una FAQ, `r` es idéntico en la
     * página y en el FAQPage; `guia` añade un enlace aparte si está publicada.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function todos(): array
    {
        return [
            'monterrey' => [
                'slug' => 'monterrey',
                'afines' => ['guadalajara', 'puebla'],
                'nombre' => 'Monterrey',
                'enlace' => 'Agencia de redes sociales en Monterrey',
                'actualizado' => '2026-11-23',
                'meta_title' => 'Agencia de Redes Sociales en Monterrey | RankPro',
                'meta_description' => 'Agencia de redes sociales en Monterrey para marcas B2B y de servicios: estrategia, contenido, LinkedIn, community management y pauta. Cotiza tu proyecto.',
                'h1' => 'Agencia de redes sociales en Monterrey',
                'area' => ['@type' => 'City', 'name' => 'Monterrey'],
                'intro' => 'Somos una agencia de redes sociales en Monterrey y el área metropolitana, con enfoque en empresas que venden a otras empresas: contenido que explica lo que haces, presencia en LinkedIn y comunidad que genera confianza antes de la primera junta.',
                'secciones' => [
                    [
                        'titulo' => 'Qué necesitan las marcas de Monterrey en redes sociales',
                        'parrafos' => [
                            'En Monterrey muchas empresas venden por relaciones y recomendaciones, con ciclos largos de decisión. Las redes ayudan a que el comprador te conozca y te vea con criterio antes de contactarte: casos explicados, procesos, equipo y contenido útil.',
                            'Priorizamos plataformas donde está el comprador B2B, como LinkedIn, y usamos otras según tu audiencia. Un gerente de compras rara vez pide una cotización por una publicación, pero sí revisa tu perfil cuando alguien le pasa tu nombre. Si la última publicación es de hace un año, la recomendación pierde fuerza.',
                        ],
                    ],
                    [
                        'titulo' => 'Para qué negocios de Monterrey lo hacemos',
                        'lista' => [
                            'Empresas industriales y de manufactura que quieren mostrar capacidades',
                            'Proveedores y distribuidores B2B',
                            'Despachos y servicios profesionales',
                            'Empresas de tecnología y logística',
                            'Marcas que contratan talento y necesitan marca empleadora',
                        ],
                    ],
                    [
                        'titulo' => 'Qué incluye la gestión de redes sociales',
                        'puntos' => [
                            ['t' => 'Estrategia y plataformas', 'd' => 'Audiencia, competencia y redes con sentido para tu sector.'],
                            ['t' => 'Contenido B2B', 'd' => 'Casos, procesos, preguntas frecuentes de tus compradores y piezas que explican tu punto de vista.'],
                            ['t' => 'LinkedIn de empresa', 'd' => 'Página completa, con calendario propio y temas que le sirven a quien especifica, compra o dirige.'],
                            ['t' => 'Community management', 'd' => 'Respuestas y mensajes con tono profesional, y paso a ventas cuando hay interés real.'],
                            ['t' => 'Pauta', 'd' => 'Campañas pagadas segmentadas por puesto, industria o empresa, cuando la plataforma y el presupuesto lo permiten.'],
                            ['t' => 'Reportes', 'd' => 'Métricas ligadas a prospectos, no solo seguidores.'],
                        ],
                    ],
                    [
                        'titulo' => 'De qué se puede hablar cuando vendes algo técnico',
                        'parrafos' => [
                            'La duda más común de una empresa industrial es que «no hay nada que publicar». Casi siempre lo hay, pero está en la cabeza de ingeniería y de ventas. Estas son líneas que suelen funcionar como punto de partida:',
                        ],
                        'lista' => [
                            'La pregunta que todo cliente hace antes de pedir precio, respondida sin rodeos',
                            'Cómo se elige entre dos materiales, procesos o equipos, con sus límites',
                            'Qué revisa tu área de calidad y por qué',
                            'Quién está detrás: el equipo que atiende, instala o da servicio',
                            'Errores frecuentes al especificar y cómo evitarlos',
                        ],
                    ],
                    [
                        'titulo' => 'Redes que ayudan a ventas y a recursos humanos',
                        'parrafos' => [
                            'En el área metropolitana de Monterrey, de San Pedro Garza García a Apodaca, Escobedo o Santa Catarina, un mismo perfil lo miran dos públicos: quien puede comprarte y quien puede querer trabajar contigo. Conviene decidir qué proporción del calendario va a cada uno, porque un mensaje para atraer técnicos no es el mismo que convence a un director de operaciones.',
                            'Por eso acordamos desde el inicio qué cuenta como resultado. Para ventas, mensajes y solicitudes con datos completos; para talento, candidatos que llegan a la vacante. Los «me gusta» se observan, pero no deciden el plan.',
                        ],
                    ],
                    [
                        'titulo' => 'Cómo trabajamos',
                        'parrafos' => [
                            'Empezamos con una sesión con ventas para reunir objeciones y preguntas reales. Con eso definimos líneas de contenido y un calendario que tu equipo pueda sostener, producimos las piezas, atendemos la comunidad y medimos. Si hay pauta, reportamos el costo por resultado.',
                        ],
                    ],
                    [
                        'titulo' => 'Lo que cuidamos',
                        'puntos' => [
                            ['t' => 'Claridad', 'd' => 'Contenido que explica procesos y capacidades sin tecnicismos innecesarios.'],
                            ['t' => 'Permisos', 'd' => 'Autorización de tu empresa, y del cliente cuando aparece, antes de mostrar instalaciones, piezas o nombres.'],
                            ['t' => 'Ritmo', 'd' => 'Frecuencia sostenible para equipos con poco tiempo.'],
                            ['t' => 'Seguimiento', 'd' => 'Cada mensaje con interés comercial llega a la persona de ventas que debe contestarlo.'],
                        ],
                    ],
                    [
                        'titulo' => 'Cuándo no te recomendamos empezar por redes',
                        'parrafos' => [
                            'Si tu producto se compra de urgencia o hay poca gente que lo busca, suele rendir más aparecer en Google primero. Tampoco conviene arrancar si nadie en tu empresa puede dedicar un rato a validar lo técnico: publicar datos incorrectos frente a compradores expertos cuesta más que no publicar. Si ese es tu caso, te lo diremos antes de cotizar.',
                        ],
                    ],
                ],
                'faqs' => [
                    [
                        'p' => '¿Manejan LinkedIn para empresas?',
                        'r' => 'Sí, se evalúa según tu objetivo y audiencia B2B. Puede incluir la página de empresa, su contenido y campañas pagadas.',
                    ],
                    [
                        'p' => '¿Pueden mostrar mi planta o procesos?',
                        'r' => 'Sí, con la autorización de tu empresa y cuidando la información sensible.',
                    ],
                    [
                        'p' => '¿Cuánto cuesta una agencia de redes sociales en Monterrey?',
                        'r' => 'Depende del alcance: redes, frecuencia, producción y pauta. Se cotiza tras entender tu caso.',
                        'guia' => 'cuanto-cuesta-manejo-de-redes-sociales',
                    ],
                    [
                        'p' => '¿Trabajan de forma remota?',
                        'r' => 'Sí. Trabajamos con marcas de todo México por videollamada, con calendario editorial y reportes periódicos.',
                    ],
                    [
                        'p' => '¿Qué redes manejan?',
                        'r' => 'Según tu negocio: Instagram, Facebook, TikTok y LinkedIn, entre otras. Elegimos las que tienen sentido para tu audiencia; estar bien en dos rinde más que estar en todas.',
                    ],
                ],
                'cierre' => [
                    'titulo' => 'Que te conozcan antes de la primera junta',
                    'texto' => 'Cuéntanos a quién le vendes y cómo llegan hoy tus clientes. Te proponemos redes, temas y frecuencia.',
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
     * Landings publicadas afines a una: 2 o 3, no todas.
     *
     * @return list<array{label: string, url: string}>
     */
    public static function afines(string $slug): array
    {
        $todas = self::todos();

        return array_values(array_map(static fn (array $l): array => [
            'label' => $l['enlace'],
            'url' => route('servicios.social', $l['slug']),
        ], array_intersect_key($todas, array_flip($todas[$slug]['afines']))));
    }
}
