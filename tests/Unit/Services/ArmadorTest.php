<?php

namespace Tests\Unit\Services;

use App\Enums\AreaReporte;
use App\Enums\EstadoReporte;
use App\Enums\TipoSeccion;
use App\Models\Cliente;
use App\Models\Reporte;
use App\Models\ReporteSeccion;
use App\Services\Reportes\Armador;
use App\Support\Reportes\EsquemaSeccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El Armador es el único sitio donde se hace aritmética del reporte: el PDF y
 * el XLSX pintan lo que sale de aquí. Estos tests son los que impiden que los
 * dos entregables del mismo reporte lleguen al cliente con cifras distintas.
 *
 * Igual que FinanzasMetricsTest, se apoya en la base de pruebas dedicada
 * (RefreshDatabase) porque armar() recorre relaciones Eloquent; las pruebas de
 * derivados por tipo trabajan sobre secciones sin persistir.
 */
class ArmadorTest extends TestCase
{
    use RefreshDatabase;

    private function armador(): Armador
    {
        return new Armador();
    }

    private function seccion(TipoSeccion $tipo, array $contenido, string $titulo = 'Sección'): ReporteSeccion
    {
        return new ReporteSeccion([
            'tipo' => $tipo->value,
            'titulo' => $titulo,
            'orden' => 1,
            'contenido' => $contenido + EsquemaSeccion::vacio($tipo),
            'visible' => true,
        ]);
    }

    private function reporte(array $overrides = []): Reporte
    {
        return Reporte::create(array_merge([
            'cliente_id' => Cliente::factory()->create()->id,
            'area' => AreaReporte::Seo->value,
            'titulo' => 'Reporte mensual',
            'periodo_inicio' => '2026-08-01',
            'periodo_fin' => '2026-08-31',
            'estado' => EstadoReporte::Borrador->value,
        ], $overrides));
    }

    /**
     * Una fila de tabla en la forma NUEVA del esquema: los valores por columna
     * anidados bajo `valores` y los metadatos de la fila en la raíz.
     */
    private function fila(array $valores, bool $destacada = false, string $motivo = ''): array
    {
        return ['valores' => $valores, 'destacada' => $destacada, 'motivo' => $motivo];
    }

    /**
     * Tabla cuyos números están elegidos para que el CTR agregado y la media
     * de los CTR por fila NO coincidan (0.1 vs 0.4555), y lo mismo para la
     * posición ponderada frente a la media simple (27.3 vs 16.5).
     */
    private function tablaDeConsultas(): ReporteSeccion
    {
        return $this->seccion(TipoSeccion::Tabla, [
            'columnas' => [
                ['clave' => 'consulta', 'titulo' => 'Consulta', 'tipo' => 'texto'],
                ['clave' => 'clics', 'titulo' => 'Clics', 'tipo' => 'numero'],
                ['clave' => 'impresiones', 'titulo' => 'Impresiones', 'tipo' => 'numero'],
                ['clave' => 'ctr', 'titulo' => 'CTR', 'tipo' => 'porcentaje'],
                ['clave' => 'posicion', 'titulo' => 'Posición', 'tipo' => 'decimal'],
                ['clave' => 'intencion', 'titulo' => 'Intención', 'tipo' => 'texto'],
                ['clave' => 'calidad', 'titulo' => 'Calidad', 'tipo' => 'decimal'],
            ],
            'filas' => [
                $this->fila([
                    'consulta' => 'marca principal',
                    'clics' => 90, 'impresiones' => 100, 'ctr' => 0.9, 'posicion' => 3,
                    'intencion' => 'navegacional', 'calidad' => 10,
                ], destacada: true, motivo: 'Consulta de marca'),
                $this->fila([
                    'consulta' => 'cola larga',
                    'clics' => 10, 'impresiones' => 900, 'ctr' => 0.0111, 'posicion' => 30,
                    'intencion' => 'informacional', 'calidad' => 'n/d',
                ]),
            ],
            'filas_excluidas' => [
                $this->fila([
                    'consulta' => 'consulta de marca excluida',
                    'clics' => 5000, 'impresiones' => 5000, 'ctr' => 1.0, 'posicion' => 1,
                    'intencion' => 'navegacional', 'calidad' => 4,
                ], motivo: 'Tráfico de marca'),
            ],
            'totales' => [
                'clics' => 'suma',
                'impresiones' => 'suma',
                'ctr' => 'ctr',
                'posicion' => 'ponderado',
                'intencion' => 'suma',
                'calidad' => 'promedio',
            ],
        ], 'Consultas');
    }

    // --- tabla -------------------------------------------------------------

    public function test_tabla_suma_only_counts_included_rows(): void
    {
        $derivados = $this->armador()->armarSeccion($this->tablaDeConsultas())['derivados'];

        $this->assertSame(100.0, $derivados['totales']['clics']);
        $this->assertSame(1000.0, $derivados['totales']['impresiones']);
        $this->assertSame(2, $derivados['conteo']);
    }

    public function test_tabla_excluded_rows_are_totalled_apart_and_never_mixed_in(): void
    {
        $derivados = $this->armador()->armarSeccion($this->tablaDeConsultas())['derivados'];

        // Si las excluidas se colaran, clics valdría 5100 en vez de 100.
        $this->assertSame(100.0, $derivados['totales']['clics']);
        $this->assertSame(5000.0, $derivados['totales_excluidas']['clics']);
        $this->assertSame(5000.0, $derivados['totales_excluidas']['impresiones']);
        $this->assertSame(1, $derivados['conteo_excluidas']);
    }

