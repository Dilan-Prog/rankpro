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
                'actualizado' => '2026-11-23',
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
