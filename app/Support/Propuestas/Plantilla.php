<?php

namespace App\Support\Propuestas;

/**
 * Esqueleto vacío de las 5 columnas JSON de una Propuesta (una por pestaña
 * del editor), sembrado en PropuestaController::store() para que ninguna
 * vista tenga que hacer `?? []` por todos lados. Los 6 renglones de
 * `condiciones` y los 3 slots de `opciones_renegociacion` son el punto de
 * partida editable que ya trae el diseño — no son campos fijos, el usuario
 * puede agregar/quitar filas de `condiciones` libremente.
 */
class Plantilla
{
    /**
     * @return array{
     *     resumen: array<string, mixed>,
     *     situacion_actual: array<string, mixed>,
     *     contexto_continuidad: array<string, mixed>,
     *     plan_detalle: array<string, mixed>,
     *     condiciones_proyeccion: array<string, mixed>,
     * }
     */
    public static function vacio(): array
    {
        return [
            'resumen' => [
                'sitio_web' => '',
                'subtitulo_plan' => 'Plan Continuidad (Puente 3 meses)',
                'vigencia_label' => '',
                'estadisticas_destacadas' => [
                    ['etiqueta' => 'Impresiones', 'valor' => '', 'nota' => ''],
                    ['etiqueta' => 'Clics orgánicos', 'valor' => '', 'nota' => ''],
                ],
            ],
            'situacion_actual' => [
                'kpis' => [
                    ['etiqueta' => 'Impresiones totales', 'valor' => '', 'color' => '#0E1B2A'],
                    ['etiqueta' => 'Clics orgánicos', 'valor' => '', 'color' => '#0FA37F'],
                    ['etiqueta' => 'CTR promedio', 'valor' => '', 'color' => '#0E1B2A'],
                    ['etiqueta' => 'Posición media ponderada', 'valor' => '', 'color' => '#0FA37F'],
                    ['etiqueta' => 'Páginas sin indexar', 'valor' => '', 'color' => '#C0392B'],
                ],
                'periodo_comparacion' => ['label_1' => '', 'label_2' => ''],
                'tabla_comparacion' => [
                    ['metrica' => 'Impresiones', 'valor_1' => '', 'valor_2' => ''],
                    ['metrica' => 'Clics orgánicos', 'valor_1' => '', 'valor_2' => ''],
                    ['metrica' => 'CTR promedio', 'valor_1' => '', 'valor_2' => ''],
                    ['metrica' => 'Posición media', 'valor_1' => '', 'valor_2' => ''],
                ],
                'tabla_consultas' => [
                    ['consulta' => '', 'posicion' => '', 'impresiones' => '', 'clics' => '', 'oportunidad' => ''],
                ],
                'resumen_texto' => '',
                'insight_texto' => '',
            ],
            'contexto_continuidad' => [
                'tabla_riesgos' => [
                    ['riesgo' => '', 'impacto' => ''],
                ],
                'checklist_protege' => [
                    ['titulo' => '', 'descripcion' => ''],
                ],
                'intro_texto' => '',
                'logica_negocio_texto' => '',
            ],
            'plan_detalle' => [
                'nombre' => 'Plan Continuidad',
                'duracion_meses' => '',
                'vigencia_inicio_texto' => '',
                'vigencia_fin_texto' => '',
                'alcance_incluido' => [''],
                'alcance_no_incluido' => [''],
                'meses' => [
                    ['mes_label' => 'M1', 'calendario' => '', 'actividades' => '', 'entregable' => '', 'horas' => ''],
                ],
            ],
            'condiciones_proyeccion' => [
                'condiciones' => [
                    ['etiqueta' => 'Duración', 'valor' => ''],
                    ['etiqueta' => 'Inversión', 'valor' => ''],
                    ['etiqueta' => 'Tarifa', 'valor' => ''],
                    ['etiqueta' => 'Horas incluidas', 'valor' => ''],
                    ['etiqueta' => 'Formas de pago', 'valor' => ''],
                    ['etiqueta' => 'Aviso de cancelación', 'valor' => ''],
                ],
                'opciones_renegociacion' => [
                    ['nombre' => '', 'descripcion' => ''],
                    ['nombre' => '', 'descripcion' => ''],
                    ['nombre' => '', 'descripcion' => ''],
                ],
                'proyeccion_periodo_label' => '',
                'tabla_proyeccion' => [
                    ['metrica' => '', 'base' => '', 'proyeccion' => '', 'escenario' => ''],
                ],
            ],
        ];
    }
}
