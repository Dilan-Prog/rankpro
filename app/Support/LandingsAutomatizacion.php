<?php

namespace App\Support;

/**
 * Landings de automatización por ciudad: /servicios/automatizacion-de-procesos/{slug}.
 *
 * Misma idea que LandingsWeb: una landing existe solo cuando está en todos(), y
 * de aquí salen la ruta, el sitemap, llms.txt y los enlaces de la página padre.
 * Va aparte porque este servicio no publica precios ni casos por ciudad.
 *
 * Texto propio por ciudad, sin copiar del servicio. No se declara oficina,
 * reuniones presenciales, clientes ni cifras locales sin confirmar.
 */
class LandingsAutomatizacion
{
    /** Slug del servicio padre en el catálogo. */
    public const SERVICIO = 'automatizacion-de-procesos';

    /**
     * `actualizado` es el lastmod del sitemap: subirlo a mano al cambiar texto.
     * `web` y `seo_ciudad` enlazan las landings de la misma ciudad.
     *
     * En una FAQ, `r` es idéntico en la página y en el FAQPage.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function todos(): array
    {
        return [
            'puebla' => [
                'slug' => 'puebla',
                'web' => 'puebla',
                'seo_ciudad' => 'puebla',
                'nombre' => 'Puebla',
                'enlace' => 'Empresas de automatización en Puebla',
                'actualizado' => '2026-11-24',
                'meta_title' => 'Empresas de Automatización en Puebla | RankPro',
                'meta_description' => 'Automatización de procesos para empresas en Puebla: leads al CRM, avisos y reportes con n8n. Diagnóstico gratuito de 45 minutos, de forma remota.',
                'h1' => 'Empresas de automatización en Puebla',
                'schema_nombre' => 'Automatización de procesos para empresas en Puebla',
                'area' => ['@type' => 'City', 'name' => 'Puebla'],
                'intro' => 'Si buscas empresas de automatización en Puebla, en RankPro trabajamos a distancia con negocios poblanos: cada prospecto llega al CRM sin captura manual, la persona correcta recibe el aviso y el reporte de la semana se genera sin copiar celdas.',
                'notas' => 'Diagnóstico de 45 minutos, sin costo · Trabajo remoto · Sin contratos anuales',
                'publico' => [
                    'titulo' => 'Qué empresas de Puebla se benefician',
                    'puntos' => [
                        ['t' => 'Comercios que venden por WhatsApp', 'd' => 'Cada conversación nueva queda registrada y con responsable, aunque la atiendan varias personas.'],
                        ['t' => 'Escuelas y centros de capacitación', 'd' => 'En inscripciones, los interesados se separan por programa y reciben la información que pidieron.'],
                        ['t' => 'Manufactura y proveedores', 'd' => 'Una solicitud pasa de ventas a producción o a compras con sus datos completos y sin reenviar correos.'],
                        ['t' => 'Despachos y servicios profesionales', 'd' => 'La cita, el expediente y el recordatorio se crean desde un mismo formulario.'],
                        ['t' => 'Negocios turísticos', 'd' => 'Las consultas y reservas que entran por varios canales se juntan en una sola lista.'],
                    ],
                ],
                'procesos' => [
                    'titulo' => 'Primeros procesos que solemos automatizar',
                    'texto' => 'Conviene empezar por uno solo, comprobar que funciona y después sumar el siguiente.',
                    'filas' => [
                        ['Lead de formulario, WhatsApp o anuncio → CRM', 'Aviso en segundos para responder antes'],
                        ['Aviso al responsable', 'Se sabe quién atiende a cada prospecto'],
                        ['Seguimiento automático', 'Menos leads fríos'],
                        ['Reporte semanal', 'Decisiones con datos'],
                    ],
                ],
                'trabajo' => [
                    'titulo' => 'Cómo trabajamos',
                    'texto' => 'Empezamos con un diagnóstico gratuito de 45 minutos. Con lo que nos cuentes diseñamos el flujo, lo construimos con n8n y, ya en marcha, lo vigilamos y ajustamos. No hay contratos anuales y, si lo prefieres, todo vive en tu propia instancia. Conoce cada paso en nuestro',
                    'enlace' => 'servicio de automatización de procesos',
                ],
                'remoto' => [
                    'titulo' => 'Trabajo remoto, con tu equipo en la llamada',
                    'texto' => 'El diagnóstico y la implementación se hacen por videollamada. Ayuda que participe quien realiza hoy la tarea, porque conoce las excepciones que no están escritas. Antes de conectar una herramienta acordamos qué accesos hacen falta.',
                ],
                'sector' => [
                    'titulo' => 'Qué necesitan las empresas de Puebla',
                    'puntos' => [
                        'En Puebla hay escuelas con temporadas de inscripción, talleres que surten a la industria, comercios familiares y despachos que viven de la agenda. Sus herramientas suelen ser correctas; lo que se pierde es el paso entre una y otra: el dato que alguien debía copiar, el mensaje que se quedó sin contestar, la cotización que nadie volvió a mirar.',
                        'Automatizar ahí no significa sustituir al personal. Significa que el sistema mueva la información y que la persona decida, hable con el cliente y atienda lo que se sale de lo normal.',
                    ],
                ],
                'detalle' => [
                    'titulo' => 'Cómo se ve cada automatización en el día a día',
                    'puntos' => [
                        ['t' => 'Seguimiento de clientes', 'd' => 'Cuando un interesado deja de responder, se crea una tarea para su vendedor con el historial a la vista. Si contesta, la tarea se cierra sola.'],
                        ['t' => 'Cotizaciones', 'd' => 'Los datos del formulario llenan la plantilla, alguien autoriza el precio y el documento sale por correo. Queda anotada la fecha para volver a preguntar.'],
                        ['t' => 'Reportes', 'd' => 'La información se toma de las mismas fuentes cada semana y llega a quien decide, con la lista de registros que tienen campos vacíos.'],
                        ['t' => 'Mensajes', 'd' => 'Se confirman citas, se avisa que una solicitud fue recibida y se canaliza al área indicada. Los temas delicados pasan siempre a una persona.'],
                    ],
                ],
                'tipos' => [
                    'titulo' => 'Señales de que tu empresa ya lo necesita',
                    'puntos' => [
                        'Dos personas capturan lo mismo en sistemas distintos',
                        'El dueño pregunta por el estado de un pedido y hay que llamar a tres áreas',
                        'En época de inscripciones o de cierre se contrata apoyo solo para copiar datos',
                        'Las solicitudes llegan por redes, teléfono y formulario, sin un registro común',
                        'Cuando falta la encargada, nadie sabe en qué paso quedó cada cliente',
                    ],
                ],
                'etapas' => [
                    'titulo' => 'Cómo trabajamos en Puebla, etapa por etapa',
                    'puntos' => [
                        ['t' => 'Observar', 'd' => 'En videollamada nos muestras la tarea tal como se hace, con sus atajos y excepciones.'],
                        ['t' => 'Dibujar', 'd' => 'Enviamos un diagrama sencillo que indica qué dispara el flujo, qué decide y dónde interviene tu equipo.'],
                        ['t' => 'Probar', 'd' => 'Corre con registros de ensayo y luego con unos cuantos reales, mientras el método anterior sigue disponible.'],
                        ['t' => 'Entregar', 'd' => 'Documentamos el recorrido, las conexiones usadas y qué hacer si una herramienta deja de responder.'],
                    ],
                ],
                'cuidados' => [
                    'titulo' => 'Qué cuidamos en cada flujo',
                    'puntos' => [
                        'Rapidez: el aviso debe salir cuando todavía sirve, y eso se verifica en las pruebas.',
                        'Claridad: los pasos llevan nombres que entiende quien opera, no solo quien programa.',
                        'Permisos: cada conexión usa únicamente los accesos que hacen falta.',
                        'Medición: se guarda cuántas veces corrió, cuántas falló y por qué.',
                        'Mantenimiento: si cambia un formulario o una cuenta, se revisa el flujo y se vuelve a probar.',
                    ],
                ],
                'errores' => [
                    'titulo' => 'Errores comunes al automatizar por primera vez',
                    'puntos' => [
                        'Empezar por el proceso más enredado de la empresa en lugar del más repetido.',
                        'Automatizar una base con duplicados y contactos sin teléfono.',
                        'Enviar respuestas automáticas a quien ya estaba hablando con un asesor.',
                        'No nombrar a un responsable que reciba las alertas cuando algo se detiene.',
                    ],
                ],
                'faqs' => [
                    [
                        'p' => '¿Tienen oficina en Puebla?',
                        'r' => 'Trabajamos de forma remota con empresas de Puebla y de todo México. Las reuniones son por videollamada.',
                    ],
                    [
                        'p' => '¿Cuánto tarda el diagnóstico?',
                        'r' => '45 minutos, sin costo.',
                    ],
                    [
                        'p' => '¿Qué empresas de Puebla lo necesitan?',
                        'r' => 'Cualquiera con tareas repetitivas entre herramientas: comercios, escuelas, manufactura y servicios.',
                    ],
                    [
                        'p' => 'Somos una escuela en Puebla. ¿Qué parte de las inscripciones se puede automatizar?',
                        'r' => 'El registro del interesado, el envío de requisitos según el programa, los recordatorios de documentos y el aviso al área de admisiones. La revisión de cada caso la sigue haciendo tu personal.',
                    ],
                    [
                        'p' => '¿Un taller o proveedor industrial necesita cambiar su sistema para automatizar?',
                        'r' => 'No necesariamente. Primero se revisa si lo que usa permite conectarse o exportar datos. Si no es posible, te lo decimos en el diagnóstico y se valora otra forma de resolverlo.',
                    ],
                ],
                'cierre' => [
                    'titulo' => '¿Qué tarea se repite cada día en tu empresa?',
                    'texto' => 'Cuéntanosla y en el diagnóstico vemos si conviene automatizarla o dejarla como está.',
                ],
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function encontrar(string $slug): ?array
    {
        return self::todos()[$slug] ?? null;
    }

    /** @return list<array{label: string, url: string}> */
    public static function enlaces(): array
    {
        return array_values(array_map(static fn (array $l): array => [
            'label' => $l['enlace'],
            'url' => route('servicios.automatizacion', $l['slug']),
        ], self::todos()));
    }
}