    public function test_tabla_ctr_is_aggregate_clicks_over_impressions_not_the_average_of_row_ctrs(): void
    {
        $derivados = $this->armador()->armarSeccion($this->tablaDeConsultas())['derivados'];

        // 100 clics / 1000 impresiones = 0.1. La media de los CTR por fila
        // ((0.9 + 0.0111) / 2 = 0.45555) sería una cifra que no corresponde a
        // nada: por eso los datos están elegidos para que difieran.
        $this->assertSame(0.1, $derivados['totales']['ctr']);
        $this->assertNotEqualsWithDelta(0.45555, $derivados['totales']['ctr'], 0.0001);
    }

    public function test_tabla_ponderado_weights_by_impressions(): void
    {
        $derivados = $this->armador()->armarSeccion($this->tablaDeConsultas())['derivados'];

        // (3*100 + 30*900) / 1000 = 27.3, no la media simple 16.5.
        $this->assertEqualsWithDelta(27.3, $derivados['totales']['posicion'], 0.0001);
        $this->assertNotEqualsWithDelta(16.5, $derivados['totales']['posicion'], 0.0001);
    }

    public function test_tabla_column_without_numeric_values_totals_to_null(): void
    {
        $derivados = $this->armador()->armarSeccion($this->tablaDeConsultas())['derivados'];

        // 'intencion' solo tiene texto: no hay nada que sumar, y el total es
        // null (no 0, que se leería como "sumaron cero").
        $this->assertNull($derivados['totales']['intencion']);
        $this->assertNull($derivados['totales_excluidas']['intencion']);
    }

    public function test_tabla_non_numeric_cells_are_ignored_instead_of_counting_as_zero(): void
    {
        $derivados = $this->armador()->armarSeccion($this->tablaDeConsultas())['derivados'];

        // Valores de la columna: 10 y 'n/d'. Ignorando el texto la media es 10;
        // contándolo como cero saldría 5.
        $this->assertSame(10.0, $derivados['totales']['calidad']);
    }

    public function test_tabla_total_mode_ninguno_produces_no_entry(): void
    {
        $seccion = $this->seccion(TipoSeccion::Tabla, [
            'columnas' => [['clave' => 'clics', 'titulo' => 'Clics', 'tipo' => 'numero']],
            'filas' => [$this->fila(['clics' => 5])],
            'totales' => ['clics' => 'ninguno'],
        ]);

        $derivados = $this->armador()->armarSeccion($seccion)['derivados'];

        $this->assertArrayNotHasKey('clics', $derivados['totales']);
    }

    /**
     * Forma nueva de la fila: `valores` anidado y los metadatos (`destacada`,
     * `motivo`) en la raíz. Es lo que consumen las dos plantillas.
     */
    public function test_tabla_rows_are_normalised_to_valores_destacada_motivo(): void
    {
        $derivados = $this->armador()->armarSeccion($this->tablaDeConsultas())['derivados'];

        $this->assertSame(['valores', 'destacada', 'motivo'], array_keys($derivados['filas'][0]));
        $this->assertTrue($derivados['filas'][0]['destacada']);
        $this->assertSame('Consulta de marca', $derivados['filas'][0]['motivo']);
        $this->assertSame(90, $derivados['filas'][0]['valores']['clics']);

        $this->assertFalse($derivados['filas'][1]['destacada']);
        $this->assertSame('', $derivados['filas'][1]['motivo']);

        // `destacadas` es el conteo que el PDF usa para la nota al pie.
        $this->assertSame(1, $derivados['destacadas']);
    }

    /**
     * Un reporte guardado antes del cambio de esquema tiene las filas como
     * mapa plano. Debe seguir totalizando igual: si no, los reportes viejos
     * se rompen al reabrirlos.
     */
    public function test_tabla_tolerates_the_old_flat_row_shape(): void
    {
        $seccion = $this->seccion(TipoSeccion::Tabla, [
            'columnas' => [
                ['clave' => 'clics', 'titulo' => 'Clics', 'tipo' => 'numero'],
                ['clave' => 'impresiones', 'titulo' => 'Impresiones', 'tipo' => 'numero'],
            ],
            'filas' => [
                ['clics' => 90, 'impresiones' => 100],
                ['clics' => 10, 'impresiones' => 900],
            ],
            'totales' => ['clics' => 'suma', 'impresiones' => 'suma'],
        ]);

        $derivados = $this->armador()->armarSeccion($seccion)['derivados'];

        $this->assertSame(100.0, $derivados['totales']['clics']);
        $this->assertSame(1000.0, $derivados['totales']['impresiones']);
        $this->assertSame(['valores', 'destacada', 'motivo'], array_keys($derivados['filas'][0]));
    }

