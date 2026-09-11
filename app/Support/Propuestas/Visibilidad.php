<?php

namespace App\Support\Propuestas;

/**
 * Catálogo de lo que se puede ocultar en el PDF de una propuesta sin borrarlo
 * de la plataforma. Fuente única: el editor pinta un interruptor por clave, el
 * controlador valida contra esta lista y las plantillas del PDF preguntan por
 * cada clave antes de imprimir.
 *
 * Las claves con punto son bloques dentro de una sección; las claves sin punto
 * (`situacion`, `contexto`, `plan`, `condiciones`) son la sección entera. Una
 * sección apagada oculta todos sus bloques aunque estén marcados visibles: eso
 * lo resuelve Propuesta::visible(), no este catálogo.
 *
 * `plan.barra_desglose` y `plan.meses_horas` existen aparte de `plan.barra_precio`
 * y `plan.meses` porque son los sitios donde el PDF revela «horas × tarifa», y
 * el equipo necesita poder ocultar la tarifa por hora sin ocultar el precio ni
 * la tabla de trabajo.
 */
class Visibilidad
{
    /**
     * @return array<string, array{etiqueta: string, pestana: string}>
     */
    public static function elementos(): array
    {
        return [
            // Portada
            'portada.sitio_web' => ['etiqueta' => 'URL del sitio', 'pestana' => 'resumen'],
            'portada.subtitulo' => ['etiqueta' => 'Subtítulo del plan', 'pestana' => 'resumen'],
            'portada.vigencia' => ['etiqueta' => 'Vigencia', 'pestana' => 'resumen'],
            'portada.precio' => ['etiqueta' => 'Precio mensual', 'pestana' => 'resumen'],
            'portada.desglose' => ['etiqueta' => 'Desglose horas × tarifa', 'pestana' => 'resumen'],
            'portada.estadisticas' => ['etiqueta' => 'Estadísticas destacadas', 'pestana' => 'resumen'],

            // Situación actual
            'situacion' => ['etiqueta' => 'Sección completa', 'pestana' => 'situacion'],
            'situacion.intro' => ['etiqueta' => 'Resumen de la situación', 'pestana' => 'situacion'],
            'situacion.kpis' => ['etiqueta' => 'KPI de situación actual', 'pestana' => 'situacion'],
            'situacion.comparacion' => ['etiqueta' => 'Comparación de periodos', 'pestana' => 'situacion'],
            'situacion.consultas' => ['etiqueta' => 'Posiciones y oportunidades', 'pestana' => 'situacion'],
            'situacion.insight' => ['etiqueta' => 'Insight crítico', 'pestana' => 'situacion'],

            // Por qué continuar
            'contexto' => ['etiqueta' => 'Sección completa', 'pestana' => 'contexto'],
            'contexto.intro' => ['etiqueta' => 'Introducción', 'pestana' => 'contexto'],
            'contexto.riesgos' => ['etiqueta' => 'Riesgos si no se actúa', 'pestana' => 'contexto'],
            'contexto.protege' => ['etiqueta' => 'Lo que el plan protege', 'pestana' => 'contexto'],
            'contexto.logica' => ['etiqueta' => 'Lógica de negocio', 'pestana' => 'contexto'],

            // Plan en detalle
            'plan' => ['etiqueta' => 'Sección completa', 'pestana' => 'plan'],
            'plan.barra_precio' => ['etiqueta' => 'Barra de precio', 'pestana' => 'plan'],
            'plan.barra_desglose' => ['etiqueta' => 'Horas × tarifa en la barra', 'pestana' => 'plan'],
            'plan.alcance_incluido' => ['etiqueta' => 'Alcance incluido', 'pestana' => 'plan'],
            'plan.alcance_no_incluido' => ['etiqueta' => 'Alcance no incluido', 'pestana' => 'plan'],
            'plan.meses' => ['etiqueta' => 'Plan mes a mes', 'pestana' => 'plan'],
            'plan.meses_horas' => ['etiqueta' => 'Columna de horas', 'pestana' => 'plan'],

            // Condiciones y proyección
            'condiciones' => ['etiqueta' => 'Sección completa', 'pestana' => 'condiciones'],
            'condiciones.tabla' => ['etiqueta' => 'Condiciones comerciales', 'pestana' => 'condiciones'],
            'condiciones.renegociacion' => ['etiqueta' => 'Renegociación al mes 3', 'pestana' => 'condiciones'],
            'condiciones.proyeccion' => ['etiqueta' => 'Proyección de resultados', 'pestana' => 'condiciones'],
            'condiciones.contacto' => ['etiqueta' => 'Bloque de contacto', 'pestana' => 'condiciones'],
        ];
    }

    /** @return array<int, string> */
    public static function claves(): array
    {
        return array_keys(self::elementos());
    }

    /**
     * Claves de una pestaña, en el orden del catálogo.
     *
     * @return array<string, array{etiqueta: string, pestana: string}>
     */
    public static function dePestana(string $pestana): array
    {
        return array_filter(self::elementos(), fn ($e) => $e['pestana'] === $pestana);
    }

    /** La sección a la que pertenece una clave de bloque, o null si ya es sección. */
    public static function seccionDe(string $clave): ?string
    {
        if (! str_contains($clave, '.')) {
            return null;
        }

        $seccion = strtok($clave, '.');

        // La portada no tiene interruptor de sección: siempre se imprime.
        return array_key_exists($seccion, self::elementos()) ? $seccion : null;
    }
}
