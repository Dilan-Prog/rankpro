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
     * `guias` (opcional) cambia las guías del pie. Dentro de un punto, `servicio`
     * y `ancla` enlazan otra página de servicio.
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

            'guadalajara' => [
                'slug' => 'guadalajara',
                'afines' => ['puebla', 'monterrey'],
                'guias' => ['cuanto-cuesta-manejo-de-redes-sociales', 'community-manager', 'que-es-social-media-marketing'],
                'nombre' => 'Guadalajara',
                'enlace' => 'Manejo de redes sociales en Guadalajara',
                'actualizado' => '2026-11-23',
                'meta_title' => 'Manejo de Redes Sociales en Guadalajara | RankPro',
                'meta_description' => 'Manejo de redes sociales en Guadalajara para marcas de tecnología, creativas y de consumo: estrategia, contenido, video corto y community management.',
                'h1' => 'Manejo de redes sociales en Guadalajara',
                'area' => ['@type' => 'City', 'name' => 'Guadalajara'],
                'intro' => 'Manejo de redes sociales en Guadalajara para marcas que quieren destacar: estrategia, contenido con identidad propia, video corto y atención a la comunidad, con un ritmo que se pueda sostener.',
                'secciones' => [
                    [
                        'titulo' => 'Qué necesitan las marcas de Guadalajara en redes sociales',
                        'parrafos' => [
                            'Guadalajara tiene una escena creativa y emprendedora muy activa: hay muchas marcas con buena imagen compitiendo por la atención. Verse bien ya no distingue a nadie. Lo que ayuda es tener una voz reconocible, usar el video corto con intención y aparecer con constancia.',
                            'Un lanzamiento intenso que se apaga a las seis semanas deja un perfil a medias. Es mejor publicar menos y llegar al siguiente trimestre con ideas, material y gente que todavía contesta los mensajes.',
                        ],
                    ],
                    [
                        'titulo' => 'Para qué negocios de Guadalajara lo hacemos',
                        'lista' => [
                            'Startups y empresas de tecnología que buscan comunidad',
                            'Marcas de consumo, moda y diseño',
                            'Restaurantes, bares y negocios de experiencias',
                            'Eventos y espacios creativos',
                            'Tiendas en línea que necesitan contenido constante',
                        ],
                    ],
                    [
                        'titulo' => 'Qué hacemos cada mes por tu marca',
                        'puntos' => [
                            ['t' => 'Líneas de contenido', 'd' => 'Educativo, de producto, de prueba social y de marca, en la proporción que pide tu momento.'],
                            ['t' => 'Video vertical', 'd' => 'Guion corto, edición y subtítulos, porque mucha gente mira sin sonido.'],
                            ['t' => 'Diseño y textos', 'd' => 'Piezas con tu identidad y una invitación clara a responder, guardar o comprar.'],
                            ['t' => 'Conversación', 'd' => 'Comentarios y mensajes atendidos con tu tono, moderación y escucha de lo que se dice de ti.'],
                            ['t' => 'Campañas pagadas', 'd' => 'Para lanzamientos, tienda o eventos, solo si el alcance contratado las incluye.'],
                            ['t' => 'Lectura de resultados', 'd' => 'Alcance, interacciones y lo que pasó después: visitas, pedidos o reservas cuando se pueden rastrear.'],
                        ],
                    ],
                    [
                        'titulo' => 'Cómo se construye una identidad que se reconoce sin ver el logo',
                        'parrafos' => [
                            'Antes de grabar definimos cuatro o cinco decisiones que se repiten: cómo saluda la marca, qué encuadres usa, qué colores dominan, de qué temas opina y de cuáles no. Parecen detalles, pero son los que hacen que alguien identifique un video tuyo en el primer segundo.',
                            'También sirven para decir que no. Una tendencia se aprovecha cuando encaja con esas decisiones; si obliga a hablar como otra marca, se deja pasar.',
                        ],
                    ],
                    [
                        'titulo' => 'Ideas de contenido según tu giro',
                        'lista' => [
                            'Tecnología: cómo se resolvió un problema del producto, contado por quien lo programó',
                            'Moda y diseño: del boceto a la prenda terminada, con materiales y tallas explicados',
                            'Restaurantes: un platillo por video, con precio, horario y forma de reservar',
                            'Eventos: qué va a pasar, cómo llegar y qué se vivió, en tres momentos distintos',
                            'Tienda en línea: dudas reales sobre envíos, cambios y cuidado del producto',
                        ],
                    ],
                    [
                        'titulo' => 'Un calendario para la vida de la ciudad',
                        'parrafos' => [
                            'No es lo mismo hablarle a quien sale a cenar un jueves por la colonia Americana que a quien busca un regalo en Tlaquepaque o vive en Zapopan. Cuando tu negocio depende de la zona, el contenido la nombra y resuelve lo práctico: estacionamiento, horarios y días con más gente.',
                            'Las fechas fuertes se preparan con anticipación. Si participas en una feria, un festival o una temporada de ventas, las piezas se planean antes, para que esos días solo quede publicar, responder y grabar lo que ocurra.',
                        ],
                    ],
                    [
                        'titulo' => 'Del arranque al primer reporte',
                        'parrafos' => [
                            'Revisamos tu audiencia, a tu competencia y las cuentas que ya tienes. De ahí sale una propuesta de plataformas, temas y frecuencia que apruebas antes de producir. Cada calendario pasa por tu visto bueno y, al cierre del periodo, comentamos juntos qué se repite, qué se cambia y qué se abandona. Cuando hay anuncios, el reporte muestra cuánto costó cada resultado.',
                            'De tu lado hace falta una persona que valide, acceso a productos o espacios para grabar y aviso oportuno de promociones o cambios de precio.',
                        ],
                    ],
                    [
                        'titulo' => 'Lo que protegemos',
                        'puntos' => [
                            ['t' => 'Coherencia', 'd' => 'Todas las piezas parecen de la misma marca, aunque las haga más de una persona.'],
                            ['t' => 'Derechos', 'd' => 'Música, fotografías y rostros se usan con licencia o con permiso.'],
                            ['t' => 'Expectativas', 'd' => 'No prometemos videos virales; trabajamos para que los buenos no dependan de la suerte.'],
                            ['t' => 'Medición', 'd' => 'Enlaces etiquetados y eventos configurados, para saber qué ventas o contactos empezaron en redes. Más en', 'servicio' => 'analytics-data', 'ancla' => 'Google Analytics 4 y medición'],
                        ],
                    ],
                ],
                'faqs' => [
                    [
                        'p' => '¿Producen ustedes el contenido?',
                        'r' => 'Sí. Producimos las piezas (diseño, video corto y textos) y las adaptamos a cada plataforma; tú compartes la información de la marca.',
                    ],
                    [
                        'p' => '¿Sirve comprar seguidores o hacer sorteos?',
                        'r' => 'Comprar seguidores, no: aumenta el número, pero no construye una comunidad que compre. Un sorteo puede ayudar de forma puntual si el premio es tu propio producto; no sustituye a una estrategia.',
                    ],
                    [
                        'p' => '¿Se puede medir si las redes generan ventas?',
                        'r' => 'Sí, con seguimiento bien configurado y, en tiendas, con etiquetado de campañas. El alcance depende de tu sitio y tus herramientas.',
                    ],
                    [
                        'p' => '¿Trabajan de forma remota con marcas tapatías?',
                        'r' => 'Sí. Nos coordinamos por videollamada y compartes avances, material y aprobaciones en línea.',
                    ],
                    [
                        'p' => '¿Qué redes convienen a una marca en Guadalajara?',
                        'r' => 'Depende de tu público: Instagram y TikTok suelen tener sentido para consumo y experiencias, Facebook para negocios de zona y LinkedIn para tecnología. Es preferible cuidar dos que abrir todas.',
                    ],
                ],
                'cierre' => [
                    'titulo' => '¿Qué debería recordar la gente de tu marca?',
                    'texto' => 'Cuéntanos qué vendes y en qué punto están tus cuentas. Te respondemos con una propuesta de redes, líneas y frecuencia.',
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