    /**
     * B14 · Una columna cuya clave es un número («2024», un año) conserva su
     * modo de total. json_decode convierte esa clave del mapa `totales` en un
     * int de PHP, y el filtro `is_string($clave)` la descartaba en silencio:
     * la columna perdía el pie de totales sin ningún error.
     */
    public function test_tabla_column_with_a_numeric_key_keeps_its_total_mode(): void
    {
        $seccion = $this->seccion(TipoSeccion::Tabla, [
            'columnas' => [
                ['clave' => 'canal', 'titulo' => 'Canal', 'tipo' => 'texto'],
                ['clave' => '2024', 'titulo' => 'Sesiones 2024', 'tipo' => 'numero'],
                ['clave' => '2025', 'titulo' => 'Sesiones 2025', 'tipo' => 'numero'],
            ],
            // Tal como llega de json_decode(..., true): las claves numéricas
            // son int, no string.
            'filas' => [
                $this->fila(['canal' => 'Orgánico', 2024 => 100, 2025 => 150]),
                $this->fila(['canal' => 'Directo', 2024 => 40, 2025 => 60]),
            ],
            'totales' => [2024 => 'suma', 2025 => 'suma'],
        ]);

        $derivados = $this->armador()->armarSeccion($seccion)['derivados'];

        $this->assertSame(['2024' => 'suma', '2025' => 'suma'], $derivados['modos']);
        $this->assertSame(140.0, $derivados['totales']['2024']);
        $this->assertSame(210.0, $derivados['totales']['2025']);
    }

    // --- modoEfectivo() -------------------------------------------------------

    /**
     * El Excel escribe una FÓRMULA por columna leyendo `modos_efectivos`: si el
     * motor degradó a media aritmética y el Excel escribiera igualmente el CTR
     * agregado, los dos documentos mostrarían cifras distintas de la misma
     * tabla. Estos tests fijan cada uno de los tres fallbacks.
     */
    public function test_modo_efectivo_returns_the_declared_mode_when_nothing_degrades(): void
    {
        $armador = $this->armador();
        $seccion = $this->tablaDeConsultas();
        $contenido = $seccion->contenido;
        $filas = $contenido['filas'];

        $this->assertSame('suma', $armador->modoEfectivo($filas, $contenido, 'clics', 'suma'));
        $this->assertSame('promedio', $armador->modoEfectivo($filas, $contenido, 'calidad', 'promedio'));
        $this->assertSame('ctr', $armador->modoEfectivo($filas, $contenido, 'ctr', 'ctr'));
        $this->assertSame('ponderado', $armador->modoEfectivo($filas, $contenido, 'posicion', 'ponderado'));
        $this->assertSame('ninguno', $armador->modoEfectivo($filas, $contenido, 'clics', 'ninguno'));
    }

    /** Fallback 1: `ctr` sin las columnas clics e impresiones. */
    public function test_modo_efectivo_degrades_ctr_to_promedio_without_clics_and_impresiones_columns(): void
    {
        $contenido = [
            'columnas' => [['clave' => 'ratio', 'titulo' => 'Ratio', 'tipo' => 'porcentaje']],
            'filas' => [$this->fila(['ratio' => 0.5]), $this->fila(['ratio' => 0.1])],
        ];

        $this->assertSame(
            'promedio',
            $this->armador()->modoEfectivo($contenido['filas'], $contenido, 'ratio', 'ctr')
        );
    }

    /** Fallback 2: `ctr` con las columnas puestas pero impresiones a cero. */
    public function test_modo_efectivo_degrades_ctr_to_promedio_when_impresiones_add_up_to_zero(): void
    {
        $contenido = [
            'columnas' => [
                ['clave' => 'clics', 'titulo' => 'Clics', 'tipo' => 'numero'],
                ['clave' => 'impresiones', 'titulo' => 'Impresiones', 'tipo' => 'numero'],
                ['clave' => 'ctr', 'titulo' => 'CTR', 'tipo' => 'porcentaje'],
            ],
            'filas' => [
                $this->fila(['clics' => 0, 'impresiones' => 0, 'ctr' => 0.2]),
                $this->fila(['clics' => 0, 'impresiones' => 0, 'ctr' => 0.4]),
            ],
        ];

        $armador = $this->armador();

        $this->assertSame('promedio', $armador->modoEfectivo($contenido['filas'], $contenido, 'ctr', 'ctr'));

        // Y el total que sale es esa media, no una división por cero.
        $seccion = $this->seccion(TipoSeccion::Tabla, $contenido + ['totales' => ['ctr' => 'ctr']]);
        $derivados = $armador->armarSeccion($seccion)['derivados'];

        $this->assertSame('promedio', $derivados['modos_efectivos']['ctr']);
        $this->assertEqualsWithDelta(0.3, $derivados['totales']['ctr'], 0.0001);
    }

    /** Fallback 3: `ponderado` sin ninguna fila con peso utilizable. */
    public function test_modo_efectivo_degrades_ponderado_to_promedio_without_usable_weights(): void
    {
        $contenido = [
            'columnas' => [
                ['clave' => 'impresiones', 'titulo' => 'Impresiones', 'tipo' => 'numero'],
                ['clave' => 'posicion', 'titulo' => 'Posición', 'tipo' => 'decimal'],
            ],
            'filas' => [
                $this->fila(['impresiones' => null, 'posicion' => 4]),
                $this->fila(['impresiones' => 0, 'posicion' => 10]),
            ],
        ];

        $armador = $this->armador();

        $this->assertSame('promedio', $armador->modoEfectivo($contenido['filas'], $contenido, 'posicion', 'ponderado'));

        $seccion = $this->seccion(TipoSeccion::Tabla, $contenido + ['totales' => ['posicion' => 'ponderado']]);
        $derivados = $armador->armarSeccion($seccion)['derivados'];

        $this->assertSame('promedio', $derivados['modos_efectivos']['posicion']);
        $this->assertEqualsWithDelta(7.0, $derivados['totales']['posicion'], 0.0001);
    }

