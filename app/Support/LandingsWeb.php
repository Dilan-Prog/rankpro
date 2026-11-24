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
     * `afines` son los slugs que enlaza al pie: 2 o 3, no todas. Enlazarlas
     * todas sumaba una frase común por cada landing nueva. Las no publicadas se
     * omiten solas. `guias` son las del blog que enlaza, también si existen.
     *
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
                'afines' => ['monterrey', 'hoteles'],
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
                    'texto' => 'Demo navegable desde la semana 2, entregas semanales en un ambiente de pruebas y el código en un repositorio de GitHub a tu nombre desde el primer commit. El detalle de fases y plazos está en',
                    'enlace' => 'la página del servicio',
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

            'monterrey' => [
                'slug' => 'monterrey',
                'afines' => ['queretaro', 'guadalajara'],
                'nombre' => 'Monterrey',
                'enlace' => 'Páginas web en Monterrey',
                'actualizado' => '2026-11-23',
                'meta_title' => 'Páginas Web en Monterrey: Diseño y Desarrollo | RankPro',
                'meta_description' => "Diseño y desarrollo de páginas web en Monterrey para empresas industriales y de servicios. Catálogos, cotizadores e integraciones. Desde {$p['sencilla']} MXN.",
                'h1' => 'Páginas web en Monterrey: diseño y desarrollo a la medida',
                'schema_nombre' => 'Desarrollo web y diseño de páginas web en Monterrey',
                'area' => ['@type' => 'City', 'name' => 'Monterrey'],
                'intro' => 'Construimos páginas web en Monterrey y el área metropolitana para negocios que venden a otras empresas: catálogos técnicos, cotizadores, formularios que califican prospectos e integración con tus sistemas.',
                'precio' => "Un sitio corporativo B2B parte de {$p['corporativo']}; una plataforma para distribuidores, de {$p['plataforma']}, más IVA.",
                'necesidad' => [
                    'titulo' => 'Qué necesita una página web en Monterrey',
                    'texto' => 'Una empresa B2B en Monterrey suele vender con ciclos largos: el comprador compara fichas técnicas, pide cotización y quiere hablar con alguien. La página debe responder rápido a eso: catálogo claro, formularios de cotización que llegan a ventas y contenido que explique el producto.',
                ],
                'publico' => [
                    'titulo' => 'Diseño de páginas web en Monterrey: para qué empresas',
                    'puntos' => [
                        'Empresas industriales, de manufactura y proveedores B2B',
                        'Distribuidores con catálogos grandes de productos',
                        'Despachos y firmas que venden conocimiento especializado',
                        'Empresas de logística y construcción',
                        'Startups y empresas de tecnología',
                    ],
                ],
                'proyectos' => [
                    'titulo' => 'Desarrollo web en Monterrey para vender a otras empresas',
                    'puntos' => [
                        ['t' => 'Sitio corporativo B2B', 'd' => 'Servicios, sectores atendidos, casos y blog.'],
                        ['t' => 'Catálogo con filtros', 'd' => 'Fichas técnicas, descargas en PDF y solicitud de cotización por producto.'],
                        ['t' => 'Cotizador en línea', 'd' => 'Formulario guiado que envía datos al equipo comercial.'],
                        ['t' => 'Conexión con tu CRM', 'd' => 'Cada solicitud entra como oportunidad, con la herramienta que ya uses si ofrece API.'],
                        ['t' => 'Plataforma o portal', 'd' => 'Acceso para clientes o distribuidores, con permisos por rol.'],
                    ],
                ],
                'cuidados' => [
                    'titulo' => 'En qué ponemos atención cuando el comprador es técnico',
                    'puntos' => [
                        ['t' => 'Catálogo pensado para Google', 'd' => 'Una URL por producto y categoría, para que cada modelo pueda encontrarse.'],
                        ['t' => 'Sistemas que se hablan', 'd' => 'Antes de cotizar revisamos qué permite cada herramienta: CRM, correo o ERP.'],
                        ['t' => 'Prospectos calificados', 'd' => 'Formularios que piden lo necesario para cotizar y nada más.'],
                        ['t' => 'Información que resiste una auditoría', 'd' => 'Normas, capacidades y certificados publicados solo con respaldo tuyo.'],
                    ],
                ],
                'trabajo' => [
                    'titulo' => 'Del levantamiento al lanzamiento',
                    'texto' => 'Empezamos con ventas e ingeniería para ordenar productos y requisitos. Cada semana revisas avances en un entorno privado, y el repositorio queda a nombre de tu empresa. Consulta las fases en',
                    'enlace' => 'nuestro servicio de desarrollo',
                ],
                'casos' => [
                    'titulo' => 'Proyectos B2B que puedes revisar',
                    'texto' => 'Son ejemplos de cómo resolvemos este tipo de venta, sin atribuirles una ubicación en Nuevo León.',
                    'puntos' => [
                        ['slug' => 'equiterm-industries', 't' => 'Equiterm: plataforma y cotizaciones', 'd' => 'Sitio y panel a la medida con gestión de cotizaciones por folio, estado y vigencia. Sus clics crecieron 790 % en tres meses desde una base pequeña (Google Search Console).'],
                        ['slug' => 'industrial-b2b', 't' => 'Ecommerce industrial sin nombre público', 'd' => 'Catálogo estructurado por línea de producto. Google Ads devolvió 8.6 pesos de venta por cada peso entre enero y septiembre de 2026 (reporte de la cuenta y CRM).'],
                    ],
                ],
                'faqs' => [
                    [
                        'p' => '¿Pueden mostrar mi catálogo técnico completo?',
                        'r' => 'Sí. Se arma un catálogo con categorías, filtros y fichas descargables. El tamaño del catálogo influye en el alcance.',
                    ],
                    [
                        'p' => '¿Se puede conectar la página con mi CRM?',
                        'r' => 'En general sí, vía API o integraciones disponibles. Se confirma en la primera reunión según la herramienta que uses.',
                    ],
                    [
                        'p' => '¿El cotizador puede dar precios automáticos?',
                        'r' => 'Si tus reglas de precio son claras, puede mostrar un rango. Cuando dependen de ingeniería, conviene que reúna los datos y avise a un vendedor.',
                    ],
                    [
                        'p' => '¿Cuánto cuestan las páginas web en Monterrey?',
                        'r' => "Para una empresa B2B lo habitual es un sitio corporativo, desde {$p['corporativo']}, o una plataforma, desde {$p['plataforma']}. Si vendes en línea, el e-commerce inicia en {$p['ecommerce']}; una landing de campaña, en {$p['landing']}, y una sencilla, en {$p['sencilla']}. Son pesos mexicanos más IVA, con opción de plan de pagos.",
                    ],
                    [
                        'p' => '¿Atienden empresas fuera de Monterrey?',
                        'r' => 'Sí, en cualquier estado. Las reuniones son por videollamada y pueden sumarse tus áreas de compras, ventas o sistemas.',
                    ],
                ],
                'cierre' => [
                    'titulo' => 'Pongamos tu catálogo a trabajar',
                    'texto' => 'Elige tipo de sitio e integraciones en el configurador y obtén un rango antes de la primera reunión.',
                ],
            ],

            'puebla' => [
                'slug' => 'puebla',
                'afines' => ['cdmx', 'queretaro', 'cancun'],
                'nombre' => 'Puebla',
                'enlace' => 'Diseño de páginas web en Puebla',
                'actualizado' => '2026-11-23',
                'meta_title' => 'Diseño de Páginas Web en Puebla | RankPro',
                'meta_description' => "Diseño y desarrollo de páginas web en Puebla para pymes, escuelas y negocios locales. Proyectos por fases, con precio y fecha claros. Desde {$p['sencilla']} MXN.",
                'h1' => 'Diseño de páginas web en Puebla',
                'schema_nombre' => 'Desarrollo web y diseño de páginas web en Puebla',
                'area' => ['@type' => 'City', 'name' => 'Puebla'],
                'intro' => 'Diseño de páginas web en Puebla para negocios que quieren empezar bien y crecer por etapas: primero una página clara que genere contactos y, después, catálogo, reservas o tienda cuando el negocio lo pida.',
                'precio' => "Puedes arrancar con una landing sencilla desde {$p['sencilla']} y pasar a un sitio corporativo desde {$p['corporativo']}. Precios en MXN + IVA.",
                'necesidad' => [
                    'titulo' => 'Qué necesita una página web en Puebla',
                    'texto' => 'Si tu presupuesto es acotado, tiene sentido ver resultados antes de invertir más. Por eso proponemos avanzar por fases: una primera versión que sirva ya, con una estructura que permita agregar funciones sin rehacer el sitio.',
                ],
                'publico' => [
                    'titulo' => 'Negocios poblanos con los que tiene sentido empezar',
                    'puntos' => [
                        'Pymes y comercios que quieren vender o captar clientes en línea',
                        'Escuelas, universidades y centros de capacitación',
                        'Restaurantes, hoteles y negocios turísticos',
                        'Clínicas, consultorios y despachos',
                        'Empresas de manufactura y proveedores de la región',
                    ],
                ],
                'proyectos' => [
                    'titulo' => 'Desarrollo web en Puebla, etapa por etapa',
                    'puntos' => [
                        ['t' => 'Primero: una landing', 'd' => 'Para inscripciones, una promoción o un servicio concreto, con un solo formulario.'],
                        ['t' => 'Luego: el sitio completo', 'd' => 'Páginas por servicio, contacto, WhatsApp y blog.'],
                        ['t' => 'Cuando haya citas', 'd' => 'Reservas para clínicas, restaurantes y talleres.'],
                        ['t' => 'Cuando quieras vender en línea', 'd' => 'Tienda con productos, cobro y envíos.'],
                    ],
                ],
                'cuidados' => [
                    'titulo' => 'Qué hace que crecer no signifique rehacer',
                    'puntos' => [
                        ['t' => 'Hitos claros', 'd' => 'Cada pago va ligado a algo que ya puedes revisar.'],
                        ['t' => 'Edición sin programar', 'd' => 'Con el CMS, tu equipo actualiza avisos, precios y fotos por su cuenta.'],
                        ['t' => 'Ubicación fácil de encontrar', 'd' => 'Mapa y ficha de Google con los mismos datos que tu página.', 'guia' => 'seo-local'],
                        ['t' => 'Ligera en el celular', 'd' => 'Se revisa la carga con datos móviles antes de publicar cada etapa.'],
                    ],
                ],
                'trabajo' => [
                    'titulo' => 'Cómo se decide qué va en cada fase',
                    'texto' => 'Hacemos una lista de lo indispensable para abrir y otra de lo que puede esperar. Ves una demo desde la segunda semana y, al cerrar cada etapa, decides si sigues. El código es tuyo aunque te detengas ahí. Los plazos por tipo de proyecto se explican en',
                    'enlace' => 'desarrollo web a la medida',
                ],
                'casos' => [
                    'titulo' => 'Dos proyectos que avanzaron por pasos',
                    'texto' => 'Ninguno se presenta como cliente poblano; sirven para ver cómo se escala.',
                    'puntos' => [
                        ['slug' => 'equiterm-industries', 't' => 'Equiterm: inversión conservadora y progresiva', 'd' => 'Comenzó con cautela y hoy suma más de 30 módulos. En un trimestre, sus clics aumentaron 790 % partiendo de muy pocos, según Google Search Console.'],
                        ['slug' => 'hotel-fratelli', 't' => 'Hotel Fratelli, en Aguascalientes', 'd' => 'Digitalizó su operación y sumó SEO y anuncios; su ocupación fue de 10 % a 50 %, dato del hotel.'],
                    ],
                ],
                'faqs' => [
                    [
                        'p' => '¿Puedo empezar con una página sencilla y crecer después?',
                        'r' => 'Sí. Se diseña la estructura pensando en crecer: más páginas, catálogo o tienda se suman sin rehacer lo anterior.',
                    ],
                    [
                        'p' => '¿Puedo editar el contenido yo mismo?',
                        'r' => 'Con el módulo de CMS editable, tu equipo actualiza textos e imágenes sin programar.',
                    ],
                    [
                        'p' => '¿Cómo se paga el proyecto?',
                        'r' => 'Por hitos ligados a entregas; el configurador muestra el esquema. Si lo necesitas, podemos acordar un plan de pagos.',
                    ],
                    [
                        'p' => '¿Cuánto cuesta el diseño de una página web en Puebla?',
                        'r' => "Depende de la etapa: {$p['sencilla']} es el punto de partida de una landing sencilla, {$p['landing']} el de una de campaña, {$p['corporativo']} el de un sitio corporativo, {$p['ecommerce']} el de una tienda y {$p['plataforma']} el de una plataforma. A todos se suma el IVA.",
                    ],
                    [
                        'p' => 'Estoy en Puebla, ¿cómo serían las juntas?',
                        'r' => 'Por videollamada, con avances que puedes revisar cada semana desde tu celular o computadora.',
                    ],
                ],
                'cierre' => [
                    'titulo' => '¿Con qué etapa empezamos en Puebla?',
                    'texto' => 'Marca solo lo indispensable en el configurador y guarda lo demás para después.',
                ],
            ],

            'guadalajara' => [
                'slug' => 'guadalajara',
                'afines' => ['monterrey', 'puebla'],
                'guias' => ['desarrollo-de-aplicaciones-web'],
                'nombre' => 'Guadalajara',
                'enlace' => 'Diseño de páginas web en Guadalajara',
                'actualizado' => '2026-11-23',
                'meta_title' => 'Diseño de Páginas Web en Guadalajara | RankPro',
                'meta_description' => "Diseño y desarrollo de páginas web en Guadalajara para empresas de tecnología, servicios y comercio. MVP, e-commerce y plataformas. Desde {$p['sencilla']} MXN.",
                'h1' => 'Diseño de páginas web en Guadalajara',
                'schema_nombre' => 'Desarrollo web y diseño de páginas web en Guadalajara',
                'area' => ['@type' => 'City', 'name' => 'Guadalajara'],
                'intro' => 'Diseño de páginas web en Guadalajara, tiendas en línea y plataformas: desde una landing para probar una idea hasta un producto digital con cuentas de usuario, con entregas cada semana y código tuyo.',
                'precio' => "Validar una oferta cuesta desde {$p['landing']} con una landing de lanzamiento; un MVP con cuentas y panel, desde {$p['plataforma']} (pesos, más IVA).",
                'necesidad' => [
                    'titulo' => 'Qué necesita una página web en Guadalajara',
                    'texto' => 'Un proyecto digital nuevo casi nunca sale bien a la primera: necesita usuarios reales y ajustes. La mejor manera es publicar pronto lo esencial, con demo navegable desde la semana 2 y cambios dentro del alcance sin costo.',
                ],
                'publico' => [
                    'titulo' => 'Equipos tapatíos a los que les sirve',
                    'puntos' => [
                        'Startups y empresas de tecnología que necesitan lanzar un MVP',
                        'Agencias y estudios creativos que requieren un portafolio a medida',
                        'Marcas locales que venden en línea',
                        'Organizadores de eventos y negocios de servicios',
                        'Empresas que quieren pasar de procesos en Excel a una plataforma',
                    ],
                ],
                'proyectos' => [
                    'titulo' => 'Desarrollo web en Guadalajara: del experimento al producto',
                    'puntos' => [
                        ['t' => 'Landing de lanzamiento', 'd' => 'Para validar una oferta antes de construir lo demás.'],
                        ['t' => 'MVP o plataforma', 'd' => 'Registro de usuarios, panel y roles, con lo mínimo para aprender.'],
                        ['t' => 'Portafolio o sitio de marca', 'd' => 'Diseño propio, no plantilla, con proyectos que Google pueda leer.'],
                        ['t' => 'E-commerce', 'd' => 'Para marcas que ya venden por redes y quieren su propio canal.'],
                    ],
                ],
                'cuidados' => [
                    'titulo' => 'Código que otro equipo pueda continuar',
                    'puntos' => [
                        ['t' => 'Tecnología de mercado', 'd' => 'Sin depender de un proveedor: cualquier desarrollador puede tomarlo.'],
                        ['t' => 'Repositorio a tu nombre', 'd' => 'Con acceso de propietario desde el inicio.'],
                        ['t' => 'Retroalimentación continua', 'd' => 'Pruebas lo construido cada semana, no al final.'],
                        ['t' => 'Calidad antes de publicar', 'd' => 'Se revisan velocidad y accesibilidad, y se corrige lo que falle.'],
                    ],
                ],
                'trabajo' => [
                    'titulo' => 'Cómo pasamos de la idea a la primera versión',
                    'texto' => 'Definimos qué hipótesis quieres probar y recortamos todo lo que no ayude a responderla. Lo que aprendas con usuarios ordena la siguiente entrega. La duración depende del tipo de proyecto, que puedes ver en',
                    'enlace' => 'nuestro configurador y cronograma',
                ],
                'casos' => [
                    'titulo' => 'Software propio que ya está en operación',
                    'texto' => 'No los mostramos como clientes tapatíos, sino como evidencia de lo que sabemos construir.',
                    'puntos' => [
                        ['slug' => 'equiterm-industries', 't' => 'Equiterm: de sitio a plataforma', 'd' => 'Ecommerce, ERP, administración y estadísticas en un mismo desarrollo. Fuente: proyecto RankPro. En Search Console, +790 % de clics en tres meses, con un punto de partida bajo.'],
                        ['slug' => 'industrial-b2b', 't' => 'Ecommerce y automatización, cliente reservado', 'd' => 'Procesos conectados a la tienda. Entre enero y septiembre de 2026, su publicidad en Google retornó 8.6 veces lo invertido, según el reporte y el CRM.'],
                    ],
                ],
                'faqs' => [
                    [
                        'p' => '¿Pueden construir un MVP?',
                        'r' => "Sí. Un producto con cuentas, panel y roles entra en el tipo \"Plataforma / SaaS\", desde {$p['plataforma']} MXN + IVA.",
                    ],
                    [
                        'p' => '¿Quién es dueño del código?',
                        'r' => 'Tú. Vive en un repositorio de GitHub a tu nombre desde el primer commit.',
                    ],
                    [
                        'p' => '¿Se puede hacer por etapas?',
                        'r' => 'Sí. Una primera versión con lo esencial y funciones nuevas en fases posteriores.',
                    ],
                    [
                        'p' => '¿Cuánto cuesta diseñar una página web en Guadalajara?',
                        'r' => "Una presentación sencilla inicia en {$p['sencilla']}; una landing de campaña, en {$p['landing']}; un sitio de marca o corporativo, en {$p['corporativo']}, y una tienda, en {$p['ecommerce']}, siempre más IVA. Los productos con usuarios se cotizan como plataforma.",
                    ],
                    [
                        'p' => '¿Mi equipo técnico puede participar?',
                        'r' => 'Sí. Puede revisar el código, proponer cambios y quedarse con el mantenimiento. Nos coordinamos a distancia.',
                    ],
                ],
                'cierre' => [
                    'titulo' => '¿Qué quieres probar primero?',
                    'texto' => 'Arma la versión mínima en el configurador y te decimos si todavía se puede recortar.',
                ],
            ],

            // El cobro en línea es solo en pesos (confirmado por el dueño): la
            // página habla de mostrar dólares como referencia, no de cobrarlos.
            'tijuana' => [
                'slug' => 'tijuana',
                'afines' => ['cancun', 'monterrey'],
                'nombre' => 'Tijuana',
                'enlace' => 'Diseño de páginas web en Tijuana',
                'actualizado' => '2026-11-23',
                'meta_title' => 'Diseño de Páginas Web en Tijuana | RankPro',
                'meta_description' => "Diseño y desarrollo de páginas web en Tijuana para negocios que atienden a clientes de México y EE. UU. Sitios bilingües a la medida. Desde {$p['sencilla']} MXN.",
                'h1' => 'Diseño de páginas web en Tijuana',
                'schema_nombre' => 'Desarrollo web y diseño de páginas web en Tijuana',
                'area' => ['@type' => 'City', 'name' => 'Tijuana'],
                'intro' => 'Diseño de páginas web en Tijuana para negocios que atienden a ambos lados de la frontera: sitios bilingües, claros en precios y moneda, y pensados para convertir visitas en llamadas, citas o compras.',
                'precio' => "Sitio corporativo desde {$p['corporativo']} y landing de citas desde {$p['landing']}, en pesos mexicanos más IVA; la versión en inglés se agrega como extra.",
                'necesidad' => [
                    'titulo' => 'Qué necesita una página web en Tijuana',
                    'texto' => 'Un negocio en Tijuana a menudo habla con dos públicos: quien busca en español y quien busca en inglés, a veces desde EE. UU. El sitio debe presentarse bien en ambos idiomas y dejar claro en qué moneda se cotiza, cómo contactar y cómo llegar.',
                ],
                'publico' => [
                    'titulo' => 'Giros fronterizos para los que está pensado',
                    'puntos' => [
                        'Clínicas y servicios de salud que atienden pacientes de EE. UU.',
                        'Empresas de manufactura y maquila que necesitan presencia B2B',
                        'Comercios y servicios de barrio',
                        'Agencias de logística y comercio exterior',
                        'Restaurantes y turismo de fin de semana',
                    ],
                ],
                'proyectos' => [
                    'titulo' => 'Desarrollo web en Tijuana para dos públicos',
                    'puntos' => [
                        ['t' => 'Español e inglés separados', 'd' => 'Cada versión con su dirección y las etiquetas correctas para Google.'],
                        ['t' => 'Landing de citas o cotización', 'd' => 'Formulario corto y botón de llamada o WhatsApp.'],
                        ['t' => 'Tienda con precios claros', 'd' => 'Cobro en pesos y, si ayuda al comprador, el equivalente en dólares como referencia.'],
                        ['t' => 'Presencia B2B', 'd' => 'Sectores, capacidades y contacto para quien evalúa proveedores desde otro país.'],
                    ],
                ],
                'cuidados' => [
                    'titulo' => 'Detalles que pesan al cruzar la frontera',
                    'puntos' => [
                        ['t' => 'Inglés revisado por una persona', 'd' => 'Una mala traducción resta confianza, sobre todo en salud.'],
                        ['t' => 'Cómo llegar y cómo llamar', 'd' => 'Horario, dirección y teléfono con lada internacional a la vista y coherentes con tu ficha de Google.', 'guia' => 'seo-local'],
                        ['t' => 'Carga rápida con datos de roaming', 'd' => 'Imágenes ligeras y lo importante primero.'],
                        ['t' => 'Datos personales', 'd' => 'Pedimos solo lo necesario y publicamos el aviso de privacidad que valide tu asesor legal.'],
                    ],
                ],
                'trabajo' => [
                    'titulo' => 'Cómo se coordina un sitio en dos idiomas',
                    'texto' => 'Primero se aprueba el contenido en español y después se adapta al inglés, para no corregir dos veces. Compartimos un enlace de prueba con cada avance y conservas la propiedad del código. Revisa tiempos en',
                    'enlace' => 'el cronograma del servicio',
                ],
                'casos' => [
                    'titulo' => 'Trabajo publicado con fuente',
                    'texto' => 'Estos proyectos no se atribuyen a Tijuana: muestran cómo medimos.',
                    'puntos' => [
                        ['slug' => 'equiterm-industries', 't' => 'Equiterm, proveedor industrial y hotelero', 'd' => 'Sitio propio con cotizaciones integradas; los clics subieron 790 % en un trimestre, desde una cifra inicial baja. Medido en Google Search Console.'],
                        ['slug' => 'industrial-b2b', 't' => 'Proveedor B2B con identidad reservada', 'd' => 'Por cada peso en Google Ads vendió 8.6, de enero a septiembre de 2026. Datos del reporte de la cuenta y del CRM.'],
                    ],
                ],
                'faqs' => [
                    [
                        'p' => '¿Hacen sitios en español e inglés?',
                        'r' => 'Sí. Cada idioma tiene su URL y su texto, y se configura para que Google muestre la versión adecuada.',
                    ],
                    [
                        'p' => '¿Se puede mostrar precios en pesos y dólares?',
                        'r' => 'Sí, el sitio puede mostrar dólares como referencia. El cobro en línea se configura en pesos mexicanos.',
                    ],
                    [
                        'p' => '¿Un paciente o cliente de EE. UU. puede agendar desde la página?',
                        'r' => 'Sí, con formulario, llamada o un módulo de citas en su idioma. Conviene indicar la zona horaria de Tijuana en la confirmación.',
                    ],
                    [
                        'p' => '¿Cuánto cuesta una página web en Tijuana?',
                        'r' => "Va de {$p['sencilla']} por una landing sencilla a {$p['plataforma']} por una plataforma, como precio inicial más IVA. En medio están la landing de campaña ({$p['landing']}), el sitio corporativo ({$p['corporativo']}) y la tienda ({$p['ecommerce']}). Se puede pagar por plan.",
                    ],
                    [
                        'p' => '¿Atienden fuera de Tijuana?',
                        'r' => 'Sí, trabajamos a distancia en toda la república; nos reunimos por videollamada en el horario del noroeste.',
                    ],
                ],
                'cierre' => [
                    'titulo' => 'Que te entiendan de los dos lados',
                    'texto' => 'Activa «Multi-idioma» en el configurador y mira cuánto suma a tu proyecto.',
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
     * Enlaces a las landings publicadas: todas, o solo las afines a una.
     *
     * @return list<array{label: string, url: string}>
     */
    public static function enlaces(?string $afinesA = null): array
    {
        $landings = self::todos();

        if ($afinesA !== null) {
            $landings = array_intersect_key($landings, array_flip($landings[$afinesA]['afines']));
        }

        return array_values(array_map(static fn (array $l): array => [
            'label' => $l['enlace'],
            'url' => route('servicios.web', $l['slug']),
        ], $landings));
    }
}
