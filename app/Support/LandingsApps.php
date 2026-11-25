<?php

namespace App\Support;

/**
 * Landings de aplicaciones móviles por ciudad: /servicios/desarrollo-de-aplicaciones-moviles/{slug}.
 *
 * Una landing existe solo cuando está en todos(): de aquí salen la ruta, el
 * sitemap y llms.txt. La página padre la enlaza desde `landings` en Servicios.
 *
 * Texto propio, 750 palabras visibles como mínimo, sin copiar del servicio ni
 * de otras landings. Sin precios, plazos, tecnologías, clientes, oficinas ni
 * resultados locales: no hay datos confirmados.
 */
class LandingsApps
{
    /** Slug del servicio padre en el catálogo. */
    public const SERVICIO = 'desarrollo-de-aplicaciones-moviles';

    /**
     * `actualizado` es el lastmod del sitemap: subirlo a mano al cambiar texto.
     * `web` y `seo_ciudad` enlazan las landings de la misma ciudad. En una FAQ,
     * `r` es idéntico en la página y en el FAQPage; `guia` añade un enlace
     * aparte si está publicada.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function todos(): array
    {
        return [
            'queretaro' => [
                'slug' => 'queretaro',
                'web' => 'queretaro',
                'seo_ciudad' => 'queretaro',
                'nombre' => 'Querétaro',
                'enlace' => 'Desarrollo de apps en Querétaro',
                'actualizado' => '2026-11-24',
                'meta_title' => 'Desarrollo de Apps en Querétaro | RankPro',
                'meta_description' => 'Desarrollo de aplicaciones móviles en Querétaro para industria, logística, tecnología y servicios: diseño, desarrollo, publicación y mantenimiento.',
                'h1' => 'Desarrollo de apps en Querétaro',
                'schema_nombre' => 'Desarrollo de apps en Querétaro',
                'area' => ['@type' => 'City', 'name' => 'Querétaro'],
                'intro' => 'El desarrollo de apps en Querétaro que ofrecemos va de definir qué debe hacer la aplicación a publicarla y mantenerla. Trabajamos con tu equipo de forma remota y con entregas por etapas.',
                'sector' => [
                    'titulo' => 'Qué necesitan las empresas de Querétaro de una app',
                    'puntos' => [
                        'Querétaro reúne industria, manufactura, logística, tecnología y servicios, entre otros sectores. En ese contexto, muchas apps útiles no son para el público general, sino para operar mejor: equipos en campo, control de procesos, seguimiento de entregas o atención a clientes.',
                        'El origen suele ser parecido: un reporte en papel que alguien captura por la tarde, una foto enviada a un grupo que después nadie encuentra, o una llamada para saber si la unidad ya salió. Una app para tu empresa en Querétaro vale la pena cuando quita uno de esos pasos, no cuando solo los pasa a una pantalla.',
                        'Por eso, antes de proponer algo revisamos si de verdad resuelve un problema concreto o si una herramienta más simple basta. Un formulario en línea o un ajuste al sistema que ya pagas puede ser la respuesta, y preferimos decirlo al principio.',
                    ],
                ],
                'publico' => [
                    'titulo' => 'Para qué negocios la hacemos',
                    'puntos' => [
                        ['t' => 'Industria y manufactura', 'd' => 'Captura de datos en planta o en campo: inspecciones, paros, listas de verificación y evidencias con foto, ligadas a la persona y al turno.'],
                        ['t' => 'Logística', 'd' => 'Seguimiento de rutas y entregas, con confirmación en el momento y motivo cuando algo no se pudo entregar.'],
                        ['t' => 'Empresas de tecnología', 'd' => 'La versión para el teléfono de un producto que ya existe, conectada a la misma información que usan sus clientes.'],
                        ['t' => 'Servicios profesionales', 'd' => 'Citas, solicitudes y documentos en un solo lugar, para que el cliente consulte su avance por sí mismo.'],
                        ['t' => 'Comercios', 'd' => 'Una experiencia propia para quien compra seguido: historial, beneficios y avisos que el cliente aceptó recibir.'],
                    ],
                ],
                'incluye' => [
                    'titulo' => 'Qué incluye',
                    'texto' => 'Lo que entra en tu proyecto queda escrito en la propuesta. El punto de partida es este:',
                    'puntos' => [
                        'Definición de alcance: objetivos, usuarios y funciones esenciales.',
                        'Diseño: flujos y prototipo para validar antes de programar.',
                        'Desarrollo e integraciones con los sistemas que ya usas, si permiten conectarse.',
                        'Pruebas en varios dispositivos.',
                        'Publicación con cuentas a nombre de tu empresa.',
                        'Mantenimiento y mejora con base en el uso, según lo acordado.',
                    ],
                ],
                'trabajo' => [
                    'titulo' => 'Cómo trabajamos',
                    'texto' => 'Todo se coordina por videollamada. Cada etapa deja algo que tu equipo puede abrir y comentar.',
                    'puntos' => [
                        ['t' => 'Descubrimiento', 'd' => 'Una sesión para entender el proceso o el problema. Conviene que asista quien hace la tarea, no solo quien la supervisa.'],
                        ['t' => 'Prototipo', 'd' => 'Pantallas navegables que pruebas con usuarios reales. Corregir aquí es más sencillo que hacerlo con la app programada.'],
                        ['t' => 'Desarrollo por etapas', 'd' => 'Construimos primero la función que justifica el proyecto y después las que la complementan.'],
                        ['t' => 'Prueba y salida', 'd' => 'Se revisa con un grupo pequeño, en sus condiciones de trabajo, y luego se prepara la publicación.'],
                    ],
                ],
                'operacion' => [
                    'titulo' => 'Preguntas que hacemos antes de una app de operación',
                    'puntos' => [
                        ['t' => '¿Hay señal donde se usará?', 'd' => 'Dentro de una nave o en carretera la conexión puede fallar. Si pasa, hay que decidir qué se guarda en el teléfono y cuándo se envía.'],
                        ['t' => '¿De quién es el dispositivo?', 'd' => 'No es lo mismo un equipo de la empresa compartido por turnos que el celular personal de cada quien.'],
                        ['t' => '¿Qué sistema manda?', 'd' => 'Si el dato ya vive en tu ERP o en otra base, la app debe consultarlo ahí y no crear una segunda versión.'],
                        ['t' => '¿Qué se hará con el dato?', 'd' => 'Cada campo que se pide cuesta tiempo. Si nadie lo va a revisar, no se pide.'],
                    ],
                ],
                'cuidados' => [
                    'titulo' => 'Lo que cuidamos',
                    'puntos' => [
                        'Que la app resuelva algo medible: tiempo de captura, solicitudes atendidas o entregas confirmadas.',
                        'Que sea fácil de usar para tu equipo o tus clientes, incluso con prisa o con una sola mano.',
                        'Seguridad y manejo responsable de datos personales, con permisos según el puesto.',
                        'Cuentas bajo el control de tu empresa y entrega del código acordada por escrito.',
                        'Un plan de mantenimiento realista, con una persona responsable de tu lado.',
                    ],
                ],
                'errores' => [
                    'titulo' => 'Qué suele frenar una app dentro de la empresa',
                    'puntos' => [
                        'Diseñarla en la oficina sin ver cómo se trabaja en piso o en ruta.',
                        'Dejar el papel y la app funcionando a la vez, sin fecha para elegir uno.',
                        'Querer reemplazar todos los formatos en la primera versión.',
                        'Lanzarla sin explicar al personal qué gana con usarla.',
                    ],
                ],
                'faqs' => [
                    [
                        'p' => '¿Desarrollan apps para empresas de Querétaro?',
                        'r' => 'Sí. Trabajamos con empresas de Querétaro y de todo México de forma remota, con reuniones por videollamada.',
                    ],
                    [
                        'p' => '¿Cuánto cuesta una app en Querétaro?',
                        'r' => 'Depende de funciones, plataformas e integraciones. Se cotiza tras entender tu caso.',
                        'guia' => 'cuanto-cuesta-crear-una-app',
                    ],
                    [
                        'p' => '¿Hacen apps internas para operación y logística?',
                        'r' => 'Sí, según el caso: captura de datos, seguimiento de tareas y reportes.',
                    ],
                    [
                        'p' => '¿Android, iOS o ambas?',
                        'r' => 'Se decide según tus usuarios.',
                        'guia' => 'plataformas-para-crear-aplicaciones-moviles',
                    ],
                    [
                        'p' => '¿Quién es dueño de la app?',
                        'r' => 'Las cuentas de las tiendas se crean a nombre de tu empresa. La entrega y la propiedad del código se acuerdan por escrito en la propuesta.',
                    ],
                ],
                'cierre' => [
                    'titulo' => '¿Qué proceso te gustaría llevar al teléfono?',
                    'texto' => 'Con eso empezamos a revisar si el desarrollo de aplicaciones móviles en Querétaro es lo que tu empresa necesita o si hay un camino más corto.',
                ],
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function encontrar(string $slug): ?array
    {
        return self::todos()[$slug] ?? null;
    }
}