    public function test_tabla_exposes_modos_and_modos_efectivos_side_by_side(): void
    {
        $derivados = $this->armador()->armarSeccion($this->tablaDeConsultas())['derivados'];

        // `modos` es lo declarado (sin los `ninguno`); `modos_efectivos` es lo
        // aplicado. Aquí no degrada nada, así que coinciden.
        $this->assertSame('ctr', $derivados['modos']['ctr']);
        $this->assertSame('ctr', $derivados['modos_efectivos']['ctr']);
        $this->assertSame('ponderado', $derivados['modos_efectivos']['posicion']);
        $this->assertSame(array_keys($derivados['modos']), array_keys($derivados['modos_efectivos']));
    }

    // --- plan ---------------------------------------------------------------

    public function test_plan_score_is_impacto_over_esfuerzo_rounded_to_two_decimals(): void
    {
        $seccion = $this->seccion(TipoSeccion::Plan, [
            'acciones' => [
                ['prioridad' => 'p1', 'accion' => 'Cinco entre tres', 'impacto' => 5, 'esfuerzo' => 3],
                ['prioridad' => 'p1', 'accion' => 'Cuatro entre dos', 'impacto' => 4, 'esfuerzo' => 2],
            ],
        ]);

        $acciones = $this->armador()->armarSeccion($seccion)['derivados']['acciones'];

        $porAccion = collect($acciones)->keyBy('accion');
        $this->assertSame(1.67, $porAccion['Cinco entre tres']['score']);
        $this->assertSame(2.0, $porAccion['Cuatro entre dos']['score']);
    }

    public function test_plan_sorts_by_prioridad_then_by_score_descending(): void
    {
        $seccion = $this->seccion(TipoSeccion::Plan, [
            'acciones' => [
                ['prioridad' => 'p2', 'accion' => 'P2 con score altísimo', 'impacto' => 5, 'esfuerzo' => 1],
                ['prioridad' => 'p1', 'accion' => 'P1 con score bajo', 'impacto' => 1, 'esfuerzo' => 5],
                ['prioridad' => 'p1', 'accion' => 'P1 con score alto', 'impacto' => 5, 'esfuerzo' => 2],
                ['prioridad' => 'p0', 'accion' => 'P0', 'impacto' => 2, 'esfuerzo' => 2],
            ],
        ]);

        $derivados = $this->armador()->armarSeccion($seccion)['derivados'];

        // La prioridad manda sobre el score: un P2 con score 5 va después de un
        // P1 con score 0.2, porque la prioridad es una decisión, no un cálculo.
        $this->assertSame([
            'P0',
            'P1 con score alto',
            'P1 con score bajo',
            'P2 con score altísimo',
        ], array_column($derivados['acciones'], 'accion'));

        $this->assertSame(['p0' => 1, 'p1' => 2, 'p2' => 1, 'p3' => 0], $derivados['por_prioridad']);
    }

    public function test_plan_with_zero_esfuerzo_scores_zero_instead_of_dividing_by_zero(): void
    {
        $seccion = $this->seccion(TipoSeccion::Plan, [
            'acciones' => [['prioridad' => 'p0', 'accion' => 'Sin esfuerzo', 'impacto' => 4, 'esfuerzo' => 0]],
        ]);

        $acciones = $this->armador()->armarSeccion($seccion)['derivados']['acciones'];

        $this->assertSame(0.0, $acciones[0]['score']);
    }

    // --- hallazgos -----------------------------------------------------------

    public function test_hallazgos_are_sorted_by_severidad_and_indexed_from_one(): void
    {
        $seccion = $this->seccion(TipoSeccion::Hallazgos, [
            'items' => [
                ['titulo' => 'Informativo', 'severidad' => 'informativo'],
                ['titulo' => 'Crítico', 'severidad' => 'critico'],
                ['titulo' => 'Medio', 'severidad' => 'medio'],
                ['titulo' => 'Alto', 'severidad' => 'alto'],
            ],
        ]);

        $derivados = $this->armador()->armarSeccion($seccion)['derivados'];

        $this->assertSame(
            ['Crítico', 'Alto', 'Medio', 'Informativo'],
            array_column($derivados['items'], 'titulo')
        );
        $this->assertSame([1, 2, 3, 4], array_column($derivados['items'], 'indice'));
        $this->assertSame('Crítico', $derivados['items'][0]['severidad_label']);
        $this->assertSame(['critico' => 1, 'alto' => 1, 'medio' => 1, 'informativo' => 1], $derivados['por_severidad']);
    }

    // --- serie ---------------------------------------------------------------

