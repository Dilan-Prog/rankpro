<?php

namespace App\Support;

/**
 * Catalogo de servicios de RankPro: fuente unica de verdad.
 *
 * Lo consumen el controlador de paginas (hub y detalle), el megamenu del navbar
 * y el footer. Al vivir en un solo sitio, un cambio de nombre, icono o resumen
 * se propaga a toda la navegacion sin quedar desincronizado.
 */
class Servicios
{
    /**
     * Precio base por tipo de proyecto web, MXN sin IVA. De aquí salen el
     * configurador, la tabla y la FAQ de precios de /servicios/desarrollo-web:
     * son un compromiso comercial y no pueden decir cosas distintas.
     */
    public const PRECIOS_WEB = [
        'landing' => 38000,
        'corporativo' => 78000,
        'ecommerce' => 145000,
        'plataforma' => 240000,
    ];

    /**
     * Landing sencilla, MXN sin IVA. Va aparte porque no está en el
     * configurador: su alcance se cotiza a mano.
     */
    public const PRECIO_LANDING_SENCILLA = 7000;

    /**
     * Catalogo completo. La clave del array es el slug de la URL.
     *
     * Cada icono es el interior de un <svg> de 24x24 con stroke currentColor,
     * igual que en resources/views/components/services.blade.php.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function todos(): array
    {
        return [
            'sem-google-ads' => [
                'slug' => 'sem-google-ads',
                'nombre' => 'SEM & Google Ads',
                'gradient' => 'gradient-1',
                'icon' => '<circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="6"></circle><circle cx="12" cy="12" r="2"></circle>',
                'tags' => ['Google Ads', 'Shopping', 'Display'],
                'resumen' => 'Campañas de búsqueda, display y shopping para maximizar tu ROI.',
                'meta_title' => 'Agencia de Google Ads en México | Campañas SEM | RankPro',
                'meta_description' => 'Agencia de Google Ads en México: auditoría gratis en 48 horas, campañas en búsqueda, Shopping, Display y YouTube, y reportes con costo por lead y ROAS.',
                'h1' => 'Agencia de Google Ads en México: deja de pagar clics, paga ventas',
                'schema_nombre' => 'Agencia de Google Ads en México',
                'schema_tipo' => 'Google Ads',
                // Guías que la landing enlaza (slug => texto): solo las publicadas.
                'guias' => [
                    'que-es-google-ads' => '¿Qué es Google Ads y cómo funciona?',
                    'como-hacer-una-campana-google-ads' => 'Cómo hacer una campaña en Google Ads paso a paso',
                    'cuanto-cuesta-google-ads' => '¿Cuánto cuesta Google Ads?',
                    'ejemplos-de-google-ads' => 'Ejemplos y formatos de anuncios en Google Ads',
                ],
                'intro' => 'Google Ads es el canal más rápido para poner tu oferta frente a alguien que ya está buscando lo que vendes. También es el más fácil de desperdiciar: basta una estructura de campañas mal armada, concordancias demasiado abiertas o una conversión mal configurada para quemar presupuesto durante meses sin darte cuenta. En RankPro trabajamos la publicidad de búsqueda como un sistema medible, no como un botón de "promocionar".',
                'secciones' => [
                    [
                        'titulo' => 'Cómo estructuramos una cuenta que sí se puede optimizar',
                        'parrafos' => [
                            'Antes de gastar el primer peso revisamos qué está pasando en tu negocio: qué productos o servicios dejan mejor margen, cuál es el ticket promedio, cuánto tiempo pasa entre el primer clic y el cierre, y qué zonas del país realmente puedes atender. Esa información define la arquitectura de la cuenta, porque una campaña que mezcla servicios con márgenes opuestos hace imposible decidir dónde subir o bajar la inversión.',
                            'A partir de ahí construimos la investigación de palabras clave separando intención comercial de intención informativa, y agrupamos los términos por lo que la persona espera encontrar al hacer clic. Cada grupo recibe anuncios y una página de destino que responden a esa intención concreta. En paralelo levantamos una lista de negativos amplia desde el día uno para no pagar por búsquedas de trabajo, tesis escolares, "gratis" o competidores que no te interesan.',
                            'La medición se define antes del lanzamiento, nunca después. Configuramos las conversiones en Google Ads y GA4, marcamos cuáles son primarias y cuáles secundarias, y cuando el negocio lo permite conectamos conversiones offline para que la plataforma optimice hacia ventas cerradas y no hacia formularios que nunca contestaron el teléfono.',
                        ],
                    ],
                    [
                        'titulo' => 'Optimización continua: qué tocamos y con qué criterio',
                        'parrafos' => [
                            'Una cuenta viva necesita mantenimiento semanal. Revisamos el informe de términos de búsqueda para seguir refinando negativos y detectar oportunidades nuevas, ajustamos pujas y presupuestos según el rendimiento por campaña y dispositivo, y rotamos creatividades para encontrar los ángulos de mensaje que mejor conectan con tu audiencia.',
                            'Trabajamos con estrategias de puja automática cuando hay suficiente volumen de conversiones para alimentarlas, y con control manual cuando el volumen todavía es bajo y el algoritmo aprendería con ruido. No cambiamos todo a la vez: los ajustes se hacen por bloques y se dejan correr el tiempo suficiente para que los datos signifiquen algo.',
                            'También cuidamos lo que pasa después del clic. Un anuncio excelente hacia una página lenta, confusa o sin un siguiente paso claro tira el presupuesto igual que una palabra clave mal elegida. Por eso revisamos la landing junto con la campaña y proponemos cambios concretos de mensaje, formulario y velocidad.',
                        ],
                    ],
                    [
                        'titulo' => 'Para quién es este servicio',
                        'parrafos' => [
                            'Google Ads funciona bien cuando existe demanda activa: alguien ya está buscando tu producto o servicio por nombre o por el problema que resuelve. Es especialmente útil para negocios locales con zona de servicio definida, comercio electrónico con catálogo estable, y empresas B2B con un ciclo de venta que arranca con una solicitud de cotización.',
                            'Es menos adecuado si tu categoría todavía no tiene búsquedas —producto muy nuevo o mercado por educar— o si el margen por venta no soporta el costo por clic de tu sector. En esos casos te lo decimos antes de firmar y proponemos otra ruta, normalmente contenido orgánico o redes sociales.',
                        ],
                    ],
                ],
                'entregables' => [
                    'Auditoría inicial de la cuenta o estructura desde cero según el caso.',
                    'Investigación de palabras clave por intención y estimación de volumen.',
                    'Campañas de búsqueda, Performance Max, Shopping, Display o YouTube según objetivo.',
                    'Redacción y pruebas de anuncios responsivos con varios ángulos de mensaje.',
                    'Configuración de conversiones en Google Ads y GA4, con Google Tag Manager.',
                    'Lista de palabras clave negativas construida y ampliada cada semana.',
                    'Optimización semanal de pujas, presupuestos, segmentación y creatividades.',
                    'Reporte mensual con inversión, conversiones, costo por conversión y siguientes pasos.',
                ],
                // El FAQPage sale de aquí, igual que lo visible. Antes la landing
                // pintaba otra lista y el schema declaraba preguntas que no se veían.
                'faqs' => [
                    [
                        'p' => '¿Qué hace una agencia de Google Ads?',
                        'r' => 'Diseña, lanza y optimiza tus campañas de publicidad en Google: estructura de cuenta, palabras clave, anuncios, pujas, seguimiento de conversiones y reportes. Su objetivo es que cada peso invertido se pueda ligar a leads o ventas.',
                    ],
                    [
                        'p' => '¿Qué incluye la auditoría gratuita?',
                        'r' => 'Un diagnóstico de desperdicio (términos, ubicaciones y horarios), revisión de estructura, pujas y anuncios, análisis de landing pages y seguimiento de conversiones, y una proyección con tu presupuesto. Se entrega en 48 horas, sin contrato ni tarjeta.',
                    ],
                    [
                        'p' => '¿Firmo algo para la auditoría?',
                        'r' => 'Nada. Nos das acceso de solo lectura a tu cuenta, la revisamos y te entregamos el diagnóstico en 48 horas. Si decides no trabajar con nosotros, el documento es tuyo.',
                    ],
                    [
                        'p' => '¿Trabajan con cuentas que ya tengo?',
                        'r' => 'Sí. Trabajamos sobre tu propia cuenta con acceso administrativo, o creamos una a nombre de tu empresa. La cuenta y su historial son tuyos.',
                    ],
                    [
                        'p' => 'Ya intenté Google Ads y no funcionó, ¿qué cambia?',
                        'r' => 'En la mayoría de las cuentas que auditamos el problema no es el canal: es estructura, términos de búsqueda sin filtrar y conversiones mal medidas. La auditoría te muestra exactamente dónde se está yendo el dinero, con capturas de tu propia cuenta.',
                    ],
                    [
                        'p' => '¿Qué incluye la fee mensual?',
                        'r' => 'Estrategia, creación y optimización de campañas, anuncios, seguimiento, reportes y juntas. La inversión publicitaria se paga directo a Google desde tu tarjeta.',
                    ],
                    [
                        'p' => '¿Cuánto presupuesto necesito para empezar con Google Ads?',
                        'r' => 'Depende del costo por clic de tu sector y de cuántas conversiones necesitas para tomar decisiones con datos. Antes de proponerte una cifra revisamos estimaciones de volumen y competencia de tus palabras clave; la idea es que el presupuesto alcance para generar suficientes conversiones al mes como para optimizar, no solo para estar presente. Como referencia, desde $30,000 MXN mensuales de inversión publicitaria obtenemos datos suficientes para optimizar; con menos podemos trabajar, pero el aprendizaje toma más tiempo. La inversión publicitaria se paga directamente a Google desde tu propia cuenta, y es independiente de nuestros honorarios de gestión.',
                    ],
                    [
                        'p' => '¿En cuánto tiempo veo resultados?',
                        'r' => 'Los primeros clics y conversiones pueden llegar en días, porque la búsqueda pagada no depende de posicionamiento orgánico. Lo que toma más tiempo es la estabilización: normalmente las primeras cuatro a ocho semanas son de aprendizaje, donde se acumulan datos, se refinan negativos y se ajustan pujas. A partir de ahí el costo por conversión suele volverse más predecible.',
                    ],
                    [
                        'p' => '¿La cuenta de Google Ads es mía o de la agencia?',
                        'r' => 'Es tuya. Trabajamos sobre tu propia cuenta con acceso administrativo, o creamos una a nombre de tu empresa. El historial, las conversiones y las audiencias te pertenecen y se quedan contigo si algún día decides trabajar con alguien más.',
                    ],
                    [
                        'p' => '¿Pueden garantizar un costo por conversión específico?',
                        'r' => 'No, y desconfía de quien lo garantice. El costo por conversión depende de la competencia en la subasta, la estacionalidad, tu oferta y la calidad de tu página de destino, factores que cambian con el mercado. Lo que sí hacemos es definir una meta realista con base en datos reales de tu cuenta y reportar con transparencia cada mes qué tan cerca estamos y por qué.',
                    ],
                ],
            ],

            'seo-organico' => [
                'slug' => 'seo-organico',
                'nombre' => 'SEO Orgánico',
                'gradient' => 'gradient-2',
                'icon' => '<circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path>',
                'tags' => ['On-Page', 'Off-Page', 'Técnico'],
                'resumen' => 'Estrategia de contenido, link building y SEO técnico para ganar posiciones en Google.',
                'meta_title' => 'Agencia SEO en México: Posicionamiento Orgánico | RankPro',
                'meta_description' => 'Agencia SEO en México: auditoría, SEO técnico, de contenidos y de autoridad. Servicio de posicionamiento SEO medido en Search Console. Pide tu auditoría.',
                'h1' => 'Agencia SEO en México que sabe qué te frena en Google',
                // Nombre y tipo del Service en el JSON-LD: el nombre comercial del
                // catálogo ("SEO Orgánico") no es como se busca este servicio.
                'schema_nombre' => 'Servicio de posicionamiento SEO',
                'schema_tipo' => 'SEO orgánico',
                // Guías que la landing enlaza, en este orden (slug => texto del
                // enlace). Solo se pintan las publicadas: el slug del artículo
                // debe coincidir con el de aquí para que el enlace aparezca.
                'guias' => [
                    'que-es-seo' => '¿Qué es SEO? Guía completa para empresas',
                    'como-hacer-posicionamiento-seo' => 'Cómo hacer posicionamiento SEO paso a paso',
                    'consultor-seo' => 'Consultor SEO en México: qué hace y cuándo contratarlo',
                    'seo-local' => 'SEO local: qué es y cómo posicionar un negocio local',
                    'herramientas-seo' => 'Las mejores herramientas SEO',
                    'cuanto-cuesta-el-posicionamiento-seo' => '¿Cuánto cuesta el posicionamiento SEO?',
                ],
                'intro' => 'El SEO no es una lista de trucos, es la suma de tres cosas que tienen que funcionar al mismo tiempo: que Google pueda rastrear e indexar tu sitio sin fricción, que tu contenido responda mejor que el de la competencia a lo que la gente realmente busca, y que otras páginas te consideren una referencia legítima. Cuando falta cualquiera de las tres, el resto rinde a medias.',
                'secciones' => [
                    [
                        'titulo' => 'SEO técnico: que Google pueda leerte',
                        'parrafos' => [
                            'Empezamos por una auditoría técnica completa. Revisamos indexación real en Search Console, estructura de URLs, etiquetas canónicas, redirecciones encadenadas o rotas, contenido duplicado, sitemap XML, robots.txt, y si el contenido depende de JavaScript para renderizarse. También evaluamos Core Web Vitals, porque la experiencia de carga afecta tanto al posicionamiento como a la conversión.',
                            'Un problema técnico frecuente en sitios mexicanos es la falta de arquitectura: toda la oferta comprimida en una sola página. Si vendes seis servicios distintos y solo tienes un home, Google no tiene a qué URL enviar a alguien que busca uno de esos servicios en particular. Crear páginas específicas, enlazadas entre sí con criterio, suele ser el cambio de mayor impacto y el primero que recomendamos.',
                        ],
                    ],
                    [
                        'titulo' => 'Contenido con intención de búsqueda',
                        'parrafos' => [
                            'Investigamos las palabras clave de tu sector clasificándolas por intención: informativa (alguien aprendiendo), comercial (alguien comparando) y transaccional (alguien listo para contratar). Cada tipo necesita un formato distinto. Intentar vender en una consulta puramente informativa suele fallar, igual que publicar una guía extensa donde el usuario solo quería un precio y un teléfono.',
                            'Con ese mapa construimos la arquitectura de contenido: páginas de servicio para lo transaccional, artículos y guías para lo informativo, y enlaces internos que conectan unas con otras para que la autoridad circule y el usuario avance en su decisión. Redactamos pensando primero en la persona que lee y después en la consulta, porque un texto escrito solo para el algoritmo se nota y no convierte.',
                            'También trabajamos los datos estructurados —Organization, Service, FAQPage, BreadcrumbList— para que Google entienda el contexto de cada página y pueda mostrar resultados enriquecidos cuando aplique.',
                        ],
                    ],
                    [
                        'titulo' => 'Autoridad y para quién tiene sentido invertir en SEO',
                        'parrafos' => [
                            'La autoridad se construye siendo citado por sitios que ya son relevantes en tu tema. Trabajamos menciones editoriales, directorios sectoriales legítimos, colaboraciones y contenido lo bastante útil como para que otros lo enlacen por decisión propia. No compramos enlaces masivos ni participamos en redes de blogs: es la vía rápida a una penalización y a perder el trabajo de meses.',
                            'El SEO tiene sentido si tu negocio piensa en horizontes de seis a doce meses y quiere reducir su dependencia de la publicidad pagada. Los primeros movimientos suelen verse entre el tercer y el sexto mes, y los resultados se acumulan: el contenido que publicas hoy sigue trayendo tráfico dentro de dos años. Si necesitas ventas la próxima semana, la respuesta correcta es Google Ads mientras el SEO madura en paralelo.',
                        ],
                    ],
                ],
                'entregables' => [
                    'Auditoría técnica completa con hallazgos priorizados por impacto y esfuerzo.',
                    'Investigación de palabras clave clasificada por intención de búsqueda.',
                    'Arquitectura de contenido y plan de páginas nuevas o a fusionar.',
                    'Optimización on-page: títulos, meta descripciones, encabezados y enlazado interno.',
                    'Implementación de datos estructurados en JSON-LD.',
                    'Corrección de Core Web Vitals junto con el equipo de desarrollo.',
                    'Redacción o edición de contenido orientado a intención real.',
                    'Estrategia de autoridad y menciones editoriales.',
                    'Reporte mensual con posiciones, clics e impresiones desde Search Console.',
                ],
                // El JSON-LD FAQPage sale de aquí mismo, así que siempre coincide
                // con el texto visible. 'guia' añade un enlace si está publicada.
                'faqs' => [
                    [
                        'p' => '¿Cuánto tarda el SEO en dar resultados?',
                        'r' => 'Los cambios suelen notarse entre el mes 3 y el 6. Depende de la competencia, la antigüedad del dominio y el estado inicial del sitio. Primero se corrige lo técnico, luego se publica contenido y después se mide el avance en Search Console.',
                    ],
                    [
                        'p' => '¿Pueden garantizar la primera posición en Google?',
                        'r' => 'No, y desconfía de quien lo haga: Google no vende posiciones y nadie controla sus resultados. Lo que sí garantizamos es el trabajo: un diagnóstico claro, un plan priorizado y reportes con datos reales de clics, impresiones y posiciones.',
                    ],
                    [
                        'p' => '¿Necesito rehacer mi sitio web para hacer SEO?',
                        'r' => 'Normalmente no. En la auditoría revisamos si los problemas se resuelven con ajustes de contenido y configuración. Solo recomendamos rehacer el sitio cuando la plataforma o la estructura impiden mejorar.',
                    ],
                    [
                        'p' => '¿Qué pasa si dejo de invertir en SEO?',
                        'r' => 'Lo que ya se construyó no desaparece de golpe, pero tu competencia sigue publicando y optimizando. Con el tiempo pierdes posiciones y el tráfico baja. Por eso el SEO funciona mejor como un proceso continuo.',
                    ],
                    [
                        'p' => '¿Cuánto cuesta una agencia SEO?',
                        'r' => 'Depende del tamaño del sitio, la competencia y el alcance. Por eso empezamos con la auditoría: con ella te damos una propuesta concreta.',
                        'guia' => 'cuanto-cuesta-el-posicionamiento-seo',
                    ],
                    [
                        'p' => '¿Trabajan con empresas fuera de la Ciudad de México?',
                        'r' => 'Sí. Atendemos empresas en todo México de forma remota.',
                    ],
                ],
            ],

            'desarrollo-web' => [
                'slug' => 'desarrollo-web',
                'nombre' => 'Desarrollo Web',
                'gradient' => 'gradient-3',
                'icon' => '<path d="m18 16 4-4-4-4"></path><path d="m6 8-4 4 4 4"></path><path d="m14.5 4-5 16"></path>',
                'tags' => ['React', 'Shopify', 'WordPress'],
                'resumen' => 'Sitios web y tiendas online de alto rendimiento con diseño único.',
                'meta_title' => 'Desarrollo Web y Diseño de Páginas Web en México | RankPro',
                'meta_description' => 'Diseño y desarrollo de páginas web, tiendas en línea y plataformas a la medida en México. Precio y cronograma al instante. Código en tu repositorio.',
                'h1' => 'Desarrollo web y diseño de páginas web a la medida en México',
                // Guías que la landing enlaza (slug => texto): solo las publicadas.
                // Las tres primeras acompañan a la tabla de precios.
                'guias' => [
                    'cuanto-cuesta-una-pagina-web' => '¿Cuánto cuesta una página web?',
                    'como-crear-una-pagina-web' => 'Cómo crear una página web paso a paso',
                    'como-hacer-una-tienda-en-linea' => 'Cómo hacer una tienda en línea',
                    'que-es-desarrollo-web' => '¿Qué es el desarrollo web?',
                    'ejemplos-de-paginas-web' => 'Ejemplos de diseño de páginas web',
                    'desarrollo-de-aplicaciones-web' => 'Desarrollo de aplicaciones web: qué son y cuándo conviene',
                ],
                'intro' => 'Un sitio web no es un folleto digital: es la infraestructura donde aterriza todo tu marketing. Si carga lento, no se entiende en celular o no deja claro qué hacer a continuación, cada peso invertido en anuncios y contenido rinde menos. Desarrollamos sitios pensando desde el primer boceto en tres cosas simultáneas: que la gente entienda, que Google pueda leerlo y que puedas medir lo que pasa dentro.',
                'secciones' => [
                    [
                        'titulo' => 'Del objetivo comercial a la arquitectura',
                        'parrafos' => [
                            'Antes de diseñar definimos qué acción queremos que ocurra en cada página: una cotización, una llamada, una compra, una descarga. Esa decisión determina la estructura de navegación, la jerarquía de la información y dónde va cada llamada a la acción. Un sitio sin objetivo definido termina siendo bonito y silencioso.',
                            'Después armamos la arquitectura de URLs pensando en posicionamiento: una página por servicio o categoría, rutas legibles, encabezados jerárquicos correctos y enlazado interno con sentido. Esto se decide en el desarrollo, no se parcha después; un sitio construido sin esta lógica obliga a rehacer trabajo cuando llega la estrategia de contenidos.',
                        ],
                    ],
                    [
                        'titulo' => 'Tecnología, rendimiento y accesibilidad',
                        'parrafos' => [
                            'Elegimos la plataforma según lo que el proyecto necesita y quién lo va a mantener. WordPress cuando el equipo publica contenido con frecuencia y necesita autonomía editorial; Shopify cuando el foco es comercio electrónico con operación estándar; desarrollo a medida —Laravel en el backend, componentes propios en el frontend— cuando hay lógica de negocio, integraciones o paneles internos que no encajan en una plantilla.',
                            'El rendimiento se cuida durante la construcción: imágenes en formatos modernos con dimensiones explícitas, CSS y JavaScript acotados, fuentes cargadas sin bloquear el renderizado, y reserva de espacio para evitar saltos de layout. También cubrimos accesibilidad básica: contraste suficiente, navegación por teclado, etiquetas en formularios y texto alternativo en imágenes, porque además de ser correcto mejora la experiencia de todos.',
                            'Entregamos con la medición ya instalada: Google Analytics 4, Google Tag Manager, Search Console y los eventos de conversión que importan, probados antes de salir a producción.',
                        ],
                    ],
                    [
                        'titulo' => 'Para quién es este servicio',
                        'parrafos' => [
                            'Es para empresas que van a invertir en marketing digital y necesitan una base que aguante: si vas a mandar tráfico pagado o a construir contenido, el sitio es el cuello de botella o el multiplicador. También para negocios con un sitio antiguo que ya no pueden editar, que carga mal en celular o que no permite crear páginas nuevas sin ayuda técnica.',
                            'Si tu sitio actual funciona razonablemente y el problema real es de contenido o de campañas, te lo decimos. Rehacer un sitio que no lo necesita es la forma más cara de posponer el problema verdadero.',
                        ],
                    ],
                ],
                'entregables' => [
                    'Definición de objetivos, arquitectura de información y mapa de URLs.',
                    'Diseño de interfaz responsivo, propio de tu marca y probado en móvil.',
                    'Desarrollo en WordPress, Shopify o a medida según el caso.',
                    'Optimización de rendimiento y Core Web Vitals desde la construcción.',
                    'SEO técnico de base: encabezados, metadatos, sitemap y datos estructurados.',
                    'Formularios funcionales con validación y notificación por correo.',
                    'Instalación y prueba de GA4, Google Tag Manager y Search Console.',
                    'Capacitación al equipo para administrar el contenido sin depender de nosotros.',
                ],
                // El FAQPage sale de aquí, igual que lo visible. Antes la landing
                // pintaba otra lista y el schema declaraba preguntas que no se veían.
                'faqs' => [
                    [
                        'p' => '¿Por qué a medida y no WordPress con plantilla?',
                        'r' => 'Si tu sitio es informativo y no vas a crecer, una plantilla puede bastar y te lo diremos. A medida gana cuando necesitas velocidad real, integraciones, procesos propios o un diseño que no se parezca al de todos: se paga solo en conversión y en no rehacerlo en 18 meses.',
                    ],
                    [
                        'p' => '¿De quién es el código?',
                        'r' => 'Tuyo. Trabajamos en tu repositorio de GitHub desde el primer commit, con acceso de propietario para ti. Sin licencias atadas a nosotros ni hosting cautivo.',
                    ],
                    [
                        'p' => '¿Qué pasa si el alcance cambia a medio proyecto?',
                        'r' => 'Cotizamos por fases. Los cambios dentro del alcance entran sin costo; lo nuevo se cotiza aparte y tú apruebas antes de que se programe una línea.',
                    ],
                    [
                        'p' => '¿Incluye contenido y fotos?',
                        'r' => 'Incluye estructura, redacción de la copy base y montaje. Fotografía y video se cotizan aparte o los aporta tu equipo.',
                    ],
                    [
                        'p' => '¿Y después del lanzamiento?',
                        'r' => '30 días de garantía sobre cualquier bug, más planes de mantenimiento mensuales opcionales con mejoras continuas y monitoreo de velocidad.',
                    ],
                    [
                        'p' => '¿Cuánto cuesta el desarrollo de una página web?',
                        'r' => sprintf(
                            'Depende del tipo de proyecto: una landing sencilla parte desde %s, una landing de campaña desde %s, un sitio corporativo desde %s, un e-commerce desde %s y una plataforma o SaaS desde %s MXN + IVA. En el configurador de esta página ves el rango y las semanas de entrega según lo que elijas; la landing sencilla se cotiza aparte. También se puede acordar un plan de pagos.',
                            ...array_map(static fn (int $n): string => '$'.number_format($n), [self::PRECIO_LANDING_SENCILLA, ...array_values(self::PRECIOS_WEB)]),
                        ),
                    ],
                    [
                        'p' => '¿Cuál es la diferencia entre diseño web y desarrollo web?',
                        'r' => 'El diseño web define cómo se ve y cómo se usa el sitio. El desarrollo web lo construye para que funcione: programación, integraciones, panel de administración, velocidad y seguridad. En un proyecto completo se hacen los dos.',
                    ],
                    [
                        'p' => '¿Trabajan con empresas fuera de mi ciudad?',
                        'r' => 'Sí. Trabajamos de forma remota con empresas de todo México, con demo desde la semana 2 y entregas semanales.',
                    ],
                ],
            ],

            'pagespeed-core-web-vitals' => [
                'slug' => 'pagespeed-core-web-vitals',
                'nombre' => 'PageSpeed & Core Web Vitals',
                'gradient' => 'gradient-4',
                'icon' => '<path d="m12 14 4-4"></path><path d="M3.34 19a10 10 0 1 1 17.32 0"></path>',
                'tags' => ['Performance', 'CWV', 'Lighthouse'],
                'resumen' => 'Optimización técnica de velocidad con mejoras medibles en Lighthouse y datos de campo.',
                'meta_title' => 'Optimización de PageSpeed y Core Web Vitals | RankPro',
                'meta_description' => 'Optimización de velocidad de carga web: diagnóstico y corrección de LCP, INP y CLS con datos de laboratorio y de campo. Mejoras reales en móvil.',
                'h1' => 'Optimización de velocidad y Core Web Vitals',
                // lastmod propio: la plantilla genérica la comparten varios servicios.
                'actualizado' => '2026-11-23',
                'cta' => 'Solicitar un diagnóstico de velocidad',
                'guias_titulo' => 'Antes de contratar, aprende a leer tus datos',
                // Solo se enlazan las publicadas (slug => texto).
                'guias' => [
                    'pagespeed-insights' => 'PageSpeed Insights: cómo leer tu reporte',
                    'como-medir-velocidad-de-carga' => 'Cómo medir la velocidad de carga de una página web',
                    'como-mejorar-la-velocidad-de-carga' => 'Cómo mejorar la velocidad de carga de tu sitio',
                ],
                // Umbrales verificados en web.dev/articles/vitals el 2026-11-23.
                'tabla' => [
                    'titulo' => 'Referencia rápida de Core Web Vitals',
                    'encabezados' => ['Métrica', 'Qué mide', 'Referencia «buena»'],
                    'filas' => [
                        ['LCP', 'Cuándo aparece el elemento principal', '≤ 2.5 s'],
                        ['INP', 'Qué tan rápido responde a una interacción', '≤ 200 ms'],
                        ['CLS', 'Cuánto se mueve el contenido al cargar', '≤ 0.1'],
                    ],
                    'nota' => 'Son los umbrales que Google considera buenos, medidos en el percentil 75 de las visitas reales. Sirven para juzgar la velocidad de carga de una página con los datos de campo, no con una sola prueba.',
                ],
                'relacionado' => [
                    'titulo' => 'Velocidad desde el desarrollo',
                    'texto' => 'Una página nueva puede nacer rápida. Si necesitas rehacer el sitio, conoce nuestro servicio, que mide Core Web Vitals antes de entregar:',
                    'servicio' => 'desarrollo-web',
                    'ancla' => 'desarrollo web',
                ],
                'intro' => 'Mejoramos la velocidad de carga de tu sitio web donde importa: en el celular de tus clientes, medida con datos reales y no solo con una calificación de laboratorio. La velocidad de un sitio se paga dos veces: en posicionamiento, porque la experiencia de página es una señal que Google considera, y en ventas, porque cada segundo de espera aumenta el abandono. Lo importante es que la velocidad no se mide con una sola calificación: se diagnostica con métricas concretas y se corrige atacando causas específicas.',
                'secciones' => [
                    [
                        'titulo' => 'Qué medimos realmente: LCP, INP y CLS',
                        'parrafos' => [
                            'Core Web Vitals se compone de tres métricas. LCP (Largest Contentful Paint) mide cuánto tarda en aparecer el elemento principal de la pantalla, normalmente la imagen o el titular del encabezado. INP (Interaction to Next Paint), que sustituyó a FID, mide qué tan rápido responde la página cuando alguien toca un botón o abre un menú. CLS (Cumulative Layout Shift) mide cuánto se mueve el contenido mientras carga, ese salto molesto que hace que toques el botón equivocado.',
                            'Trabajamos con dos fuentes de datos: laboratorio y campo. Lighthouse y PageSpeed Insights simulan una carga controlada y sirven para diagnosticar; el informe de experiencia de usuario en Chrome y Search Console muestran lo que le pasa a la gente real, con sus dispositivos y sus conexiones. Cuando ambas fuentes no coinciden, mandan los datos de campo: la calificación de laboratorio es una herramienta de trabajo, no el objetivo.',
                        ],
                    ],
                    [
                        'titulo' => 'Dónde suele estar el problema y cómo lo corregimos',
                        'parrafos' => [
                            'La causa más común de un LCP alto son las imágenes: pesadas, sin formatos modernos, sin dimensiones, cargadas de forma diferida cuando justamente son lo primero que se ve. Convertimos a WebP o AVIF, servimos tamaños adecuados por dispositivo, aplicamos precarga al recurso principal y quitamos lazy loading donde estorba.',
                            'El INP alto casi siempre viene de JavaScript: scripts de terceros, etiquetas de seguimiento duplicadas, plugins que cargan en todas las páginas aunque solo se usen en una. Auditamos qué se ejecuta y cuándo, diferimos lo que no es crítico, eliminamos lo que ya nadie usa y consolidamos el seguimiento a través de un solo contenedor de etiquetas.',
                            'El CLS se corrige reservando espacio: dimensiones explícitas en imágenes y videos, alturas definidas para banners y anuncios, y carga de fuentes que no provoque un cambio brusco de tipografía. Son cambios pequeños con efecto inmediato y visible.',
                            'También revisamos el servidor. Un tiempo de respuesta lento arrastra todas las métricas, y a veces la solución real no es tocar el frontend sino corregir consultas a base de datos, activar caché o cambiar un hosting saturado.',
                        ],
                    ],
                    [
                        'titulo' => 'Para quién es este servicio',
                        'parrafos' => [
                            'Es para sitios que ya reciben tráfico y lo están desperdiciando: comercio electrónico donde el abandono en móvil es alto, sitios con inversión publicitaria activa donde cada visita tiene un costo, y proyectos que reciben avisos de Search Console sobre URLs con Core Web Vitals deficientes.',
                            'Si tu sitio es nuevo y aún no recibe visitas, la optimización de velocidad rinde más como parte del desarrollo que como proyecto aparte. Y si la plataforma está tan comprometida que cada mejora requiere pelear con el tema o los plugins, te diremos honestamente si conviene optimizar o reconstruir.',
                        ],
                    ],
                ],
                'entregables' => [
                    'Diagnóstico con datos de laboratorio (Lighthouse) y de campo (CrUX y Search Console).',
                    'Informe de causas priorizadas por impacto en LCP, INP y CLS.',
                    'Optimización de imágenes: formatos modernos, tamaños y carga diferida correcta.',
                    'Reducción y diferimiento de JavaScript y CSS no críticos.',
                    'Auditoría de scripts de terceros y consolidación del seguimiento.',
                    'Corrección de desplazamientos de diseño reservando espacio.',
                    'Revisión de caché, compresión y tiempo de respuesta del servidor.',
                    'Medición antes y después, documentada y comparable.',
                ],
                'faqs' => [
                    [
                        'p' => '¿Es realista llegar a 90+ en PageSpeed Insights?',
                        'r' => 'En muchos sitios sí, sobre todo en desarrollos a medida donde controlamos todo el código. En sitios con plataformas cargadas de plugins o con scripts de terceros obligatorios —chats, píxeles, widgets de reseñas— el techo puede ser más bajo. Preferimos comprometernos con una mejora medible de las métricas de campo, que es lo que afecta a tus usuarios y a tu posicionamiento, antes que con un número de laboratorio.',
                    ],
                    [
                        'p' => '¿Optimizar la velocidad me va a subir de posición en Google?',
                        'r' => 'La experiencia de página es una señal real pero no la principal: la relevancia del contenido pesa más. La velocidad suele funcionar como desempate entre resultados de calidad parecida, y como multiplicador de conversión sobre el tráfico que ya tienes. Si el contenido no responde a la búsqueda, ningún puntaje de velocidad lo compensa.',
                    ],
                    [
                        'p' => '¿Cuánto tarda ver reflejadas las mejoras?',
                        'r' => 'En laboratorio, de inmediato al terminar los cambios. En los datos de campo de Search Console la ventana de medición es de veintiocho días, así que la mejora aparece completa varias semanas después de publicar las correcciones. Es normal y no significa que algo esté fallando.',
                    ],
                    [
                        'p' => '¿Qué es la velocidad de carga de un sitio web?',
                        'r' => 'Es el tiempo que tarda una página en mostrar su contenido y responder al usuario. Se mide con métricas como LCP, INP y CLS, que Google agrupa en Core Web Vitals.',
                    ],
                    [
                        'p' => '¿Cómo sé si mi sitio es lento?',
                        'r' => 'Revisa el informe de Core Web Vitals de Search Console y prueba tus páginas con PageSpeed Insights. Si ambas fuentes difieren, confía en los datos de campo (usuarios reales).',
                    ],
                    [
                        'p' => '¿Qué incluye el diagnóstico?',
                        'r' => 'Una revisión con datos de laboratorio y de campo para identificar qué retrasa tu sitio (imágenes, scripts, fuentes, servidor, diseño) y un plan de correcciones priorizado.',
                    ],
                ],
            ],

            'analytics-data' => [
                'slug' => 'analytics-data',
                'nombre' => 'Analytics & Data',
                'gradient' => 'gradient-5',
                'icon' => '<line x1="18" x2="18" y1="20" y2="10"></line><line x1="12" x2="12" y1="20" y2="4"></line><line x1="6" x2="6" y1="20" y2="14"></line>',
                'tags' => ['GA4', 'Looker Studio', 'GTM'],
                'resumen' => 'GA4, Tag Manager y dashboards para decisiones basadas en datos reales.',
                'meta_title' => 'Analítica Digital: GA4, GTM y Dashboards | RankPro',
                'meta_description' => 'Implementación de Google Analytics 4, Tag Manager y tableros en Looker Studio. Medición de conversiones confiable para decidir con datos reales.',
                'h1' => 'Analítica digital y medición de conversiones',
                'actualizado' => '2026-11-23',
                'schema_nombre' => 'Analítica digital y medición de conversiones',
                'schema_tipo' => 'Analítica digital',
                'cta' => 'Solicitar mi plan de medición',
                'guias_titulo' => 'Aprende antes de contratar',
                // Solo se enlazan las publicadas (slug => texto).
                'guias' => [
                    'google-analytics-4' => 'Google Analytics 4: qué cambió y cómo configurarlo',
                    'que-es-google-analytics' => '¿Qué es Google Analytics y para qué sirve?',
                    'google-tag-manager' => 'Google Tag Manager: qué es y cómo instalarlo',
                    'como-configurar-google-analytics' => 'Cómo instalar y configurar Google Analytics',
                    'dashboards-de-google-analytics' => 'Dashboards de Google Analytics',
                    'conectar-google-ads-con-google-analytics' => 'Cómo conectar Google Ads con Google Analytics',
                    'adobe-analytics-vs-google-analytics' => 'Adobe Analytics vs Google Analytics',
                ],
                'relacionado' => [
                    'titulo' => 'Medición y campañas, juntas',
                    'texto' => 'La analítica web rinde más cuando las campañas optimizan con esas mismas conversiones. Conoce nuestro servicio de',
                    'servicio' => 'sem-google-ads',
                    'ancla' => 'SEM y Google Ads',
                ],
                'intro' => 'Hacemos analítica web que sirve para decidir: un plan de medición, GA4 y Tag Manager bien configurados y reportes que tu equipo usa. La mayoría de las cuentas de analítica que auditamos miden mal. No porque falte la herramienta —casi todos tienen GA4 instalado— sino porque nadie definió qué era una conversión, los eventos se duplican, el tráfico interno nunca se filtró y los reportes se ven sin saber si los números significan algo. Medir bien no es instalar un script: es decidir qué preguntas necesitas responder y construir el sistema que las responde.',
                'secciones' => [
                    [
                        'titulo' => 'Del plan de medición a la implementación',
                        'parrafos' => [
                            'Arrancamos con un plan de medición: qué acciones importan en tu negocio, cuáles son macroconversiones (una compra, una cotización solicitada) y cuáles microconversiones (ver un precio, iniciar un formulario, dar clic al teléfono). Ese documento evita el error más común, que es medir todo y no poder interpretar nada.',
                            'La configuración de Google Analytics va por Google Tag Manager y la lleva un especialista en Google Analytics, para que el seguimiento sea mantenible sin tocar el código del sitio cada vez. Configuramos GA4 con eventos nombrados de forma consistente, marcamos las conversiones clave, filtramos el tráfico interno, activamos la vinculación con Google Ads y Search Console, y ajustamos la retención de datos.',
                            'Después viene la parte que casi nadie hace: verificar. Probamos cada evento en modo de depuración, confirmamos que no haya duplicados, revisamos que los formularios registren solo envíos exitosos y que los clics de WhatsApp o teléfono se cuenten una sola vez. Un dato incorrecto es peor que no tener dato, porque lleva a decisiones equivocadas con confianza.',
                        ],
                    ],
                    [
                        'titulo' => 'Reportes que se usan para decidir',
                        'parrafos' => [
                            'Construimos tableros en Looker Studio conectados a GA4, Google Ads y Search Console, pensados para responder preguntas concretas: de dónde viene el tráfico que sí convierte, qué páginas generan contactos, cuánto cuesta cada conversión por canal, cómo evoluciona la visibilidad orgánica. Un tablero con cincuenta métricas y sin jerarquía no es un reporte, es ruido con colores.',
                            'También documentamos el modelo de atribución que estás usando y sus límites, porque es la fuente más frecuente de confusión: cuando Google Ads, Meta y GA4 reportan cifras distintas del mismo mes, casi nunca es que uno mienta, sino que cada uno atribuye con reglas y ventanas diferentes. Explicarlo de forma clara evita discusiones estériles y decisiones apresuradas.',
                        ],
                    ],
                    [
                        'titulo' => 'Para quién es este servicio',
                        'parrafos' => [
                            'Es para negocios que ya invierten en marketing y no pueden responder con seguridad de dónde salieron sus clientes del mes pasado. También para empresas que migraron a GA4 con una configuración automática y arrastran una medición incompleta desde entonces.',
                            'Es una base más que un servicio aislado: sin medición confiable, optimizar campañas o SEO se vuelve una cuestión de intuición. Por eso normalmente es lo primero que revisamos al iniciar cualquier proyecto, aunque nos hayas contratado para otra cosa.',
                        ],
                    ],
                ],
                'entregables' => [
                    'Plan de medición con macro y microconversiones definidas.',
                    'Implementación o corrección de Google Analytics 4.',
                    'Contenedor de Google Tag Manager con etiquetas, activadores y variables documentados.',
                    'Configuración y validación de eventos de conversión sin duplicados.',
                    'Filtros de tráfico interno y ajuste de retención de datos.',
                    'Vinculación con Google Ads y Search Console.',
                    'Tableros en Looker Studio orientados a decisiones.',
                    'Documentación del setup para que tu equipo lo entienda y lo mantenga.',
                ],
                'faqs' => [
                    [
                        'p' => '¿Por qué Google Ads y GA4 me dan números diferentes?',
                        'r' => 'Porque atribuyen distinto. Google Ads cuenta la conversión en la fecha del clic y usa su propia ventana de atribución; GA4 la registra en la fecha en que ocurrió y aplica su modelo. Sumado a las diferencias por consentimiento de cookies y por dispositivos cruzados, una discrepancia moderada es esperada. Lo que sí revisamos es que la brecha sea razonable y estable, no que desaparezca.',
                    ],
                    [
                        'p' => '¿Se puede medir cumpliendo con la privacidad de los usuarios?',
                        'r' => 'Sí, y es lo correcto. Configuramos el modo de consentimiento, evitamos enviar datos personales identificables a las plataformas de analítica y ajustamos la retención al mínimo que necesita el negocio. En México aplica la legislación en materia de protección de datos personales, y tu aviso de privacidad debe reflejar qué herramientas usas y para qué.',
                    ],
                    [
                        'p' => '¿Puedo medir llamadas telefónicas y mensajes de WhatsApp?',
                        'r' => 'Sí. Los clics en el botón de llamada o de WhatsApp se miden como eventos, y con números de seguimiento dinámicos se puede llegar al detalle de llamadas efectivas. Es importante entender el límite: se mide el clic o la llamada, no si la conversación terminó en venta, salvo que conectemos tu CRM para cerrar el círculo.',
                    ],
                    [
                        'p' => '¿Los datos y las cuentas quedan a mi nombre?',
                        'r' => 'Sí. Las propiedades de GA4, el contenedor de Tag Manager y los tableros se crean o se mantienen en cuentas de tu empresa. Nosotros trabajamos con accesos, y todo el histórico permanece contigo.',
                    ],
                    [
                        'p' => '¿Qué es un plan de medición?',
                        'r' => 'Es un documento que define qué acciones importan en tu negocio (macroconversiones y microconversiones), cómo se nombran los eventos y qué reportes necesitas, antes de configurar nada.',
                    ],
                    [
                        'p' => '¿Qué hace un especialista en Google Analytics?',
                        'r' => 'Configura GA4 y Google Tag Manager, define y valida las conversiones, vincula Google Ads y Search Console, y arma reportes para decidir. Su trabajo incluye verificar que los datos sean confiables.',
                    ],
                    [
                        'p' => '¿Cuánto tarda la configuración de Google Analytics?',
                        'r' => 'Depende del número de sitios, formularios y eventos. Se estima tras revisar tu caso.',
                    ],
                ],
            ],

            'social-media' => [
                'slug' => 'social-media',
                'nombre' => 'Social Media',
                'gradient' => 'gradient-6',
                'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
                'tags' => ['Instagram', 'Facebook', 'LinkedIn'],
                'resumen' => 'Gestión profesional de redes: contenido, pauta y crecimiento de comunidad.',
                'meta_title' => 'Gestión de Redes Sociales y Pauta Digital | RankPro',
                'meta_description' => 'Estrategia de contenido, producción, community management y campañas pagadas en Meta, Instagram, TikTok y LinkedIn para marcas en México.',
                'h1' => 'Gestión de redes sociales y pauta en Meta',
                'actualizado' => '2026-11-23',
                'schema_nombre' => 'Gestión de redes sociales y pauta en Meta',
                'schema_tipo' => 'Gestión de redes sociales',
                'cta' => 'Cotizar mi estrategia de redes',
                'incluye' => 'Un social media manager coordina el trabajo. El social media management abarca desde la estrategia hasta el reporte, no solo publicar.',
                'guias_titulo' => 'Antes de contratar, esto te conviene saber',
                // Solo se enlazan las publicadas (slug => texto).
                'guias' => [
                    'que-es-social-media-marketing' => '¿Qué es el social media marketing?',
                    'community-manager' => 'Community manager: qué hace y cuándo contratarlo',
                    'herramientas-para-community-manager' => 'Herramientas para community manager',
                    'cuanto-cuesta-manejo-de-redes-sociales' => '¿Cuánto cuesta el manejo de redes sociales?',
                    'social-media-ads' => 'Social media ads',
                ],
                // Landings por ciudad: texto => ruta. null = sin publicar, no se enlaza.
                'landings_titulo' => 'Redes sociales por ciudad',
                'landings' => [
                    'Agencia de redes sociales en Monterrey' => 'servicios/social-media/monterrey',
                    'Manejo de redes sociales en Guadalajara' => null,
                    'Community manager en Puebla' => null,
                ],
                'relacionado' => [
                    'titulo' => 'Si tu cliente ya te busca en Google',
                    'texto' => 'Las redes generan interés; la búsqueda captura a quien ya decidió. Conoce nuestro servicio de',
                    'servicio' => 'sem-google-ads',
                    'ancla' => 'SEM y Google Ads',
                ],
                'intro' => 'Somos una agencia de redes sociales en México: hacemos el manejo de redes sociales de tu marca con estrategia, contenido, community management y, si lo necesitas, pauta. Las redes sociales operan con una lógica distinta a la búsqueda: en Google alguien ya está buscando lo que vendes, en redes tú interrumpes para generar interés donde todavía no lo había. Eso cambia todo —el tipo de contenido, cómo se mide, cuánto tarda en convertir— y explica por qué copiar la estrategia de un canal al otro casi nunca funciona.',
                'secciones' => [
                    [
                        'titulo' => 'Estrategia antes que calendario',
                        'parrafos' => [
                            'Empezamos definiendo a quién le hablamos y para qué. Revisamos tu audiencia real, qué está publicando tu competencia y qué formatos rinden en tu categoría, y elegimos las plataformas que tienen sentido para tu negocio. Estar en todas las redes con contenido reciclado suele rendir menos que estar bien en dos.',
                            'Con eso construimos líneas de contenido —educativo, de producto, de prueba social, de marca— y un calendario editorial con frecuencia sostenible. Preferimos un ritmo que puedas mantener durante meses sobre un arranque intenso que se apaga a las seis semanas, porque la consistencia es lo que construye comunidad.',
                        ],
                    ],
                    [
                        'titulo' => 'Producción, comunidad y pauta',
                        'parrafos' => [
                            'Producimos las piezas: diseño gráfico alineado a tu identidad, edición de video vertical, redacción de textos con llamada a la acción clara y adaptación de cada pieza al formato de cada plataforma. El video corto domina el alcance orgánico hoy, así que ahí concentramos el esfuerzo cuando la marca lo permite.',
                            'La gestión de comunidad va más allá de responder comentarios: es un canal de venta y de servicio. Definimos tiempos de respuesta, tono de marca y respuestas a las preguntas frecuentes, y escalamos hacia tu equipo lo que requiere atención humana especializada. Muchas ventas en México se cierran en mensajes directos y en WhatsApp, no en el sitio web, y la operación tiene que estar lista para eso.',
                            'En pauta trabajamos campañas en Meta, Instagram, TikTok o LinkedIn según el objetivo, con el píxel bien instalado y eventos verificados. Estructuramos por etapa —reconocimiento, consideración, conversión y remarketing— y probamos creatividades de forma sistemática, porque en redes el creativo pesa más que la segmentación.',
                        ],
                    ],
                    [
                        'titulo' => 'Para quién es este servicio',
                        'parrafos' => [
                            'Funciona bien para marcas de consumo con producto visual, negocios locales que dependen del reconocimiento en su zona, y empresas B2B que construyen autoridad en LinkedIn. También para negocios donde la decisión de compra es emocional o requiere descubrimiento, no una búsqueda deliberada.',
                            'Es menos eficiente si tu producto es puramente técnico, de compra reactiva o de nicho muy estrecho: ahí la búsqueda pagada y el SEO suelen dar mejor retorno. Y conviene decirlo claro: las redes construyen demanda a mediano plazo, no reemplazan un canal de captación inmediata.',
                        ],
                    ],
                ],
                'entregables' => [
                    'Estrategia de contenido con líneas editoriales y selección de plataformas.',
                    'Calendario mensual de publicaciones aprobado por ti antes de publicar.',
                    'Diseño gráfico y edición de video adaptados a cada formato.',
                    'Redacción de textos y llamadas a la acción con tono de marca definido.',
                    'Community management con tiempos de respuesta acordados.',
                    'Campañas pagadas en Meta, Instagram, TikTok o LinkedIn.',
                    'Instalación y verificación del píxel y los eventos de conversión.',
                    'Reporte mensual de alcance, interacción, crecimiento y resultados de pauta.',
                ],
                'faqs' => [
                    [
                        'p' => '¿Cuántas publicaciones al mes incluye el servicio?',
                        'r' => 'Depende del plan y del formato, porque un video editado no cuesta lo mismo que una gráfica. Definimos la frecuencia junto contigo buscando un ritmo sostenible: más vale publicar tres veces por semana durante todo el año que diario durante un mes y luego desaparecer.',
                    ],
                    [
                        'p' => '¿Comprar seguidores o hacer sorteos sirve para crecer?',
                        'r' => 'Comprar seguidores no, nunca: inflan el número y hunden el porcentaje de interacción, que es justo la señal que usan las plataformas para decidir a quién mostrar tu contenido. Los sorteos pueden servir puntualmente si el premio es tu propio producto y atrae a compradores reales; si regalas un teléfono atraes cazadores de premios que se van al día siguiente.',
                    ],
                    [
                        'p' => '¿Ustedes producen el contenido o yo tengo que enviarlo?',
                        'r' => 'Producimos diseño, edición y textos. Lo que sí necesitamos de tu parte es materia prima: fotografías reales del producto o del equipo, acceso a instalaciones para sesiones, y validación de información técnica o de precios. El contenido con material real rinde bastante mejor que el que depende solo de banco de imágenes.',
                    ],
                    [
                        'p' => '¿Puedo medir ventas que vienen de redes sociales?',
                        'r' => 'Sí, con el píxel y los eventos bien configurados, aunque con matices. Las restricciones de privacidad en iOS y el bloqueo de cookies hacen que la atribución en redes sea menos precisa que en búsqueda, y buena parte de la conversación termina en mensajes directos o WhatsApp, fuera del sitio. Por eso combinamos los datos de plataforma con preguntas directas al cliente sobre cómo nos encontró.',
                    ],
                    [
                        'p' => '¿Qué hace un social media manager?',
                        'r' => 'Define la estrategia de redes, planea el contenido, coordina su producción, publica, atiende a la comunidad y mide resultados. A veces también gestiona la publicidad en redes.',
                    ],
                    [
                        'p' => '¿Qué incluye el manejo de redes sociales?',
                        'r' => 'Estrategia, calendario editorial, producción de piezas, community management, reportes y, si se contrata, pauta. El alcance exacto se define en la propuesta.',
                    ],
                    [
                        'p' => '¿Cuánto cuesta el manejo de redes sociales?',
                        'r' => 'Depende del número de redes, la frecuencia, el tipo de producción y si incluye pauta. Se cotiza tras entender tu caso.',
                        'guia' => 'cuanto-cuesta-manejo-de-redes-sociales',
                    ],
                ],
            ],
            'automatizacion-de-procesos' => [
                'slug' => 'automatizacion-de-procesos',
                'nombre' => 'Automatización de Procesos',
                'gradient' => 'gradient-7',
                'icon' => '<rect width="8" height="8" x="3" y="3" rx="2"></rect><path d="M7 11v4a2 2 0 0 0 2 2h4"></path><rect width="8" height="8" x="13" y="13" rx="2"></rect>',
                'tags' => ['n8n', 'CRM', 'Integraciones'],
                'resumen' => 'Flujos automáticos con n8n: leads al CRM, avisos al vendedor y reportes solos.',
                'meta_title' => 'Automatización de Procesos Empresariales con n8n | RankPro',
                'meta_description' => 'Automatización de procesos para empresas en México: leads al CRM, avisos al equipo y reportes automáticos con n8n. Diagnóstico gratis de 45 minutos.',
                'h1' => 'Automatización de procesos para empresas: recupera tu tiempo',
                'schema_nombre' => 'Automatización de procesos para empresas',
                'schema_tipo' => 'Automatización de procesos',
                // Guías que la landing enlaza (slug => texto): solo las publicadas.
                'guias' => [
                    'que-es-la-automatizacion-de-procesos' => '¿Qué es la automatización de procesos?',
                    'n8n-que-es' => 'n8n: qué es y casos de uso',
                    'chatbot-whatsapp-business' => 'Cómo crear un chatbot para WhatsApp',
                    'automatizacion-de-marketing' => 'Automatización de marketing',
                ],
                'intro' => 'La mayoría de las empresas que atendemos no tienen un problema de generación de demanda: tienen un problema de qué pasa después. Un formulario llega al correo de alguien que está en junta, un vendedor captura a mano el mismo dato en tres sistemas, y el reporte del lunes se arma copiando celdas. Automatizar no es comprar una herramienta más: es conectar lo que ya usas para que el trabajo repetitivo deje de consumir a gente capaz. Lo hacemos con n8n, que puede vivir en tu propia infraestructura, así que tus datos no salen de tu servidor y no pagas por cada ejecución.',
                'secciones' => [
                    [
                        'titulo' => 'Por qué el problema casi nunca es la falta de leads',
                        'parrafos' => [
                            'Cuando revisamos una operación comercial que "no vende", el cuello de botella rara vez está en el volumen de prospectos. Está en el tiempo que tarda alguien en enterarse de que llegó uno, en si ese aviso llegó a la persona correcta, y en si quedó registro de la conversación. En ventas por búsqueda, la ventaja de responder primero es enorme: el prospecto que llenó tu formulario también llenó el de dos competidores.',
                            'El segundo desperdicio es la captura manual. Copiar un nombre y un teléfono del correo al CRM, del CRM a la hoja de seguimiento y de ahí a facturación no aporta nada al cliente y consume horas de gente que debería estar vendiendo o produciendo. Peor: cada copia manual es una oportunidad de perder el origen de la campaña, que es justamente el dato que necesitas para saber qué publicidad funciona.',
                        ],
                    ],
                    [
                        'titulo' => 'Qué automatizamos primero',
                        'parrafos' => [
                            'Empezamos por el flujo de captación: formularios del sitio, mensajes de WhatsApp Business y formularios nativos de Meta y Google Ads entran a un solo proceso que valida el dato, lo enriquece y lo deja creado y asignado en tu CRM con su origen y campaña intactos. En paralelo sale la notificación al vendedor que corresponde, con el contexto y el siguiente paso.',
                            'Después vienen los procesos internos: sincronización entre sistemas que hoy nadie conversa entre sí, generación de documentos, y los reportes recurrentes que alguien arma a mano cada semana. La regla que seguimos es simple: automatizamos lo que es repetitivo y tiene reglas claras, y dejamos en manos de personas lo que exige criterio.',
                        ],
                    ],
                    [
                        'titulo' => 'Por qué n8n y no una plataforma cerrada',
                        'parrafos' => [
                            'n8n se puede alojar en tu propio servidor. Eso importa por dos razones: tus datos de clientes no salen de tu infraestructura, lo cual simplifica el cumplimiento de la LFPDPPP, y el costo no crece con cada ejecución como en las plataformas por consumo. Si tu operación pasa de 500 a 50,000 ejecuciones al mes, el costo de infraestructura sube poco.',
                            'También importa la propiedad: los flujos quedan en tu instancia, documentados y exportables. No quedas atado a nosotros ni a una licencia. Si algún día decides llevarlo con tu equipo interno o con otro proveedor, te llevas todo.',
                        ],
                    ],
                ],
                'entregables' => [
                    'Diagnóstico de procesos: mapeo de lo que hoy se hace a mano y dónde se pierde tiempo.',
                    'Diagrama del flujo propuesto, sistemas que conecta y ahorro estimado en horas.',
                    'Implementación en tu propia instancia de n8n, con los flujos documentados.',
                    'Integración con tu CRM, WhatsApp Business, Meta, Google Ads y sistemas con API.',
                    'Manejo de errores: reintentos, registro de cada ejecución y alertas si algo falla.',
                    'Capacitación al equipo y soporte para ajustes conforme crece la operación.',
                ],
                'faqs' => [
                    [
                        'p' => '¿Qué es n8n y por qué lo usan?',
                        'r' => 'Es una plataforma de automatización que conecta tus aplicaciones sin desarrollar todo desde cero. La diferencia con otras: puede vivir en tu propio servidor, así que tus datos no salen de tu infraestructura y no pagas por cada ejecución.',
                    ],
                    [
                        'p' => '¿Necesito personal técnico para operarlo?',
                        'r' => 'No. Entregamos los flujos funcionando, documentados y con un tablero simple para monitorearlos. Capacitamos a tu equipo en una sesión y quedamos como soporte.',
                    ],
                    [
                        'p' => '¿Se conecta con el sistema que ya uso?',
                        'r' => 'Casi siempre sí: HubSpot, Salesforce, Pipedrive, Zoho, Google Workspace, Meta, WhatsApp Business, Shopify, sistemas de facturación mexicanos y cualquier plataforma con API. Si tu sistema no tiene API, lo revisamos en el diagnóstico y te decimos con honestidad si es viable.',
                    ],
                    [
                        'p' => '¿Cuánto tarda la implementación?',
                        'r' => 'Un flujo de captación y asignación de leads suele estar operando en 2 a 3 semanas. Proyectos con varios sistemas y reglas de negocio complejas, entre 4 y 8 semanas.',
                    ],
                    [
                        'p' => '¿Qué pasa si un flujo falla?',
                        'r' => 'Cada flujo lleva reintentos, registro de errores y alertas. Si algo se rompe, lo sabemos antes que tú y queda respaldo de cada ejecución para no perder ningún lead.',
                    ],
                    [
                        'p' => '¿Cuánto cuesta automatizar un proceso?',
                        'r' => 'Depende del número de sistemas que se conectan y de la lógica del flujo. En el diagnóstico gratuito de 45 minutos se define el alcance y se cotiza.',
                    ],
                    [
                        'p' => '¿Qué procesos se pueden automatizar?',
                        'r' => 'Captación de leads, asignación en CRM, avisos al equipo, reportes, y tareas repetitivas entre hojas de cálculo, facturación y otras herramientas.',
                    ],
                    [
                        'p' => '¿Automatización de procesos es lo mismo que RPA?',
                        'r' => 'No exactamente. RPA suele imitar clics en pantallas; la automatización con n8n conecta aplicaciones por sus APIs, lo que suele ser más estable. Se elige según el caso.',
                    ],
                ],
            ],
        ];
    }

    /**
     * Version reducida para la navegacion: solo lo que el megamenu y el footer
     * necesitan pintar. Evita cargar secciones, FAQs y entregables en el layout.
     *
     * @return array<int, array{slug: string, nombre: string, resumen: string, icon: string, gradient: string, url: string}>
     */
    public static function navegacion(): array
    {
        return array_values(array_map(static fn (array $s): array => [
            'slug' => $s['slug'],
            'nombre' => $s['nombre'],
            'resumen' => $s['resumen'],
            'icon' => $s['icon'],
            'gradient' => $s['gradient'],
            'url' => route('servicios.show', $s['slug']),
        ], self::todos()));
    }
}
