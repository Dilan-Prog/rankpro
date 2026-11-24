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
     * omiten solas. `seo_ciudad` enlaza la landing de SEO de la misma ciudad y
     * `servicio`, dentro de un punto, otra página de servicio. `guias` son las del blog que enlaza, también si existen.
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

            'cdmx' => [
                'slug' => 'cdmx',
                'afines' => ['puebla', 'queretaro'],
                'seo_ciudad' => 'cdmx',
                'nombre' => 'CDMX',
                'enlace' => 'Diseño de páginas web en CDMX',
                'actualizado' => '2026-11-23',
                'meta_title' => 'Diseño de Páginas Web en CDMX | RankPro',
                'meta_description' => "Diseño y desarrollo de páginas web en CDMX para empresas, startups y tiendas en línea. Sitios rápidos, medibles y a la medida. Desde {$p['sencilla']} MXN + IVA.",
                'h1' => 'Diseño de páginas web en CDMX',
                'schema_nombre' => 'Desarrollo web y diseño de páginas web en CDMX',
                'area' => ['@type' => 'City', 'name' => 'Ciudad de México'],
                'intro' => 'Diseño de páginas web en CDMX a la medida, rápidas y medibles. En una ciudad donde tanta oferta compite por la misma persona, te ayudamos a que tu empresa se distinga, se encuentre en Google y convierta.',
                'precio' => "Un rediseño corporativo arranca en {$p['corporativo']} y una tienda con catálogo propio en {$p['ecommerce']}, sin incluir IVA.",
                'necesidad' => [
                    'titulo' => 'Qué necesita una página web en CDMX',
                    'texto' => 'En CDMX, un sitio genérico se pierde entre muchos parecidos. Lo que diferencia es la claridad de la propuesta, la velocidad, la estructura para posicionar cada servicio por separado y la medición para decidir con datos.',
                ],
                'publico' => [
                    'titulo' => 'A quién le conviene en la capital',
                    'puntos' => [
                        'Consultoras y firmas de servicios profesionales',
                        'Empresas de tecnología que cambiaron de propuesta y su sitio no lo refleja',
                        'Tiendas en línea con catálogo propio',
                        'Empresas con varias líneas de servicio que necesitan ordenar su sitio',
                        'Organizaciones que requieren portales internos o de clientes',
                    ],
                ],
                'proyectos' => [
                    'titulo' => 'Desarrollo web en CDMX: sitio nuevo o rediseño',
                    'puntos' => [
                        ['t' => 'Rediseño del sitio actual', 'd' => 'Con inventario de URLs y plan de redirecciones para cuidar lo que ya posiciona.'],
                        ['t' => 'Sitio por líneas de servicio', 'd' => 'Una página para cada servicio, con blog y casos.'],
                        ['t' => 'Comercio electrónico', 'd' => 'Productos, cobro y logística conectados a tu operación.'],
                        ['t' => 'Portal interno o de clientes', 'd' => 'Acceso con contraseña, documentos y permisos por área.'],
                    ],
                ],
                'cuidados' => [
                    'titulo' => 'Lo que protegemos cuando cambias de sitio',
                    'puntos' => [
                        ['t' => 'Migración ordenada', 'd' => 'Cada dirección antigua con valor lleva a su equivalente con una redirección 301.'],
                        ['t' => 'Medición desde el primer día', 'd' => 'Search Console y GA4 listos antes de publicar, para comparar el antes y el después.'],
                        ['t' => 'Estructura para buscar por servicio', 'd' => 'El trabajo continúa, si lo quieres, con nuestro', 'servicio' => 'seo-organico', 'ancla' => 'servicio de SEO'],
                        ['t' => 'Accesible y veloz', 'd' => 'Lo comprobamos con mediciones previas a la entrega, sin prometer una calificación.'],
                    ],
                ],
                'trabajo' => [
                    'titulo' => 'Cómo se planea el cambio sin apagar lo que funciona',
                    'texto' => 'Revisamos qué páginas traen visitas y contactos hoy, construimos la nueva versión en paralelo y elegimos contigo el día del cambio. Después vigilamos errores y posiciones. Las fases completas están descritas en',
                    'enlace' => 'desarrollo de sitios web',
                ],
                'casos' => [
                    'titulo' => 'Sitios que puedes abrir y evaluar',
                    'texto' => 'No afirmamos que sean de la capital; valen como muestra de método y de medición.',
                    'puntos' => [
                        ['slug' => 'equiterm-industries', 't' => 'Equiterm Industries, páginas por línea', 'd' => 'Calderas, calentadores y tratamiento de agua con URL propia. Avance de 790 % en clics durante tres meses, con base inicial chica, en Search Console.'],
                        ['slug' => 'industrial-b2b', 't' => 'Ecommerce B2B con nombre protegido', 'd' => 'Su cuenta de Google Ads dio un retorno de 8.6 entre enero y septiembre de 2026; lo respaldan el reporte y el CRM.'],
                    ],
                ],
                'faqs' => [
                    [
                        'p' => '¿Hacen rediseño de una página que ya tengo?',
                        'r' => 'Sí. Se planea la migración con redirecciones para cuidar el posicionamiento que ya tienes. Toda migración puede mover posiciones un tiempo, por eso se vigila después de publicar.',
                    ],
                    [
                        'p' => '¿Pueden hacer la página y el SEO?',
                        'r' => 'Sí. El desarrollo prepara la estructura técnica, y el servicio de SEO la continúa con contenido y seguimiento.',
                    ],
                    [
                        'p' => '¿Qué necesito entregar para empezar un rediseño?',
                        'r' => 'Acceso de lectura a Search Console y a tu analítica, los textos que quieras conservar y quién aprueba los cambios.',
                    ],
                    [
                        'p' => '¿Cuánto cuesta una página web en CDMX?',
                        'r' => "El precio base es de {$p['corporativo']} para un sitio corporativo, {$p['ecommerce']} para una tienda y {$p['plataforma']} para un portal o plataforma. Si solo requieres una página, la sencilla comienza en {$p['sencilla']} y la de campaña en {$p['landing']}. Falta sumar IVA, y hay plan de pagos.",
                    ],
                    [
                        'p' => '¿Las reuniones son presenciales o en línea?',
                        'r' => 'En línea, por videollamada. Puedes invitar a todas las áreas que deban aprobar el sitio.',
                    ],
                ],
                'cierre' => [
                    'titulo' => '¿Tu sitio actual se queda corto?',
                    'texto' => 'Configura la versión que necesitas y compárala con lo que cuesta seguir igual.',
                ],
            ],

            'queretaro' => [
                'slug' => 'queretaro',
                'afines' => ['monterrey', 'puebla'],
                'seo_ciudad' => 'queretaro',
                'nombre' => 'Querétaro',
                'enlace' => 'Diseño de páginas web en Querétaro',
                'actualizado' => '2026-11-23',
                'meta_title' => 'Diseño de Páginas Web en Querétaro | RankPro',
                'meta_description' => "Diseño y desarrollo de páginas web en Querétaro para empresas industriales, logísticas y de servicios. Portales y catálogos a medida. Desde {$p['sencilla']} MXN.",
                'h1' => 'Diseño de páginas web en Querétaro',
                'schema_nombre' => 'Desarrollo web y diseño de páginas web en Querétaro',
                'area' => ['@type' => 'City', 'name' => 'Querétaro'],
                'intro' => 'Diseño de páginas web en Querétaro para empresas que venden a otras empresas y para negocios que crecen: catálogos de productos o servicios, portales para proveedores y clientes, y sitios que explican con claridad lo que haces.',
                'precio' => "Presenta tus capacidades en un sitio corporativo desde {$p['corporativo']}, o abre un portal con cuentas desde {$p['plataforma']}. Son montos en MXN, sin IVA.",
                'necesidad' => [
                    'titulo' => 'Qué necesita una página web en Querétaro',
                    'texto' => 'Cuando una empresa te evalúa como proveedor, pregunta por procesos, certificaciones y capacidad. Una página que lo explica bien y un formulario que califica al prospecto ahorran juntas y aceleran la cotización.',
                ],
                'publico' => [
                    'titulo' => 'Para qué empresas de Querétaro lo hacemos',
                    'puntos' => [
                        'Industria, sector aeroespacial y manufactura avanzada',
                        'Proveedores y operadores logísticos',
                        'Compañías de software y servicios especializados',
                        'Inmobiliarias y desarrolladores',
                        'Negocios locales que quieren vender en línea',
                    ],
                ],
                'proyectos' => [
                    'titulo' => 'Desarrollo web en Querétaro: proyectos que podemos construir',
                    'puntos' => [
                        ['t' => 'Sitio para ser evaluado', 'd' => 'Capacidades, industrias que atiendes, certificaciones y contacto, en el orden en que las revisa un comprador.'],
                        ['t' => 'Portal de proveedores o clientes', 'd' => 'Cada cuenta entra a sus documentos y solicitudes, según su rol.'],
                        ['t' => 'Catálogo con cotización', 'd' => 'El visitante elige un producto o servicio y envía la solicitud con sus datos.'],
                        ['t' => 'Venta directa', 'd' => 'Una tienda en línea para lo que no requiere cotización.'],
                    ],
                ],
                'cuidados' => [
                    'titulo' => 'Lo que revisa quien va a darte de alta',
                    'puntos' => [
                        ['t' => 'Evidencia vigente', 'd' => 'Las certificaciones se publican con su alcance y fecha, a partir de los documentos que nos entregues.'],
                        ['t' => 'Accesos bien delimitados', 'd' => 'En un portal, cada empresa ve solo su información.'],
                        ['t' => 'Conexión por API', 'd' => 'Si tu sistema la ofrece, se valora qué datos pueden intercambiarse antes de fijar el alcance.'],
                        ['t' => 'SEO técnico de origen', 'd' => 'Páginas separadas por proceso o servicio, para que aparezcan en búsquedas concretas.'],
                    ],
                ],
                'trabajo' => [
                    'titulo' => 'De la ficha de empresa al sitio publicado',
                    'texto' => 'Partimos de tu presentación comercial y de las preguntas que te repiten los compradores. La demo se puede navegar desde la semana 2 y hay entregas semanales; todo el código queda en tu cuenta de GitHub desde el primer commit. Fases y plazos, en',
                    'enlace' => 'la página de desarrollo web',
                ],
                'casos' => [
                    'titulo' => 'Resultados que puedes verificar',
                    'texto' => 'Se incluyen por el método y no se presentan como clientes de Querétaro.',
                    'puntos' => [
                        ['slug' => 'equiterm-industries', 't' => 'Equiterm Industries, hecho a la medida', 'd' => 'Clics: +790 % en tres meses, con una base pequeña al inicio. La fuente es Google Search Console.'],
                        ['slug' => 'industrial-b2b', 't' => 'Industrial B2B, nombre reservado', 'd' => 'Retorno de 8.6x en Google Ads durante enero-septiembre de 2026, según el reporte de la cuenta y el CRM.'],
                    ],
                ],
                'faqs' => [
                    [
                        'p' => '¿Pueden hacer un portal para proveedores?',
                        'r' => "Sí. Entra en el tipo de proyecto \"Plataforma\", con cuentas, panel y roles, desde {$p['plataforma']} MXN + IVA.",
                    ],
                    [
                        'p' => '¿Pueden mostrar certificaciones y capacidades?',
                        'r' => 'Sí. Se diseña una sección clara con tu información verificada.',
                    ],
                    [
                        'p' => '¿Cómo es el proceso?',
                        'r' => 'Demo desde la semana 2, avances cada semana y pago por hitos.',
                    ],
                    [
                        'p' => '¿Cuánto cuesta una página web en Querétaro?',
                        'r' => "Los precios iniciales son {$p['sencilla']} para una landing sencilla, {$p['landing']} para una de campaña, {$p['corporativo']} para un sitio corporativo, {$p['ecommerce']} para un e-commerce y {$p['plataforma']} para una plataforma, todos en MXN + IVA. El configurador calcula rango y semanas, salvo en la landing sencilla, que se cotiza aparte.",
                    ],
                    [
                        'p' => '¿Todo el proyecto se puede llevar a distancia?',
                        'r' => 'Sí. Nos reunimos por videollamada, revisas los avances en línea y el repositorio está a tu nombre.',
                    ],
                ],
                'cierre' => [
                    'titulo' => '¿Hablamos de tu proyecto en Querétaro?',
                    'texto' => 'Elige portal, catálogo o sitio corporativo y conoce inversión y cronograma al instante.',
                ],
            ],

            // Hotel Fratelli se cita solo por su resultado en búsqueda: lo que se
            // construyó fue el PMS y las fichas, no una página como la que aquí
            // se vende.
            'hoteles' => [
                'slug' => 'hoteles',
                'afines' => ['cancun', 'puebla'],
                'nombre' => 'Hoteles',
                'enlace' => 'Diseño de páginas web para hoteles',
                'actualizado' => '2026-11-23',
                'meta_title' => 'Diseño de Páginas Web para Hoteles | RankPro',
                'meta_description' => "Páginas web para hoteles con reservas directas, varios idiomas y velocidad en móvil. Diseño y desarrollo a la medida en México. Desde {$p['corporativo']} + IVA.",
                'h1' => 'Diseño de páginas web para hoteles',
                'schema_nombre' => 'Diseño de páginas web para hoteles',
                'area' => ['@type' => 'Country', 'name' => 'México'],
                'intro' => 'Diseño de páginas web para hoteles, hostales y rentas vacacionales con un objetivo claro: que el huésped encuentre tu propiedad, la entienda en segundos y reserve directo.',
                'precio' => "El sitio de un hotel parte de {$p['corporativo']} MXN + IVA. Con reservas y multi-idioma, el rango sube; el configurador te lo muestra.",
                'necesidad' => [
                    'titulo' => 'De la búsqueda a la reserva, sin rodeos',
                    'texto' => 'Quien busca hospedaje compara varias opciones a la vez. Si tu página tarda, esconde las tarifas o manda a otro sitio para consultar disponibilidad, es fácil que vuelva al intermediario donde te vio. La página de un hotel tiene que resolver dudas y dejar reservar.',
                ],
                'publico' => [
                    'titulo' => 'Qué necesita la página de un hotel',
                    'puntos' => [
                        'Reserva directa visible desde cualquier pantalla',
                        'Galería rápida, que no pese de más',
                        'Habitaciones y tarifas claras, con disponibilidad',
                        'Textos en español e inglés como mínimo',
                        'Ubicación, accesos y cómo llegar',
                        'Reseñas reales, publicadas con autorización',
                    ],
                ],
                'proyectos' => [
                    'titulo' => 'Módulos que construimos para tu hotel',
                    'puntos' => [
                        ['t' => 'Motor o módulo de reservas', 'd' => 'Consulta de fechas, disponibilidad y confirmación al huésped.'],
                        ['t' => 'Multi-idioma', 'd' => 'Dirección propia para cada lengua, sin mezclar textos.'],
                        ['t' => 'Pagos en línea', 'd' => 'Anticipo o total de la estancia, según tu política.'],
                        ['t' => 'Galería optimizada', 'd' => 'Fotos bonitas sin lentitud.'],
                        ['t' => 'CMS editable', 'd' => 'Recepción o ventas cambian tarifas y promociones sin esperar a nadie.'],
                        ['t' => 'SEO técnico y schema', 'd' => 'Datos estructurados del hotel y sus habitaciones para Google.'],
                    ],
                ],
                'cuidados' => [
                    'titulo' => 'Velocidad en móvil y posicionamiento local',
                    'puntos' => [
                        ['t' => 'Fotos que no frenan', 'd' => 'Suelen ser lo más pesado de un hotel: las comprimimos y medimos Core Web Vitals antes de entregar.'],
                        ['t' => 'Reserva con el pulgar', 'd' => 'Calendario, botones y formulario se prueban en celular, no solo en escritorio.'],
                        ['t' => 'Ficha de Google coherente', 'd' => 'Nombre, teléfono y dirección iguales en el sitio y en tu perfil de empresa.', 'guia' => 'seo-local'],
                        ['t' => 'Contenido sobre la zona', 'd' => 'Playas, eventos o centros de negocio cercanos ayudan a que te encuentren. Lo desarrolla nuestro', 'servicio' => 'seo-organico', 'ancla' => 'servicio de SEO para posicionar tu hotel'],
                    ],
                ],
                'trabajo' => [
                    'titulo' => 'Cómo se trabaja sin detener la operación',
                    'texto' => 'Reunimos fotos, tipos de habitación y políticas, y acordamos cómo se conectará la reserva. Desde la semana 2 navegas una demo y después hay entregas semanales en un ambiente de pruebas; el código vive en un repositorio de GitHub que es tuyo. Conoce las etapas en',
                    'enlace' => 'nuestro desarrollo de páginas web',
                ],
                'casos' => [
                    'titulo' => 'Resultados publicados y qué demuestra cada uno',
                    'texto' => 'El del hotel se incluye solo por su posicionamiento; los otros dos son de otros giros.',
                    'puntos' => [
                        ['slug' => 'hotel-fratelli', 't' => 'Hotel Fratelli: resultado en búsqueda', 'd' => 'Con SEO pasó de no aparecer a 1,330 impresiones y un CTR de 3.1 % en el periodo medido. Fuente: Google Search Console.'],
                        ['slug' => 'equiterm-industries', 't' => 'Equiterm Industries: desarrollo a la medida', 'd' => 'Creció 790 % en clics a lo largo de tres meses; su base era pequeña. Lo registra Search Console.'],
                        ['slug' => 'industrial-b2b', 't' => 'Industrial B2B: anuncios, sin revelar el nombre', 'd' => 'Google Ads le devolvió 8.6x de enero a septiembre de 2026. Consta en el reporte y en su CRM.'],
                    ],
                ],
                'faqs' => [
                    [
                        'p' => '¿Pueden hacer una página web para mi hotel con reservas?',
                        'r' => 'Sí. Se construye con un módulo de reservas o se integra tu motor actual, si permite hacerlo. Lo confirmamos contigo antes de cotizar, según el motor que uses.',
                    ],
                    [
                        'p' => '¿Se puede hacer en varios idiomas?',
                        'r' => 'Sí. Se arma una URL por idioma, con textos revisados, para que cada huésped vea su versión.',
                    ],
                    [
                        'p' => '¿Cómo ayuda la página a reducir comisiones de intermediarios?',
                        'r' => 'Una página rápida, clara y con reserva directa invita a reservar sin pasar por terceros. El efecto depende de tu mercado y de tu tráfico; no lo garantizamos.',
                    ],
                    [
                        'p' => '¿También se encargan del posicionamiento en Google?',
                        'r' => 'Sí, como servicio aparte. El desarrollo deja lista la base técnica y el SEO sigue con contenido y seguimiento.',
                    ],
                    [
                        'p' => '¿Cuánto cuesta la página web de un hotel?',
                        'r' => "Un sitio corporativo parte desde {$p['corporativo']} MXN + IVA; con reservas, multi-idioma o pagos, el rango sube. El configurador muestra el rango.",
                    ],
                ],
                'cierre' => [
                    'titulo' => '¿Quieres más reservas directas?',
                    'texto' => 'Arma tu cotización con los módulos que necesita tu hotel o cuéntanos tu caso.',
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