    /**
     * Serie con doble eje: clics a la izquierda (máximo 45) e impresiones a la
     * derecha (máximo 300). Las escalas son deliberadamente distintas para que
     * un escalado contra el máximo global se note.
     */
    private function serieDeTrafico(): ReporteSeccion
    {
        return $this->seccion(TipoSeccion::Serie, [
            'series' => [
                ['clave' => 'clics', 'titulo' => 'Clics', 'eje' => 'izq', 'color' => '#1f2937'],
                ['clave' => 'impresiones', 'titulo' => 'Impresiones', 'eje' => 'der', 'color' => '#9ca3af'],
            ],
            'filas' => [
                ['x' => '2026-08-01', 'valores' => ['clics' => 10, 'impresiones' => 300]],
                ['x' => '2026-08-02', 'valores' => ['clics' => 45, 'impresiones' => 120]],
                ['x' => '2026-08-03', 'valores' => ['clics' => 5, 'impresiones' => 80]],
                ['x' => '2026-08-04', 'valores' => ['clics' => 0, 'impresiones' => 0]],
            ],
        ], 'Tráfico diario');
    }

    public function test_serie_computes_maximos_and_totales_per_series(): void
    {
        $seccion = $this->seccion(TipoSeccion::Serie, [
            'series' => [
                ['clave' => 'clics', 'titulo' => 'Clics'],
                ['clave' => 'impresiones', 'titulo' => 'Impresiones'],
            ],
            'filas' => [
                ['x' => '2026-08-01', 'valores' => ['clics' => 10, 'impresiones' => 300]],
                ['x' => '2026-08-02', 'valores' => ['clics' => 45, 'impresiones' => 120]],
                ['x' => '2026-08-03', 'valores' => ['clics' => 5, 'impresiones' => 80]],
            ],
        ]);

        $derivados = $this->armador()->armarSeccion($seccion)['derivados'];

        $this->assertSame(45.0, $derivados['maximos']['clics']);
        $this->assertSame(300.0, $derivados['maximos']['impresiones']);
        $this->assertSame(60.0, $derivados['totales']['clics']);
        $this->assertSame(500.0, $derivados['totales']['impresiones']);
        $this->assertSame(3, $derivados['conteo']);
    }

    public function test_serie_groups_series_by_eje_with_its_own_maximum(): void
    {
        $derivados = $this->armador()->armarSeccion($this->serieDeTrafico())['derivados'];

        $this->assertSame(['clics'], $derivados['ejes']['izq']['series']);
        $this->assertSame(45.0, $derivados['ejes']['izq']['maximo']);
        $this->assertSame(['impresiones'], $derivados['ejes']['der']['series']);
        $this->assertSame(300.0, $derivados['ejes']['der']['maximo']);
    }

    /** Una serie sin `eje` declarado cae en el izquierdo, que es el del diseño. */
    public function test_serie_without_declared_eje_falls_back_to_the_left_one(): void
    {
        $seccion = $this->seccion(TipoSeccion::Serie, [
            'series' => [
                ['clave' => 'clics', 'titulo' => 'Clics'],
                ['clave' => 'sesiones', 'titulo' => 'Sesiones', 'eje' => 'inventado'],
            ],
            'filas' => [['x' => '2026-08-01', 'valores' => ['clics' => 10, 'sesiones' => 20]]],
        ]);

        $derivados = $this->armador()->armarSeccion($seccion)['derivados'];

        $this->assertSame(['clics', 'sesiones'], $derivados['ejes']['izq']['series']);
        $this->assertSame([], $derivados['ejes']['der']['series']);
    }

    /**
     * Las alturas se escalan contra el máximo del PROPIO eje. Ese es el sentido
     * del doble eje: 45 clics deben llenar la caja igual que 300 impresiones,
     * porque no comparten escala.
     */
    public function test_serie_bar_heights_are_scaled_against_the_maximum_of_their_own_axis(): void
    {
        $derivados = $this->armador()->armarSeccion($this->serieDeTrafico())['derivados'];
        $barras = $derivados['barras'];

        $alto = Armador::ALTO_GRAFICO_PX;

        // Máximo del eje izquierdo (45 clics) → barra a tope.
        $this->assertSame($alto, $barras[1]['valores']['clics']['altura']);
        // Máximo del eje derecho (300 impresiones) → también a tope.
        $this->assertSame($alto, $barras[0]['valores']['impresiones']['altura']);

        // 10 clics sobre un máximo de 45: escalado por su eje, no por el global.
        $this->assertSame((int) round(10 / 45 * $alto), $barras[0]['valores']['clics']['altura']);
        $this->assertNotSame((int) round(10 / 300 * $alto), $barras[0]['valores']['clics']['altura']);

        $this->assertSame('2026-08-01', $barras[0]['x']);
        $this->assertSame(10.0, $barras[0]['valores']['clics']['valor']);
    }

    public function test_serie_bar_height_is_zero_only_for_a_zero_value(): void
    {
        $barras = $this->armador()->armarSeccion($this->serieDeTrafico())['derivados']['barras'];

        $this->assertSame(0, $barras[3]['valores']['clics']['altura']);
        $this->assertSame(0.0, $barras[3]['valores']['clics']['valor']);
    }

