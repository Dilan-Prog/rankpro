<?php

namespace App\Support;

/**
 * Landings de SEO por ciudad: /servicios/seo-organico/{ciudad}.
 *
 * Fuente única: de aquí salen la ruta, el sitemap, llms.txt, el pie y los
 * enlaces de la landing padre. Una ciudad existe solo cuando está en todos(),
 * así que no hay forma de enlazar una que aún no se publica.
 *
 * El marco de la página es común, pero TODO el texto se escribe para cada
 * ciudad. La primera versión compartía proceso, revisiones y casos: entre 56 %
 * y 74 % de las frases eran idénticas entre páginas. CiudadesSeoTest falla si
 * dos landings vuelven a compartir el 15 %. Tampoco se declara oficina, cliente
 * ni cifra local que no esté confirmada.
 */
class CiudadesSeo
{
    /** Slug del servicio padre en el catálogo. */
    public const SERVICIO = 'seo-organico';

    /**
     * La clave es el slug de la URL. `actualizado` es el lastmod del sitemap:
     * subirlo a mano cuando cambie el contenido.
     *
     * En las FAQ, `guia` y `padre` añaden un enlace tras la respuesta; el texto
     * de `r` es el que viaja al FAQPage y es idéntico al visible.
     *
     * En `casos`, la cifra va siempre con su fuente y nunca se dice dónde está
     * un cliente anonimizado, ni por afirmación ni por descarte.
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
                'intro' => 'Somos una agencia SEO para empresas de CDMX que compiten por los mismos clientes que decenas de negocios a pocas calles. Revisamos tu sitio, tu contenido y tus enlaces para encontrar las búsquedas que sí puedes ganar, y lo medimos en Google Search Console.',
                'confianza' => 'Sin llamada de venta para arrancar · Acceso de lectura, nada más · Te quedas con el informe',
                'contexto' => [
                    'titulo' => 'Por qué el SEO en la Ciudad de México es distinto',
                    'texto' => 'El problema en la capital rara vez es falta de demanda: es que casi cualquier término general ya tiene dueño. Estas tres decisiones marcan el plan:',
                    'puntos' => [
                        ['t' => 'Elegir batallas por alcaldía y colonia', 'd' => 'La ciudad tiene 16 alcaldías y cada una funciona como un mercado. «Dentista» es una pelea larga; «dentista en la Del Valle» o «dentista en Coyoacán» es alcanzable y trae a quien sí puede llegar a tu consultorio.'],
                        ['t' => 'Separar al que trabaja del que vive en la zona', 'd' => 'En Santa Fe, Reforma o Polanco mucha gente busca desde su oficina; en Narvarte o Portales, desde su casa. El mismo servicio puede necesitar otro horario, otro mensaje y otra página.'],
                        ['t' => 'Resolver en el celular y en pocos segundos', 'd' => 'Quien busca camino al Metro o entre dos juntas no espera a que cargue un carrusel. Teléfono, zona de atención y precio orientativo tienen que verse sin hacer scroll.'],
                    ],
                ],
                'frentes' => [
                    ['t' => 'Velocidad y rastreo', 'd' => 'Corregimos lo que frena la carga en móvil y lo que impide a Google llegar a tus páginas: scripts de más, redirecciones en cadena y secciones huérfanas.'],
                    ['t' => 'Páginas por zona que sí dicen algo', 'd' => 'Una URL por alcaldía o colonia solo cuando hay información distinta que dar: cómo llegar, cobertura, tiempos o trabajos en esa zona. Si no la hay, no se crea.'],
                    ['t' => 'SEO local con varias sucursales', 'd' => 'Ordenamos fichas duplicadas, categorías y horarios de Google Business Profile, y conectamos cada ficha con su propia página en lugar de mandarlas todas al inicio.', 'guia' => 'seo-local'],
                    ['t' => 'Autoridad frente a directorios', 'd' => 'En la capital compites también con directorios y medios. Analizamos quién los cita y buscamos menciones del sector, sin comprar enlaces.'],
                ],
                'modalidad' => 'Todo el proyecto se lleva en línea, con videollamadas y acceso compartido a tus datos.',
                'pasos' => [
                    ['t' => 'Mapa de competencia', 'd' => 'Identificamos quién ocupa hoy los primeros resultados en tus alcaldías prioritarias y por qué.'],
                    ['t' => 'Lista corta de oportunidades', 'd' => 'Recibes las combinaciones de servicio y zona ordenadas por posibilidad real de avanzar.'],
                    ['t' => 'Plan o archivo', 'd' => 'Puedes encargarnos la ejecución o dárselo a tu propio equipo: el documento es tuyo.'],
                ],
                'revisiones_titulo' => 'Lo que buscamos primero en un sitio de la capital',
                'revisiones' => [
                    ['sev' => 'critico', 't' => 'Páginas de colonias clonadas', 'd' => 'Textos iguales con otro nombre de zona, que se estorban entre sí.'],
                    ['sev' => 'critico', 't' => 'Carga lenta con datos móviles', 'd' => 'LCP e INP medidos con usuarios reales, no solo en laboratorio.'],
                    ['sev' => 'critico', 't' => 'Fichas de Google repetidas o mal ubicadas', 'd' => 'Frecuente tras un cambio de domicilio o de sucursal.'],
                    ['sev' => 'medio', 't' => 'Servicios sin página propia', 'd' => 'Un solo apartado de «Servicios» no puede competir por diez búsquedas.'],
                    ['sev' => 'medio', 't' => 'Distancia en enlaces con quien va primero', 'd' => 'Sirve para saber si un término es viable este año.'],
                    ['sev' => 'ok', 't' => 'Orden de las URLs por servicio y alcaldía', 'd' => 'Una jerarquía clara evita rehacer el sitio al crecer.'],
                ],
                'casos' => [
                    'titulo' => 'Con qué criterio entramos a un mercado saturado',
                    'parrafos' => [
                        'No tenemos todavía un caso con nombre de una empresa de la capital que podamos enseñarte, y preferimos decirlo. Lo que sí aplicamos es una regla: no perseguir el término más grande hasta haber ganado los específicos.',
                        'Esa lógica de estructura por línea de servicio es la de Equiterm Industries, que creció 790 % en clics en tres meses según Google Search Console. Es una comercializadora industrial y hotelera, así que tómalo como método y no como promesa para tu giro.',
                    ],
                    'caso' => 'equiterm-industries',
                    'enlace' => 'Ver cómo se trabajó Equiterm',
                ],
                'faqs' => [
                    [
                        'p' => '¿Cuánto tarda el SEO en dar resultados en la Ciudad de México?',
                        'r' => 'Los cambios suelen notarse entre el mes 3 y el 6, y en categorías muy peleadas de la capital pueden tomar más. Por eso arrancamos con búsquedas de alcaldía o colonia.',
                    ],
                    [
                        'p' => '¿Conviene una página por cada alcaldía?',
                        'r' => 'Solo en las que atiendes y donde puedes decir algo propio. Dieciséis páginas iguales con otro nombre suelen perjudicar más de lo que ayudan.',
                    ],
                    [
                        'p' => 'Tengo varias sucursales en CDMX, ¿cómo se trabaja?',
                        'r' => 'Cada sucursal lleva su ficha de Google y su página, con dirección, horario y servicios reales. Así no compiten entre ellas por la misma búsqueda.',
                        'guia' => 'seo-local',
                    ],
                    [
                        'p' => '¿Necesito oficina en CDMX para aparecer en búsquedas de la ciudad?',
                        'r' => 'Para salir en el mapa sí hace falta un domicilio o un área de servicio válida. En los resultados orgánicos compites con contenido y autoridad, aunque estés en otro estado.',
                    ],
                    [
                        'p' => '¿Cuánto cuesta una agencia SEO en CDMX?',
                        'r' => 'Pesa sobre todo cuántas zonas y servicios quieres cubrir y qué tan fuerte es quien ya está arriba. La auditoría permite cotizar con ese dato en la mano.',
                        'guia' => 'cuanto-cuesta-el-posicionamiento-seo',
                    ],
                    [
                        'p' => '¿Trabajan solo con empresas de CDMX?',
                        'r' => 'No, la capital es una de las plazas que atendemos a distancia.',
                        'padre' => 'Para otras plazas, abre',
                    ],
                ],
                'cierre_titulo' => 'Averigua qué búsquedas de CDMX puedes ganar',
                'cierre' => 'Antes de invertir en la ciudad más disputada conviene saber en qué zonas tienes opción. Eso es lo que responde la auditoría.',
            ],

            'monterrey' => [
                'slug' => 'monterrey',
                'nombre' => 'Monterrey',
                'nombre_largo' => 'Monterrey',
                'actualizado' => '2026-11-23',
                'meta_title' => 'Agencia SEO en Monterrey: Posicionamiento Web | RankPro',
                'meta_description' => 'Agencia SEO en Monterrey para empresas de Nuevo León. SEO técnico, de contenidos y local, medido en Search Console. Pide tu auditoría sin compromiso.',
                'h1' => 'Agencia SEO en Monterrey: posiciona tu empresa en Google',
                'intro' => '¿Buscas una agencia SEO en Monterrey que entienda la venta entre empresas? Ayudamos a proveedores, fabricantes y firmas de servicios de Nuevo León a que compras e ingeniería los encuentren cuando comparan opciones.',
                'confianza' => 'Primero el diagnóstico, no la venta · Permisos solo de consulta · El documento queda en tu empresa',
                'contexto' => [
                    'titulo' => 'Posicionamiento SEO en Monterrey: qué hay que tener en cuenta',
                    'texto' => 'Aquí buena parte del negocio se cierra de empresa a empresa. Eso cambia qué se escribe, para quién y cómo se mide:',
                    'puntos' => [
                        ['t' => 'Pocas búsquedas, pedidos grandes', 'd' => 'Un término con treinta consultas al mes puede valer más que uno con tres mil si detrás hay una requisición. Se prioriza por valor del contrato, no por volumen.'],
                        ['t' => 'Compra un comité, no una persona', 'd' => 'Ingeniería busca especificaciones, compras busca condiciones y dirección busca respaldo. Cada perfil necesita su propia página para convencerse.'],
                        ['t' => 'El municipio dice mucho del cliente', 'd' => 'En Apodaca, Escobedo y Santa Catarina predomina la planta y el parque industrial; en San Pedro Garza García, el corporativo y el servicio profesional; San Nicolás mezcla industria y consumo.'],
                        ['t' => 'Se investiga antes de pedir cotización', 'd' => 'Las fichas técnicas, normas y comparativos que hoy están en un PDF suelen ser justo lo que el comprador intenta encontrar en Google.'],
                    ],
                ],
                'frentes' => [
                    ['t' => 'Catálogos que Google puede leer', 'd' => 'Pasamos líneas de producto, modelos y especificaciones de archivos descargables a páginas indexables con datos estructurados.'],
                    ['t' => 'Contenido para quien especifica', 'd' => 'Guías de selección, comparativos y casos de uso escritos con tu equipo técnico, porque un comprador industrial nota cuando el texto es genérico.'],
                    ['t' => 'Presencia por planta u oficina', 'd' => 'Si atiendes en varios municipios, ordenamos fichas y páginas para que se entienda dónde fabricas, dónde entregas y dónde recibes visitas.', 'guia' => 'seo-local'],
                    ['t' => 'Respaldo del sector', 'd' => 'Cámaras, clústeres, distribuidores y proveedores que ya te conocen son la fuente de menciones más creíble para una empresa industrial.'],
                ],
                'modalidad' => 'Las sesiones son por videollamada y pedimos que participe alguien de ventas además de marketing.',
                'pasos' => [
                    ['t' => 'Entrevista con ventas', 'd' => 'Qué preguntan los prospectos, qué línea deja mejor margen y qué cotizaciones se pierden.'],
                    ['t' => 'Cruce con la búsqueda', 'd' => 'Comparamos eso con lo que tu sitio y tus rivales regiomontanos responden hoy.'],
                    ['t' => 'Ruta por línea de negocio', 'd' => 'Entregamos prioridades por producto para que las ejecutes dentro o con nosotros.'],
                ],
                'revisiones_titulo' => 'Dónde suelen perderse cotizaciones',
                'revisiones' => [
                    ['sev' => 'critico', 't' => 'Información técnica atrapada en PDF', 'd' => 'Se descarga, pero casi nunca posiciona ni lleva a un formulario.'],
                    ['sev' => 'critico', 't' => 'Una sola página para todas las líneas', 'd' => 'Bombas, refacciones y servicio compiten por la misma URL.'],
                    ['sev' => 'critico', 't' => 'Formularios que no distinguen la cotización', 'd' => 'Sin ese evento no se sabe qué contenido genera negocio.'],
                    ['sev' => 'medio', 't' => 'Fichas de producto copiadas del fabricante', 'd' => 'El mismo texto aparece en todos los distribuidores.'],
                    ['sev' => 'medio', 't' => 'Sectores atendidos sin desarrollar', 'd' => 'Alimentos, metalmecánica o construcción merecen argumentos distintos.'],
                    ['sev' => 'ok', 't' => 'Filtros y parámetros del catálogo', 'd' => 'Evita que Google gaste rastreo en miles de combinaciones.'],
                ],
                'casos' => [
                    'titulo' => 'Experiencia en SEO para empresas industriales y de servicios',
                    'parrafos' => [
                        'El proyecto más parecido a este tipo de venta es el de una comercializadora industrial B2B que pidió no ser identificada, por lo que no publicamos su nombre ni su ubicación. Combinó ecommerce, SEO, automatización y anuncios durante más de tres años.',
                        'Su dato medido es de Google Ads: 8.6 pesos de venta por cada peso invertido de enero a septiembre de 2026, según el reporte de la cuenta y el CRM. Lo citamos porque enseña algo útil para el SEO B2B: sin conectar el CRM no se sabe qué búsqueda termina en pedido.',
                    ],
                    'caso' => 'industrial-b2b',
                    'enlace' => 'Leer el caso industrial completo',
                ],
                'faqs' => [
                    [
                        'p' => '¿Sirve el SEO para empresas B2B o industriales?',
                        'r' => 'Sí, sobre todo cuando el comprador compara proveedores antes de llamar. Se trabaja con menos visitas y se mide por cotizaciones calificadas.',
                    ],
                    [
                        'p' => '¿Cómo se mide si vendo por cotización y no en línea?',
                        'r' => 'Registramos formularios, llamadas y mensajes por página de origen y, si tienes CRM, los comparamos con las oportunidades abiertas y ganadas.',
                    ],
                    [
                        'p' => 'Mi catálogo está en PDF, ¿hay que rehacer el sitio?',
                        'r' => 'Por lo general basta con publicar las líneas prioritarias como páginas y dejar el PDF de apoyo. Un rediseño solo tiene sentido si la plataforma no lo permite.',
                    ],
                    [
                        'p' => '¿Cuánto tarda el posicionamiento SEO en Monterrey?',
                        'r' => 'Los cambios suelen notarse entre el mes 3 y el 6. En B2B hay que sumar tu propio ciclo de venta: la visita de hoy puede ser el pedido del próximo trimestre.',
                    ],
                    [
                        'p' => '¿Cuánto cuesta una agencia SEO en Monterrey?',
                        'r' => 'Depende de cuántas líneas de producto se trabajen y de cuánto contenido técnico haya que producir con tu gente.',
                        'guia' => 'cuanto-cuesta-el-posicionamiento-seo',
                    ],
                    [
                        'p' => '¿Atienden a empresas que venden fuera de Nuevo León?',
                        'r' => 'Sí. Si distribuyes en varios estados, el plan separa la demanda local de la nacional.',
                        'padre' => 'El alcance nacional se explica en',
                    ],
                ],
                'cierre_titulo' => 'Descubre qué busca compras antes de llamarte',
                'cierre' => 'Te mostramos qué preguntas de tus compradores responde hoy la competencia y cuáles siguen libres.',
            ],

            'guadalajara' => [
                'slug' => 'guadalajara',
                'nombre' => 'Guadalajara',
                'nombre_largo' => 'Guadalajara',
                'actualizado' => '2026-11-23',
                'meta_title' => 'Agencia SEO en Guadalajara: Posicionamiento Web | RankPro',
                'meta_description' => 'Agencia SEO en Guadalajara para empresas de Jalisco. SEO técnico, de contenidos y local, medido en Search Console. Pide tu auditoría sin compromiso.',
                'h1' => 'Agencia SEO en Guadalajara: posiciona tu empresa en Google',
                'intro' => '¿Buscas una agencia SEO en Guadalajara? Trabajamos con negocios tapatíos que venden cosas muy distintas, desde software hasta muebles o consultas médicas, y por eso el plan parte de tu cliente y no de una plantilla.',
                'confianza' => 'Nadie te llama para venderte · Basta con un acceso de lectura · El diagnóstico es para ti',
                'contexto' => [
                    'titulo' => 'SEO en Guadalajara: qué conviene tener en cuenta',
                    'texto' => 'Pocas ciudades reúnen giros tan diferentes a tan poca distancia. Lo que sirve en un corredor no sirve en el de al lado:',
                    'puntos' => [
                        ['t' => 'Cada municipio tiene su vocación', 'd' => 'Zapopan concentra corporativos, plazas y vivienda; Tlaquepaque y Tonalá son referencia en artesanía, decoración y mayoreo; Tlajomulco crece con fraccionamientos nuevos y sus servicios.'],
                        ['t' => 'Conviven menudeo y mayoreo', 'd' => 'Quien busca «muebles por mayoreo» y quien busca «muebles para sala» no debe llegar a la misma página, aunque el producto sea idéntico.'],
                        ['t' => 'Tecnología que compite fuera de Jalisco', 'd' => 'Una empresa de software tapatía rara vez quiere clientes de su colonia: su SEO es nacional o internacional y la ciudad solo aporta confianza y talento.'],
                        ['t' => 'Un calendario de ferias y expos', 'd' => 'Los eventos que se realizan en la ciudad generan picos de búsqueda de hospedaje, montaje, impresión o catering. Esas páginas tienen que existir meses antes.'],
                    ],
                ],
                'frentes' => [
                    ['t' => 'Comercio y mayoreo', 'd' => 'Categorías, existencias y condiciones de compra en páginas separadas, con datos estructurados de producto cuando aplica.'],
                    ['t' => 'Servicios profesionales y salud', 'd' => 'Perfiles de especialistas, tratamientos y dudas frecuentes firmadas por quien responde, porque en estos temas la experiencia demostrable cuenta.'],
                    ['t' => 'Negocios de barrio', 'd' => 'Ficha de Google completa, fotos recientes y reseñas contestadas para ganar el «cerca de mí» de tu colonia.', 'guia' => 'seo-local'],
                    ['t' => 'Software y agencias creativas', 'd' => 'Casos de uso, integraciones y portafolio como contenido indexable, en lugar de una sola página vistosa que Google no entiende.'],
                ],
                'modalidad' => 'Nos reunimos por videollamada y cada semana dejamos por escrito qué se hizo y qué sigue.',
                'pasos' => [
                    ['t' => 'Definir a quién quieres', 'd' => 'Mayorista, consumidor, paciente o empresa: se elige uno o dos perfiles para empezar.'],
                    ['t' => 'Leer tu Search Console', 'd' => 'Vemos por qué búsquedas apareces ya y cuáles no tienen nada que ver con tu negocio.'],
                    ['t' => 'Acordar el orden', 'd' => 'Sales con una lista de páginas por crear, fusionar o borrar, la hagas con quien la hagas.'],
                ],
                'revisiones_titulo' => 'Errores que revisamos según tu giro',
                'revisiones' => [
                    ['sev' => 'critico', 't' => 'Público mezclado en la misma URL', 'd' => 'Mayoreo y menudeo, o paciente y proveedor, sin camino propio.'],
                    ['sev' => 'critico', 't' => 'Tienda en línea con productos agotados', 'd' => 'Cientos de páginas sin salida que diluyen las que sí venden.'],
                    ['sev' => 'critico', 't' => 'Portafolios hechos solo con imágenes', 'd' => 'Sin texto ni atributos alternativos no hay nada que posicionar.'],
                    ['sev' => 'medio', 't' => 'Redes sociales con más información que el sitio', 'd' => 'Precios y horarios que solo existen en una historia.'],
                    ['sev' => 'medio', 't' => 'Municipio ausente o equivocado', 'd' => 'Decir «Guadalajara» cuando el local está en Zapopan confunde a todos.'],
                    ['sev' => 'ok', 't' => 'Subdominios y micrositios sueltos', 'd' => 'Conviene saber cuántos hay antes de publicar más.'],
                ],
                'casos' => [
                    'titulo' => 'Casos publicados y cómo leerlos desde tu giro',
                    'parrafos' => [
                        'Ninguno de los casos que publicamos se presenta como cliente tapatío. Los usamos para explicar decisiones: qué páginas se crearon, en qué orden y con qué se midió.',
                        'Si vendes varias líneas, el más cercano es Equiterm: calderas, calentadores y tratamiento de agua con una página por producto. La fuente de su +790 % de clics en tres meses es Google Search Console, y partía de una base pequeña.',
                    ],
                    'caso' => 'equiterm-industries',
                    'enlace' => 'Revisar la estructura por línea',
                ],
                'faqs' => [
                    [
                        'p' => 'Vendo mayoreo y menudeo, ¿necesito dos sitios?',
                        'r' => 'Casi nunca. Basta con dos secciones bien separadas, cada una con sus precios, mínimos de compra y formulario.',
                    ],
                    [
                        'p' => 'Mi local está en Zapopan o Tlaquepaque, ¿apunto a Guadalajara?',
                        'r' => 'La ficha y la dirección deben decir el municipio real. En el contenido se puede mencionar la zona metropolitana si de verdad la atiendes.',
                        'guia' => 'seo-local',
                    ],
                    [
                        'p' => 'Soy una empresa de software, ¿me sirve el SEO local?',
                        'r' => 'Poco. Suele rendir más trabajar problemas, integraciones y comparativos para todo el país, y usar la ciudad solo en la página de la empresa.',
                    ],
                    [
                        'p' => '¿Con cuánta anticipación preparo una página para una expo?',
                        'r' => 'Varios meses antes, para que Google la conozca cuando empiecen las búsquedas. Después se actualiza cada edición en la misma URL.',
                    ],
                    [
                        'p' => '¿Cuánto tarda el posicionamiento SEO en Guadalajara?',
                        'r' => 'Los cambios suelen notarse entre el mes 3 y el 6; un sitio nuevo o con muchas páginas por depurar puede necesitar más.',
                    ],
                    [
                        'p' => '¿Cuánto cuesta una agencia SEO en Guadalajara?',
                        'r' => 'Cambia según el giro: no cuesta lo mismo ordenar un catálogo de miles de productos que un consultorio. Se cotiza después de revisar el sitio.',
                        'guia' => 'cuanto-cuesta-el-posicionamiento-seo',
                        'padre' => 'El servicio completo está en',
                    ],
                ],
                'cierre_titulo' => 'Empieza por tu giro, no por una plantilla',
                'cierre' => 'Dinos qué vendes y a quién. Con eso y acceso de lectura te devolvemos un diagnóstico hecho para ese negocio.',
            ],

            // La keyword con volumen es "posicionamiento seo en puebla"; "agencia"
            // va de apoyo. Por eso fija su propio nombre de schema y título.
            'puebla' => [
                'slug' => 'puebla',
                'nombre' => 'Puebla',
                'nombre_largo' => 'Puebla',
                'actualizado' => '2026-11-23',
                'meta_title' => 'Posicionamiento SEO en Puebla: Agencia SEO | RankPro',
                'meta_description' => 'Posicionamiento SEO en Puebla para empresas poblanas. Agencia con SEO técnico, de contenidos y local, medido en Search Console. Pide tu auditoría.',
                'h1' => 'Posicionamiento SEO en Puebla: aparece cuando te buscan',
                'schema_nombre' => 'Posicionamiento SEO en Puebla',
                'servicio_titulo' => 'Servicio de posicionamiento SEO para empresas en Puebla',
                'intro' => '¿Buscas posicionamiento SEO en Puebla? Somos una agencia SEO que ayuda a hoteles, restaurantes, escuelas y empresas poblanas a llegar a tiempo a la búsqueda: antes del puente, de las inscripciones o de la temporada.',
                'confianza' => 'No empezamos con una llamada comercial · Solo miramos, con permiso de lectura · Conservas todo el reporte',
                'contexto' => [
                    'titulo' => 'Posicionamiento SEO en Puebla: qué conviene tener en cuenta',
                    'texto' => 'La demanda poblana sube y baja con el calendario, y cambia por completo de una zona a otra:',
                    'puntos' => [
                        ['t' => 'Del Centro Histórico a Angelópolis', 'd' => 'El turista busca a pie por el centro; la familia y el ejecutivo buscan en Angelópolis. Son dos clientes con horarios, precios y palabras diferentes.'],
                        ['t' => 'Las Cholulas no son una colonia', 'd' => 'San Andrés y San Pedro Cholula son municipios con vida propia, universitaria y turística. Meterlos en «Puebla» deja fuera búsquedas que los nombran.'],
                        ['t' => 'La cocina tiene temporada', 'd' => 'El chile en nogada concentra su interés en verano. La página que lo anuncia debe estar indexada antes y no publicarse cuando el plato ya está en la mesa.'],
                        ['t' => 'El ciclo escolar también se busca', 'd' => 'Universidades, colegios, residencias y servicios para estudiantes viven de convocatorias e inscripciones con fechas conocidas.'],
                        ['t' => 'La cadena automotriz compra distinto', 'd' => 'Para un proveedor de autopartes importan certificaciones, capacidades y tiempos de entrega, y sus consultas son escasas pero muy precisas.'],
                    ],
                ],
                'frentes' => [
                    ['t' => 'Hoteles y hospedaje', 'd' => 'Páginas de habitación, de zona y de temporada pensadas para la reserva directa, sin depender solo de las agencias en línea.'],
                    ['t' => 'Restaurantes y gastronomía', 'd' => 'Menú en texto y no en foto, platillos con su propia página cuando la gente los busca y ficha de Google siempre al día.', 'guia' => 'seo-local'],
                    ['t' => 'Escuelas y universidades', 'd' => 'Una página por programa con plan, costos orientativos y fechas, enlazada desde el calendario de admisión.'],
                    ['t' => 'Proveedores industriales', 'd' => 'Capacidades, procesos y certificaciones explicados para un ingeniero de calidad, con un contacto directo a ventas técnicas.'],
                ],
                'modalidad' => 'Coordinamos a distancia y fijamos juntos un calendario de publicación desde la primera llamada.',
                'pasos' => [
                    ['t' => 'Calendario del negocio', 'd' => 'Marcamos tus meses altos y bajos y contamos hacia atrás cuándo debe estar lista cada página.'],
                    ['t' => 'Diagnóstico con fechas', 'd' => 'Cada hallazgo lleva un límite: lo que urge antes de la próxima temporada va primero.'],
                    ['t' => 'Cierre de temporada', 'd' => 'Revisamos qué funcionó y qué se conserva para el año siguiente, sigas o no con nosotros.'],
                ],
                'revisiones_titulo' => 'Puntos que frenan a un negocio con temporadas',
                'revisiones' => [
                    ['sev' => 'critico', 't' => 'Páginas de temporada que se borran cada año', 'd' => 'Se pierde lo ganado y hay que empezar de nuevo.'],
                    ['sev' => 'critico', 't' => 'Menús y tarifas en imagen o PDF', 'd' => 'Ni Google ni el celular del cliente los leen bien.'],
                    ['sev' => 'critico', 't' => 'Ficha con horario equivocado en puentes', 'd' => 'Un «cerrado» falso cuesta visitas el mejor fin de semana.'],
                    ['sev' => 'medio', 't' => 'Sitio sin mención de Cholula o Angelópolis', 'd' => 'Si atiendes ahí, tiene que decirlo en alguna parte útil.'],
                    ['sev' => 'medio', 't' => 'Motor de reservas en otro dominio', 'd' => 'Rompe la medición y confunde a quien iba a pagar.'],
                    ['sev' => 'ok', 't' => 'Marcado de evento, curso o platillo', 'd' => 'Ayuda a que Google entienda fechas y oferta.'],
                ],
                'casos' => [
                    'titulo' => 'Lo que aprendimos trabajando turismo',
                    'parrafos' => [
                        'El caso publicado con más parecido a la hotelería poblana es Hotel Fratelli, que está en Aguascalientes y no en Puebla. Su ocupación pasó de 10 % a 50 %, dato del propio hotel, con un proyecto que sumó sistema de gestión, SEO y anuncios: no es mérito exclusivo del posicionamiento.',
                        'De ahí viene nuestro criterio para negocios con visitantes: crear pronto la página de la fecha fuerte, como se hizo allá con la Feria de San Marcos, y mantener viva la misma URL.',
                    ],
                    'caso' => 'hotel-fratelli',
                    'enlace' => 'Conocer el caso Hotel Fratelli',
                ],
                'faqs' => [
                    [
                        'p' => '¿Cuándo debo publicar el contenido de temporada?',
                        'r' => 'Lo recomendable es tenerlo en línea varios meses antes y refrescarlo cada año sin cambiar la dirección de la página.',
                    ],
                    [
                        'p' => 'Estoy en Cholula, ¿me posiciono como Puebla?',
                        'r' => 'Con las dos referencias, pero en orden: tu municipio en ficha y dirección, y Puebla como zona cercana cuando ayude al visitante a ubicarse.',
                        'guia' => 'seo-local',
                    ],
                    [
                        'p' => '¿Puedo depender menos de las plataformas de reserva?',
                        'r' => 'Es un objetivo razonable, aunque gradual. Se empieza por tu marca y por búsquedas de zona, y se comprueba cuántas reservas directas llegan.',
                    ],
                    [
                        'p' => '¿El SEO funciona para un proveedor automotriz?',
                        'r' => 'Puede aportar, si hay consultas de tus procesos. Cuando el volumen es mínimo te lo decimos y sugerimos otro canal.',
                    ],
                    [
                        'p' => '¿Cuánto tarda el posicionamiento SEO en Puebla?',
                        'r' => 'Los cambios suelen notarse entre el mes 3 y el 6, así que conviene iniciar al menos medio año antes de tu fecha fuerte.',
                    ],
                    [
                        'p' => '¿Cuánto cuesta el posicionamiento SEO en Puebla?',
                        'r' => 'Varía con el número de páginas, idiomas y temporadas que haya que preparar. Tras el diagnóstico recibes un alcance cerrado.',
                        'guia' => 'cuanto-cuesta-el-posicionamiento-seo',
                        'padre' => 'Antes puedes leer',
                    ],
                ],
                'cierre_titulo' => 'Llega antes que la temporada',
                'cierre' => 'Cuéntanos cuál es tu mes más fuerte y revisamos si tu sitio estará listo cuando la gente empiece a buscar.',
            ],

            'queretaro' => [
                'slug' => 'queretaro',
                'nombre' => 'Querétaro',
                'nombre_largo' => 'Querétaro',
                'actualizado' => '2026-11-23',
                'meta_title' => 'Agencia SEO en Querétaro: Posicionamiento Web | RankPro',
                'meta_description' => 'Agencia SEO en Querétaro para empresas de la zona metropolitana. SEO técnico, de contenidos y local, medido en Search Console. Pide tu auditoría.',
                'h1' => 'Agencia SEO en Querétaro: posiciona tu empresa en Google',
                'intro' => '¿Buscas una agencia SEO en Querétaro? Ayudamos a empresas que llegaron a la región por su industria, su logística o su crecimiento, y que aún no aparecen cuando un cliente nuevo busca proveedor.',
                'confianza' => 'Sin presentación comercial previa · Lectura de datos, sin permisos de edición · El entregable es de tu equipo',
                'contexto' => [
                    'titulo' => 'SEO en Querétaro: qué conviene tener en cuenta',
                    'texto' => 'Es un mercado en expansión donde muchos compradores acaban de llegar y no conocen a los proveedores locales:',
                    'puntos' => [
                        ['t' => 'Clientes que todavía no te conocen', 'd' => 'Plantas, oficinas y familias recién instaladas no tienen a quién preguntar y recurren al buscador. Ser visible ahí sustituye a la recomendación que aún no existe.'],
                        ['t' => 'Cadenas con requisitos estrictos', 'd' => 'Aeroespacial, automotriz y tecnología piden normas, trazabilidad y capacidad instalada. El proveedor que lo documenta en su sitio entra antes a la lista corta.'],
                        ['t' => 'Logística alrededor de la carretera 57', 'd' => 'Almacenaje, transporte y maquila se buscan por corredor: El Marqués para parques industriales y San Juan del Río, que está fuera de la zona metropolitana, como plaza aparte.'],
                        ['t' => 'Corregidora y Juriquilla viven otro ritmo', 'd' => 'En las zonas habitacionales dominan salud, educación, remodelación y servicios para el hogar, con decisiones rápidas y peso de las reseñas.'],
                        ['t' => 'Vino, queso y fines de semana', 'd' => 'Hacia Tequisquiapan y Ezequiel Montes el visitante planea por fecha y por experiencia, y suele reservar desde otra ciudad.'],
                    ],
                ],
                'frentes' => [
                    ['t' => 'Proveedores de manufactura', 'd' => 'Páginas por proceso y por certificación, con tolerancias, materiales y capacidad, para que un área de desarrollo de proveedores pueda evaluarte.'],
                    ['t' => 'Almacenes y transporte', 'd' => 'Cobertura por ruta, tipo de carga y metros disponibles explicados en páginas claras, no en un mapa sin texto.'],
                    ['t' => 'Servicios para quien se muda', 'd' => 'Contenido que responde lo que pregunta un recién llegado y una ficha local con la zona de atención bien definida.', 'guia' => 'seo-local'],
                    ['t' => 'Viñedos, hoteles y experiencias', 'd' => 'Información de visitas, aforos y eventos privados en español y, si tu público lo pide, en inglés.'],
                ],
                'modalidad' => 'Trabajamos en remoto y documentamos cada decisión para que también la entienda un corporativo fuera del estado.',
                'pasos' => [
                    ['t' => 'Requisitos del comprador', 'd' => 'Reunimos las preguntas que te hacen en altas de proveedor, licitaciones o primeras llamadas.'],
                    ['t' => 'Contraste con tu sitio', 'd' => 'Señalamos cuáles ya contestas, cuáles están escondidas y cuáles no aparecen.'],
                    ['t' => 'Primeras diez páginas', 'd' => 'Proponemos un arranque acotado que puedes producir internamente o encargarnos.'],
                ],
                'revisiones_titulo' => 'Lo que debe quedar claro en tu sitio',
                'revisiones' => [
                    ['sev' => 'critico', 't' => 'Certificaciones sin explicar', 'd' => 'Un logotipo no dice alcance, vigencia ni proceso cubierto.'],
                    ['sev' => 'critico', 't' => 'Ubicación ambigua', 'd' => 'Estado, capital o parque industrial se confunden con facilidad.'],
                    ['sev' => 'critico', 't' => 'Versión en inglés traducida a medias', 'd' => 'Sin hreflang correcto, Google puede mostrar la página equivocada.'],
                    ['sev' => 'medio', 't' => 'Capacidad y equipo sin datos', 'd' => 'Quien evalúa proveedores descarta lo que no puede comprobar.'],
                    ['sev' => 'medio', 't' => 'Sitio dirigido solo a clientes actuales', 'd' => 'Usa siglas internas que nadie nuevo escribiría en Google.'],
                    ['sev' => 'ok', 't' => 'Dominio corporativo compartido', 'd' => 'Conviene decidir si la planta local merece su propia sección.'],
                ],
                'casos' => [
                    'titulo' => 'Referencia industrial, con sus límites',
                    'parrafos' => [
                        'Contamos con un caso B2B del sector industrial cuyo cliente prefiere el anonimato; respetamos eso y tampoco decimos en qué ciudad opera. Empezó sin ningún contacto por internet.',
                        'La cifra comprobable procede de su publicidad en Google, con 8.6 de retorno entre enero y septiembre de 2026 (fuente: reporte de Google Ads y CRM). No es un resultado de SEO, pero sí muestra que un comprador técnico llega por el buscador cuando encuentra información suficiente.',
                    ],
                    'caso' => 'industrial-b2b',
                    'enlace' => 'Abrir la ficha del proyecto B2B',
                ],
                'faqs' => [
                    [
                        'p' => 'Acabamos de abrir en Querétaro, ¿por dónde empezamos?',
                        'r' => 'Por una página local con datos reales de la operación y por la ficha de Google, si recibes visitas o defines un área de servicio. Después vienen los procesos.',
                        'guia' => 'seo-local',
                    ],
                    [
                        'p' => '¿Necesito el sitio en inglés?',
                        'r' => 'Si tus compradores evalúan desde otro país, sí, al menos en capacidades y contacto. Si vendes en la región, primero va el español.',
                    ],
                    [
                        'p' => '¿San Juan del Río y Querétaro van en la misma página?',
                        'r' => 'Depende de tu operación. Con instalaciones en ambas plazas conviene separarlas; si solo entregas allá, alcanza con describir la cobertura.',
                    ],
                    [
                        'p' => 'Mi corporativo controla el sitio, ¿se puede hacer SEO local?',
                        'r' => 'Normalmente se consigue una sección para la planta. Preparamos la propuesta técnica para que tu área central la apruebe.',
                    ],
                    [
                        'p' => '¿Cuánto tarda el posicionamiento SEO en Querétaro?',
                        'r' => 'Los cambios suelen notarse entre el mes 3 y el 6. Con aprobaciones corporativas de por medio, lo que más alarga el plazo es publicar.',
                    ],
                    [
                        'p' => '¿Cuánto cuesta una agencia SEO en Querétaro?',
                        'r' => 'Influyen los procesos a documentar, los idiomas y quién redacta la parte técnica. Con esas tres respuestas se prepara la propuesta.',
                        'guia' => 'cuanto-cuesta-el-posicionamiento-seo',
                        'padre' => 'Como referencia, consulta',
                    ],
                ],
                'cierre_titulo' => 'Entra a la lista corta de quien aún no te conoce',
                'cierre' => 'Revisamos tu sitio como lo haría un comprador recién llegado a la región y te decimos qué le falta para considerarte.',
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