    /**
     * Un valor positivo minúsculo redondea a 0 px y desaparecería del gráfico,
     * que se leería como "no hubo dato". El mínimo de 1 px lo impide.
     */
    public function test_serie_a_positive_value_never_draws_a_zero_pixel_bar(): void
    {
        $seccion = $this->seccion(TipoSeccion::Serie, [
            'series' => [['clave' => 'clics', 'titulo' => 'Clics']],
            'filas' => [
                ['x' => '2026-08-01', 'valores' => ['clics' => 1000]],
                // 1 / 1000 * 216 = 0.216, que redondea a 0.
                ['x' => '2026-08-02', 'valores' => ['clics' => 1]],
            ],
        ]);

        $barras = $this->armador()->armarSeccion($seccion)['derivados']['barras'];

        $this->assertSame(1, $barras[1]['valores']['clics']['altura']);
    }

    public function test_serie_labels_at_most_seven_x_marks(): void
    {
        $filas = [];
        for ($dia = 1; $dia <= 20; $dia++) {
            $filas[] = ['x' => sprintf('2026-08-%02d', $dia), 'valores' => ['clics' => $dia]];
        }

        $seccion = $this->seccion(TipoSeccion::Serie, [
            'series' => [['clave' => 'clics', 'titulo' => 'Clics']],
            'filas' => $filas,
        ]);

        $etiquetas = $this->armador()->armarSeccion($seccion)['derivados']['etiquetas_x'];

        $this->assertLessThanOrEqual(7, count($etiquetas));
        $this->assertSame(0, $etiquetas[0]['indice']);
        $this->assertSame('2026-08-01', $etiquetas[0]['label']);
        $this->assertSame(19, end($etiquetas)['indice']);
        $this->assertSame('2026-08-20', end($etiquetas)['label']);
    }

    public function test_serie_aggregates_by_iso_week_when_the_x_axis_holds_dates(): void
    {
        $derivados = $this->armador()->armarSeccion($this->serieDeTrafico())['derivados'];
        $agregado = $derivados['agregado'];

        // 1 y 2 de agosto de 2026 caen en la semana ISO 31; 3 y 4, en la 32.
        $this->assertCount(2, $agregado);
        $this->assertSame(['Sem 31', 'Sem 32'], array_column($agregado, 'label'));

        $this->assertSame(55.0, $agregado[0]['valores']['clics']);
        $this->assertSame(420.0, $agregado[0]['valores']['impresiones']);
        $this->assertSame(2, $agregado[0]['dias']);

        // El 4 de agosto no tiene ningún valor > 0: la semana 32 solo cuenta
        // un día con datos, aunque tenga dos filas.
        $this->assertSame(1, $agregado[1]['dias']);

        // `peso` se mide sobre la serie del eje derecho: 420 / 500.
        $this->assertSame(0.84, $agregado[0]['peso']);
        $this->assertSame(0.16, $agregado[1]['peso']);
    }

    /**
     * Media agrupación es peor que ninguna: si el eje X no son fechas
     * («Sem 3», «Top 10»), el agregado semanal sale vacío en vez de inventado.
     */
    public function test_serie_aggregate_is_empty_when_the_x_axis_is_not_made_of_dates(): void
    {
        $seccion = $this->seccion(TipoSeccion::Serie, [
            'series' => [['clave' => 'clics', 'titulo' => 'Clics']],
            'filas' => [
                ['x' => 'Semana 1', 'valores' => ['clics' => 10]],
                ['x' => 'Semana 2', 'valores' => ['clics' => 20]],
            ],
        ]);

        $this->assertSame([], $this->armador()->armarSeccion($seccion)['derivados']['agregado']);
    }

    /** Basta con que UNA fila no parsee como fecha para descartar el agregado. */
    public function test_serie_aggregate_is_empty_if_a_single_row_is_not_a_date(): void
    {
        $seccion = $this->seccion(TipoSeccion::Serie, [
            'series' => [['clave' => 'clics', 'titulo' => 'Clics']],
            'filas' => [
                ['x' => '2026-08-01', 'valores' => ['clics' => 10]],
                ['x' => 'Total', 'valores' => ['clics' => 20]],
            ],
        ]);

        $this->assertSame([], $this->armador()->armarSeccion($seccion)['derivados']['agregado']);
    }

    // --- ficha ----------------------------------------------------------------

    /**
     * Ficha de auditoría con dos dimensiones. El registro «Home» tiene un campo
     * crítico dentro de una dimensión por lo demás correcta; «Contacto» no
     * tiene ningún estado puesto.
     */
    private function fichaDeAuditoria(): ReporteSeccion
    {
        return $this->seccion(TipoSeccion::Ficha, [
            'dimensiones' => [
                ['clave' => 'tecnico', 'titulo' => 'Técnico'],
                ['clave' => 'contenido', 'titulo' => 'Contenido'],
            ],
            'campos' => [
                ['clave' => 'indexable', 'titulo' => 'Indexable', 'dimension' => 'tecnico'],
                ['clave' => 'canonical', 'titulo' => 'Canonical', 'dimension' => 'tecnico'],
                ['clave' => 'title', 'titulo' => 'Title', 'dimension' => 'contenido'],
                // Campo sin dimensión: solo se ve en el detalle, nunca tiñe el
                // semáforo.
                ['clave' => 'notas', 'titulo' => 'Notas'],
            ],
            'registros' => [
                [
                    'titulo' => 'Home',
                    'valores' => [
                        'indexable' => ['valor' => 'Sí', 'estado' => 'ok'],
                        'canonical' => ['valor' => 'Cruzado', 'estado' => 'critico'],
                        'title' => ['valor' => '58 caracteres', 'estado' => 'revisar'],
                        'notas' => ['valor' => 'Revisada a mano', 'estado' => 'critico'],
                    ],
                    'veredicto' => 'Corregir canonical',
                    'estado' => 'critico',
                ],
                [
                    'titulo' => 'Contacto',
                    'valores' => [
                        'indexable' => ['valor' => 'Sí', 'estado' => null],
                        'canonical' => ['valor' => 'Propio', 'estado' => 'nd'],
                        'title' => ['valor' => '', 'estado' => ''],
                    ],
                    'veredicto' => '',
                ],
            ],
        ], 'Auditoría de plantillas');
    }

    public function test_ficha_semaforo_lets_the_worst_state_win_within_a_dimension(): void
    {
        $semaforo = $this->armador()->armarSeccion($this->fichaDeAuditoria())['derivados']['semaforo'];

        $this->assertSame(['tecnico', 'contenido'], array_column($semaforo['dimensiones'], 'clave'));
        $this->assertSame('Home', $semaforo['filas'][0]['titulo']);
        // ok + critico → critico: pintarlo verde por mayoría escondería el
        // problema en la vista que existe para encontrarlo.
        $this->assertSame('critico', $semaforo['filas'][0]['estados']['tecnico']);
        $this->assertSame('revisar', $semaforo['filas'][0]['estados']['contenido']);
    }

    public function test_ficha_semaforo_is_nd_when_no_field_of_the_dimension_has_a_state(): void
    {
        $semaforo = $this->armador()->armarSeccion($this->fichaDeAuditoria())['derivados']['semaforo'];

        $this->assertSame('Contacto', $semaforo['filas'][1]['titulo']);
        $this->assertSame('nd', $semaforo['filas'][1]['estados']['tecnico']);
        $this->assertSame('nd', $semaforo['filas'][1]['estados']['contenido']);
    }

    /** Un campo sin dimensión no puede teñir ninguna columna del semáforo. */
    public function test_ficha_field_without_dimension_stays_out_of_the_semaforo(): void
    {
        $seccion = $this->seccion(TipoSeccion::Ficha, [
            'dimensiones' => [['clave' => 'tecnico', 'titulo' => 'Técnico']],
            'campos' => [
                ['clave' => 'indexable', 'titulo' => 'Indexable', 'dimension' => 'tecnico'],
                ['clave' => 'notas', 'titulo' => 'Notas'],
            ],
            'registros' => [[
                'titulo' => 'Home',
                'valores' => [
                    'indexable' => ['valor' => 'Sí', 'estado' => 'ok'],
                    'notas' => ['valor' => 'Algo', 'estado' => 'critico'],
                ],
            ]],
        ]);

        $semaforo = $this->armador()->armarSeccion($seccion)['derivados']['semaforo'];

        $this->assertSame('ok', $semaforo['filas'][0]['estados']['tecnico']);
    }

    /** Sin dimensiones declaradas no hay semáforo: el PDF cae a las fichas sueltas. */
    public function test_ficha_without_dimensiones_produces_an_empty_semaforo(): void
    {
        $seccion = $this->seccion(TipoSeccion::Ficha, [
            'campos' => [['clave' => 'indexable', 'titulo' => 'Indexable']],
            'registros' => [['titulo' => 'Home', 'valores' => ['indexable' => ['valor' => 'Sí', 'estado' => 'ok']]]],
        ]);

        $this->assertSame([], $this->armador()->armarSeccion($seccion)['derivados']['semaforo']);
    }

    // --- kpis ------------------------------------------------------------------

    public function test_kpis_are_split_into_principales_and_secundarios_by_the_destacado_flag(): void
    {
        $seccion = $this->seccion(TipoSeccion::Kpis, [
            'items' => [
                ['label' => 'Clics', 'valor' => '1.200', 'destacado' => true],
                ['label' => 'Impresiones', 'valor' => '40.000', 'destacado' => false],
                // Sin flag: principal, que es como se comportaba antes de que
                // el flag existiera.
                ['label' => 'CTR', 'valor' => '3,00%'],
                ['label' => 'Posición', 'valor' => '12,4', 'destacado' => false, 'desactualizado' => '18 ago 2026'],
            ],
        ]);

        $derivados = $this->armador()->armarSeccion($seccion)['derivados'];

        $this->assertSame(['Clics', 'CTR'], array_column($derivados['principales'], 'label'));
        $this->assertSame(['Impresiones', 'Posición'], array_column($derivados['secundarios'], 'label'));
        $this->assertSame('18 ago 2026', $derivados['secundarios'][1]['desactualizado']);
    }

    // --- armar(): solo secciones visibles -------------------------------------

    public function test_armar_includes_only_visible_sections_in_order(): void
    {
        $reporte = $this->reporte(['titulo' => 'Reporte de agosto']);

        $reporte->secciones()->create([
            'tipo' => 'texto', 'titulo' => 'Visible primera', 'orden' => 1,
            'contenido' => EsquemaSeccion::vacio(TipoSeccion::Texto), 'visible' => true,
        ]);
        $reporte->secciones()->create([
            'tipo' => 'kpis', 'titulo' => 'Oculta', 'orden' => 2,
            'contenido' => EsquemaSeccion::vacio(TipoSeccion::Kpis), 'visible' => false,
        ]);
        $reporte->secciones()->create([
            'tipo' => 'plan', 'titulo' => 'Visible segunda', 'orden' => 3,
            'contenido' => EsquemaSeccion::vacio(TipoSeccion::Plan), 'visible' => true,
        ]);

        $datos = $this->armador()->armar($reporte);

        $this->assertSame(
            ['Visible primera', 'Visible segunda'],
            array_column($datos['secciones'], 'titulo')
        );
        $this->assertSame('Reporte de agosto', $datos['reporte']['titulo']);
        $this->assertSame('seo', $datos['reporte']['area']);
        // Periodo inclusivo: del 1 al 31 de agosto son 31 días.
        $this->assertSame(31, $datos['reporte']['dias_periodo']);
    }

    public function test_armar_returns_tipo_titulo_rotulo_aviso_contenido_and_derivados_per_section(): void
    {
        $reporte = $this->reporte();
        $tabla = $this->tablaDeConsultas();
        $reporte->secciones()->create([
            'tipo' => $tabla->tipo->value,
            'titulo' => 'Consultas',
            'rotulo' => 'SECCIÓN 2',
            'orden' => 1,
            'contenido' => $tabla->contenido,
            'aviso' => ['texto' => 'Dato desactualizado', 'fecha' => '18 ago 2026'],
            'visible' => true,
        ]);

        $datos = $this->armador()->armar($reporte);

        $this->assertCount(1, $datos['secciones']);
        $this->assertSame(
            ['tipo', 'titulo', 'rotulo', 'aviso', 'contenido', 'derivados'],
            array_keys($datos['secciones'][0])
        );
        $this->assertSame('tabla', $datos['secciones'][0]['tipo']);
        $this->assertSame('SECCIÓN 2', $datos['secciones'][0]['rotulo']);
        $this->assertSame('Dato desactualizado', $datos['secciones'][0]['aviso']['texto']);
        $this->assertSame(100.0, $datos['secciones'][0]['derivados']['totales']['clics']);
    }

    public function test_armar_reports_a_section_without_rotulo_or_aviso_as_null(): void
    {
        $reporte = $this->reporte();
        $reporte->secciones()->create([
            'tipo' => 'texto', 'titulo' => 'Alcance', 'orden' => 1,
            'contenido' => EsquemaSeccion::vacio(TipoSeccion::Texto), 'visible' => true,
        ]);

        $seccion = $this->armador()->armar($reporte)['secciones'][0];

        $this->assertNull($seccion['rotulo']);
        $this->assertNull($seccion['aviso']);
    }

    // --- formatear() -----------------------------------------------------------

    /**
     * En las celdas de datos el diseño pide «n/d» explícito y no el em dash que
     * usa el resto del proyecto: un guion largo en una columna numérica se lee
     * como un cero o como un signo menos truncado.
     */
    public function test_formatear_renders_the_ausente_marker_for_null_and_empty_values(): void
    {
        $armador = $this->armador();

        $this->assertSame(Armador::AUSENTE, $armador->formatear(null, 'numero'));
        $this->assertSame('n/d', $armador->formatear('', 'texto'));
        $this->assertSame('n/d', $armador->formatear(null, 'texto'));
        $this->assertSame('n/d', $armador->formatear([], 'numero'));
    }

    public function test_formatear_renders_each_declared_format(): void
    {
        $armador = $this->armador();

        $this->assertSame('1,068', $armador->formatear(1068, 'numero'));
        $this->assertSame('4.2', $armador->formatear(4.23, 'decimal'));
        $this->assertSame('3.09%', $armador->formatear(0.0309, 'porcentaje'));
        $this->assertSame('$980.00', $armador->formatear(980, 'moneda'));
        $this->assertSame('n/d', $armador->formatear('n/d', 'numero'));
    }

    /** `nivel` es la escala cualitativa del diseño: siempre en mayúsculas. */
    public function test_formatear_upcases_the_nivel_format(): void
    {
        $armador = $this->armador();

        $this->assertSame('ALTA', $armador->formatear('alta', 'nivel'));
        $this->assertSame('MEDIA', $armador->formatear(' Media ', 'nivel'));
        $this->assertSame('n/d', $armador->formatear(null, 'nivel'));
    }

    /**
     * B12 · Un CTR de 1.0 es el 100 %: una fila con tantos clics como
     * impresiones. Con el intervalo abierto caía del lado "ya viene en puntos"
     * y se imprimía como 1.00 %, que es un error de dos órdenes de magnitud.
     */
    public function test_formatear_prints_a_ctr_of_one_as_one_hundred_percent(): void
    {
        $armador = $this->armador();

        $this->assertSame('100.00%', $armador->formatear(1.0, 'porcentaje'));
        $this->assertSame('100.00%', $armador->formatear(1, 'porcentaje'));
        $this->assertSame(100.0, $armador->aPorcentaje(1.0));

        // Y lo que ya viene en puntos sigue respetándose.
        $this->assertSame('4.32%', $armador->formatear(4.32, 'porcentaje'));
        $this->assertSame('0.00%', $armador->formatear(0, 'porcentaje'));
    }
}
