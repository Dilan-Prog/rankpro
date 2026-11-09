<?php

namespace App\Support\Reportes;

use App\Enums\AreaReporte;
use App\Enums\TipoSeccion;

/**
 * Secciones que se siembran al crear un reporte de cada área, ya con las
 * columnas configuradas. Es lo que hace viable la captura manual: el equipo
 * abre el reporte y encuentra la estructura puesta, solo pega y redacta.
 *
 * Añadir un área nueva se hace aquí y en App\Enums\AreaReporte; no requiere
 * migraciones ni tipos de sección nuevos.
 */
class Plantillas
{
    /** Las seis dimensiones del semáforo de auditoría on-page del diseño. */
    private const DIMENSIONES_ONPAGE = [
        ['clave' => 'title', 'titulo' => 'Title'],
        ['clave' => 'meta', 'titulo' => 'Meta'],
        ['clave' => 'encabezados', 'titulo' => 'Encab.'],
        ['clave' => 'schema', 'titulo' => 'Schema'],
        ['clave' => 'contenido', 'titulo' => 'Contenido'],
        ['clave' => 'ttfb', 'titulo' => 'TTFB'],
    ];

    /**
     * Clave estable y marca `avanzada` de cada sección por título. Se resuelve por título
     * para no tocar la definición de las secciones; los títulos son únicos dentro de cada área.
     * Las avanzadas se ofrecen como casillas opcionales en "Generar reporte del mes";
     * en el reporte personalizado se siembran todas, como siempre.
     */
    private const META = [
        'seo' => [
            'Alcance del reporte' => ['alcance', false],
            'Indicadores del periodo' => ['kpis', false],
            'Hallazgos críticos' => ['hallazgos', false],
            'Tendencia diaria' => ['tendencia_diaria', true],
            'Consultas' => ['consultas', true],
            'Rendimiento por página' => ['paginas', true],
            'Países' => ['paises', true],
            'Dispositivos' => ['dispositivos', true],
            'Cobertura de indexación' => ['indexacion', true],
            'Auditoría on-page' => ['onpage', true],
            'Análisis y riesgos' => ['analisis', false],
            'Plan de acción' => ['plan', false],
            'Metodología y fuentes' => ['metodologia', false],
        ],
        'ads' => [
            'Alcance del reporte' => ['alcance', false],
            'Indicadores del periodo' => ['kpis', false],
            'Hallazgos y oportunidades' => ['hallazgos', false],
            'Tendencia diaria' => ['tendencia_diaria', true],
            'Rendimiento por campaña' => ['campanas', false],
            'Palabras clave y públicos' => ['palabras', true],
            'Creativos' => ['creativos', true],
            'Conversiones por etapa del embudo' => ['embudo', true],
            'Análisis y riesgos' => ['analisis', false],
            'Plan de optimización' => ['plan', false],
            'Metodología y fuentes' => ['metodologia', false],
        ],
        'web' => [
            'Alcance del reporte' => ['alcance', false],
            'Indicadores del periodo' => ['kpis', false],
            'Hallazgos técnicos' => ['hallazgos', false],
            'Tiempo de carga y disponibilidad' => ['tiempo_carga', true],
            'Core Web Vitals por plantilla' => ['cwv', false],
            'Auditoría técnica por página' => ['auditoria', true],
            'Incidencias detectadas' => ['incidencias', true],
            'Análisis y riesgos' => ['analisis', false],
            'Plan de mejoras' => ['plan', false],
            'Metodología y fuentes' => ['metodologia', false],
        ],
    ];

    /** Orden de "Generar reporte del mes"; lo que no está en la plantilla clásica se construye en paraMes(). */
    private const ORDEN_MES = [
        'seo' => ['resumen', 'alcance', 'kpis', 'serie_mensual', 'movimientos', 'hallazgos', '@avanzadas', 'analisis', 'plan', 'metodologia'],
        'ads' => ['resumen', 'alcance', 'kpis', 'serie_mensual', 'campanas', 'hallazgos', '@avanzadas', 'analisis', 'plan', 'metodologia'],
        'web' => ['resumen', 'alcance', 'kpis', 'serie_mensual', 'metricas', 'hallazgos', 'cwv', '@avanzadas', 'analisis', 'plan', 'metodologia'],
    ];

    /** Qué clave de Precarga llena cada sección del modo mensual. */
    private const PRECARGA = [
        'resumen' => 'resumen',
        'kpis' => 'kpis',
        'serie_mensual' => 'serie',
        'movimientos' => 'movimientos',
        'campanas' => 'campanas',
        'metricas' => 'metricas',
    ];

    /**
     * Plantilla clásica (reporte personalizado): TODAS las secciones, vacías.
     *
     * @return array<int, array{tipo: TipoSeccion, titulo: string, contenido: array<string, mixed>, clave: ?string, avanzada: bool}>
     */
    public static function para(AreaReporte $area): array
    {
        $secciones = match ($area) {
            AreaReporte::Seo => self::seo(),
            AreaReporte::Ads => self::ads(),
            AreaReporte::Web => self::web(),
        };

        return array_map(function (array $seccion) use ($area) {
            [$clave, $avanzada] = self::META[$area->value][$seccion['titulo']] ?? [null, false];

            return $seccion + ['clave' => $clave, 'avanzada' => $avanzada];
        }, $secciones);
    }

    /**
     * Secciones opcionales que se ofrecen como casillas en "Generar reporte del mes".
     *
     * @return array<string, string> clave => título
     */
    public static function avanzadas(AreaReporte $area): array
    {
        $salida = [];
        foreach (self::para($area) as $seccion) {
            if ($seccion['avanzada']) {
                $salida[$seccion['clave']] = $seccion['titulo'];
            }
        }

        return $salida;
    }

    /**
     * Plantilla de "Generar reporte del mes": resumen para el cliente, indicadores y gráfica
     * precargados, lo básico del área y SOLO las avanzadas elegidas. Cada sección trae
     * `precarga` (clave que llena App\Support\Reportes\Precarga\*) o null si es de captura manual.
     *
     * @param  array<int, string>  $avanzadas  claves de avanzadas elegidas
     * @return array<int, array{tipo: TipoSeccion, titulo: string, contenido: array<string, mixed>, clave: ?string, avanzada: bool, precarga: ?string}>
     */
    public static function paraMes(AreaReporte $area, array $avanzadas = []): array
    {
        $base = collect(self::para($area))->keyBy('clave');
        $salida = [];

        foreach (self::ORDEN_MES[$area->value] as $clave) {
            if ($clave === '@avanzadas') {
                foreach ($base as $k => $seccion) {
                    if ($seccion['avanzada'] && in_array($k, $avanzadas, true)) {
                        $salida[] = $seccion + ['precarga' => null];
                    }
                }

                continue;
            }

            $seccion = match ($clave) {
                'resumen' => ['tipo' => TipoSeccion::ResumenCliente, 'titulo' => 'Resumen para el cliente', 'contenido' => EsquemaSeccion::vacio(TipoSeccion::ResumenCliente)],
                'serie_mensual' => self::serie('Evolución mensual', []),
                'movimientos' => self::tabla('Movimiento de keywords', []),
                'metricas' => self::tabla('Métricas contra su meta', []),
                'kpis' => self::kpis('Indicadores del mes'),
                default => $base->get($clave),
            };

            if ($seccion === null) {
                continue;
            }

            $salida[] = $seccion + ['clave' => $clave, 'avanzada' => false, 'precarga' => self::PRECARGA[$clave] ?? null];
        }

        return $salida;
    }

    /**
     * @return array<int, array{tipo: TipoSeccion, titulo: string, contenido: array<string, mixed>}>
     */
    private static function seo(): array
    {
        return [
            self::texto('Alcance del reporte', 'pares'),
            self::kpis('Indicadores del periodo'),
            self::hallazgos('Hallazgos críticos'),
            self::serie('Tendencia diaria', [
                ['clave' => 'clics', 'titulo' => 'Clics', 'eje' => 'izq'],
                ['clave' => 'impresiones', 'titulo' => 'Impresiones', 'eje' => 'der'],
            ]),
            self::tabla('Consultas', [
                ['clave' => 'consulta', 'titulo' => 'Consulta', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
                ['clave' => 'clics', 'titulo' => 'Clics', 'tipo' => 'numero', 'alineacion' => 'derecha'],
                ['clave' => 'impresiones', 'titulo' => 'Impresiones', 'tipo' => 'numero', 'alineacion' => 'derecha'],
                ['clave' => 'ctr', 'titulo' => 'CTR', 'tipo' => 'porcentaje', 'alineacion' => 'derecha'],
                ['clave' => 'posicion', 'titulo' => 'Posición media', 'tipo' => 'decimal', 'alineacion' => 'derecha'],
                ['clave' => 'intencion', 'titulo' => 'Intención', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
                ['clave' => 'pagina_serp', 'titulo' => 'Página SERP', 'tipo' => 'texto', 'alineacion' => 'centro'],
                ['clave' => 'oportunidad', 'titulo' => 'Oportunidad', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
            ], [
                'clics' => 'suma',
                'impresiones' => 'suma',
                'ctr' => 'ctr',
                'posicion' => 'ponderado',
            ]),
            self::tabla('Rendimiento por página', [
                ['clave' => 'url', 'titulo' => 'URL', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
                ['clave' => 'ruta', 'titulo' => 'Ruta', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
                ['clave' => 'host', 'titulo' => 'Host', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
                ['clave' => 'clics', 'titulo' => 'Clics', 'tipo' => 'numero', 'alineacion' => 'derecha'],
                ['clave' => 'impresiones', 'titulo' => 'Impresiones', 'tipo' => 'numero', 'alineacion' => 'derecha'],
                ['clave' => 'ctr', 'titulo' => 'CTR', 'tipo' => 'porcentaje', 'alineacion' => 'derecha'],
                ['clave' => 'posicion', 'titulo' => 'Posición media', 'tipo' => 'decimal', 'alineacion' => 'derecha'],
            ], [
                'clics' => 'suma',
                'impresiones' => 'suma',
                'ctr' => 'ctr',
                'posicion' => 'ponderado',
            ]),
            self::tabla('Países', [
                ['clave' => 'pais', 'titulo' => 'País', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
                ['clave' => 'clics', 'titulo' => 'Clics', 'tipo' => 'numero', 'alineacion' => 'derecha'],
                ['clave' => 'impresiones', 'titulo' => 'Impresiones', 'tipo' => 'numero', 'alineacion' => 'derecha'],
                ['clave' => 'ctr', 'titulo' => 'CTR', 'tipo' => 'porcentaje', 'alineacion' => 'derecha'],
                ['clave' => 'posicion', 'titulo' => 'Posición media', 'tipo' => 'decimal', 'alineacion' => 'derecha'],
            ], ['clics' => 'suma', 'impresiones' => 'suma', 'ctr' => 'ctr']),
            self::tabla('Dispositivos', [
                ['clave' => 'dispositivo', 'titulo' => 'Dispositivo', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
                ['clave' => 'clics', 'titulo' => 'Clics', 'tipo' => 'numero', 'alineacion' => 'derecha'],
                ['clave' => 'impresiones', 'titulo' => 'Impresiones', 'tipo' => 'numero', 'alineacion' => 'derecha'],
                ['clave' => 'ctr', 'titulo' => 'CTR', 'tipo' => 'porcentaje', 'alineacion' => 'derecha'],
                ['clave' => 'posicion', 'titulo' => 'Posición media', 'tipo' => 'decimal', 'alineacion' => 'derecha'],
            ], ['clics' => 'suma', 'impresiones' => 'suma', 'ctr' => 'ctr']),
            self::tabla('Cobertura de indexación', [
                ['clave' => 'motivo', 'titulo' => 'Motivo', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
                ['clave' => 'fuente', 'titulo' => 'Fuente', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
                ['clave' => 'paginas', 'titulo' => 'Páginas', 'tipo' => 'numero', 'alineacion' => 'derecha'],
                ['clave' => 'diagnostico', 'titulo' => 'Diagnóstico', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
            ], ['paginas' => 'suma']),
            self::ficha('Auditoría on-page', [
                ['clave' => 'title', 'titulo' => 'Title', 'dimension' => 'title'],
                ['clave' => 'long_title', 'titulo' => 'Long. title', 'dimension' => 'title'],
                ['clave' => 'meta', 'titulo' => 'Meta description', 'dimension' => 'meta'],
                ['clave' => 'long_meta', 'titulo' => 'Long. meta', 'dimension' => 'meta'],
                ['clave' => 'h1', 'titulo' => 'H1', 'dimension' => 'encabezados'],
                ['clave' => 'h2', 'titulo' => 'H2', 'dimension' => 'encabezados'],
                ['clave' => 'h3', 'titulo' => 'H3', 'dimension' => 'encabezados'],
                ['clave' => 'schema', 'titulo' => 'Schema JSON-LD', 'dimension' => 'schema'],
                ['clave' => 'palabras', 'titulo' => 'Palabras', 'dimension' => 'contenido'],
                ['clave' => 'imagenes', 'titulo' => 'Imágenes', 'dimension' => 'contenido'],
                ['clave' => 'sin_alt', 'titulo' => 'Sin ALT', 'dimension' => 'contenido'],
                ['clave' => 'enlaces', 'titulo' => 'Enlaces', 'dimension' => 'contenido'],
                ['clave' => 'ttfb', 'titulo' => 'TTFB (ms)', 'dimension' => 'ttfb'],
                ['clave' => 'dom', 'titulo' => 'DOM completo (ms)', 'dimension' => 'ttfb'],
            ], self::DIMENSIONES_ONPAGE),
            self::texto('Análisis y riesgos', 'narrativa'),
            self::plan('Plan de acción'),
            self::texto('Metodología y fuentes', 'pares'),
        ];
    }

    /**
     * @return array<int, array{tipo: TipoSeccion, titulo: string, contenido: array<string, mixed>}>
     */
    private static function ads(): array
    {
        return [
            self::texto('Alcance del reporte', 'pares'),
            self::kpis('Indicadores del periodo'),
            self::hallazgos('Hallazgos y oportunidades'),
            self::serie('Tendencia diaria', [
                ['clave' => 'inversion', 'titulo' => 'Inversión', 'eje' => 'izq'],
                ['clave' => 'conversiones', 'titulo' => 'Conversiones', 'eje' => 'der'],
            ]),
            self::tabla('Rendimiento por campaña', [
                ['clave' => 'campana', 'titulo' => 'Campaña', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
                ['clave' => 'plataforma', 'titulo' => 'Plataforma', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
                ['clave' => 'inversion', 'titulo' => 'Inversión', 'tipo' => 'moneda', 'alineacion' => 'derecha'],
                ['clave' => 'impresiones', 'titulo' => 'Impresiones', 'tipo' => 'numero', 'alineacion' => 'derecha'],
                ['clave' => 'clics', 'titulo' => 'Clics', 'tipo' => 'numero', 'alineacion' => 'derecha'],
                ['clave' => 'ctr', 'titulo' => 'CTR', 'tipo' => 'porcentaje', 'alineacion' => 'derecha'],
                ['clave' => 'conversiones', 'titulo' => 'Conversiones', 'tipo' => 'numero', 'alineacion' => 'derecha'],
                ['clave' => 'cpa', 'titulo' => 'CPA', 'tipo' => 'moneda', 'alineacion' => 'derecha'],
                ['clave' => 'roas', 'titulo' => 'ROAS', 'tipo' => 'decimal', 'alineacion' => 'derecha'],
            ], [
                'inversion' => 'suma',
                'impresiones' => 'suma',
                'clics' => 'suma',
                'ctr' => 'ctr',
                'conversiones' => 'suma',
                'cpa' => 'promedio',
                'roas' => 'promedio',
            ]),
            self::tabla('Palabras clave y públicos', [
                ['clave' => 'termino', 'titulo' => 'Término', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
                ['clave' => 'grupo', 'titulo' => 'Grupo', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
                ['clave' => 'impresiones', 'titulo' => 'Impresiones', 'tipo' => 'numero', 'alineacion' => 'derecha'],
                ['clave' => 'clics', 'titulo' => 'Clics', 'tipo' => 'numero', 'alineacion' => 'derecha'],
                ['clave' => 'ctr', 'titulo' => 'CTR', 'tipo' => 'porcentaje', 'alineacion' => 'derecha'],
                ['clave' => 'conversiones', 'titulo' => 'Conversiones', 'tipo' => 'numero', 'alineacion' => 'derecha'],
                ['clave' => 'cpa', 'titulo' => 'CPA', 'tipo' => 'moneda', 'alineacion' => 'derecha'],
            ], ['impresiones' => 'suma', 'clics' => 'suma', 'ctr' => 'ctr', 'conversiones' => 'suma']),
            self::tabla('Creativos', [
                ['clave' => 'creativo', 'titulo' => 'Creativo', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
                ['clave' => 'formato', 'titulo' => 'Formato', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
                ['clave' => 'impresiones', 'titulo' => 'Impresiones', 'tipo' => 'numero', 'alineacion' => 'derecha'],
                ['clave' => 'clics', 'titulo' => 'Clics', 'tipo' => 'numero', 'alineacion' => 'derecha'],
                ['clave' => 'ctr', 'titulo' => 'CTR', 'tipo' => 'porcentaje', 'alineacion' => 'derecha'],
                ['clave' => 'conversiones', 'titulo' => 'Conversiones', 'tipo' => 'numero', 'alineacion' => 'derecha'],
            ], ['impresiones' => 'suma', 'clics' => 'suma', 'ctr' => 'ctr', 'conversiones' => 'suma']),
            self::tabla('Conversiones por etapa del embudo', [
                ['clave' => 'etapa', 'titulo' => 'Etapa', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
                ['clave' => 'conversiones', 'titulo' => 'Conversiones', 'tipo' => 'numero', 'alineacion' => 'derecha'],
                ['clave' => 'valor', 'titulo' => 'Valor', 'tipo' => 'moneda', 'alineacion' => 'derecha'],
                ['clave' => 'nota', 'titulo' => 'Nota', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
            ], ['conversiones' => 'suma', 'valor' => 'suma']),
            self::texto('Análisis y riesgos', 'narrativa'),
            self::plan('Plan de optimización'),
            self::texto('Metodología y fuentes', 'pares'),
        ];
    }

    /**
     * @return array<int, array{tipo: TipoSeccion, titulo: string, contenido: array<string, mixed>}>
     */
    private static function web(): array
    {
        return [
            self::texto('Alcance del reporte', 'pares'),
            self::kpis('Indicadores del periodo'),
            self::hallazgos('Hallazgos técnicos'),
            self::serie('Tiempo de carga y disponibilidad', [
                ['clave' => 'lcp', 'titulo' => 'LCP (s)', 'eje' => 'izq'],
                ['clave' => 'disponibilidad', 'titulo' => 'Disponibilidad (%)', 'eje' => 'der'],
            ]),
            self::tabla('Core Web Vitals por plantilla', [
                ['clave' => 'plantilla', 'titulo' => 'Plantilla', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
                ['clave' => 'dispositivo', 'titulo' => 'Dispositivo', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
                ['clave' => 'lcp', 'titulo' => 'LCP (s)', 'tipo' => 'decimal', 'alineacion' => 'derecha'],
                ['clave' => 'inp', 'titulo' => 'INP (ms)', 'tipo' => 'numero', 'alineacion' => 'derecha'],
                ['clave' => 'cls', 'titulo' => 'CLS', 'tipo' => 'decimal', 'alineacion' => 'derecha'],
                ['clave' => 'veredicto', 'titulo' => 'Veredicto', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
            ], ['lcp' => 'promedio', 'inp' => 'promedio', 'cls' => 'promedio']),
            self::ficha('Auditoría técnica por página', [
                ['clave' => 'estado_http', 'titulo' => 'Estado HTTP', 'dimension' => 'respuesta'],
                ['clave' => 'canonical', 'titulo' => 'Canonical', 'dimension' => 'respuesta'],
                ['clave' => 'title', 'titulo' => 'Title', 'dimension' => 'marcado'],
                ['clave' => 'accesibilidad', 'titulo' => 'Accesibilidad', 'dimension' => 'marcado'],
                ['clave' => 'peso', 'titulo' => 'Peso (KB)', 'dimension' => 'rendimiento'],
                ['clave' => 'ttfb', 'titulo' => 'TTFB (ms)', 'dimension' => 'rendimiento'],
                ['clave' => 'errores_consola', 'titulo' => 'Errores de consola', 'dimension' => 'errores'],
            ], [
                ['clave' => 'respuesta', 'titulo' => 'Respuesta'],
                ['clave' => 'marcado', 'titulo' => 'Marcado'],
                ['clave' => 'rendimiento', 'titulo' => 'Rendimiento'],
                ['clave' => 'errores', 'titulo' => 'Errores'],
            ]),
            self::tabla('Incidencias detectadas', [
                ['clave' => 'incidencia', 'titulo' => 'Incidencia', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
                ['clave' => 'severidad', 'titulo' => 'Severidad', 'tipo' => 'texto', 'alineacion' => 'centro'],
                ['clave' => 'paginas', 'titulo' => 'Páginas afectadas', 'tipo' => 'numero', 'alineacion' => 'derecha'],
                ['clave' => 'estado', 'titulo' => 'Estado', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
            ], ['paginas' => 'suma']),
            self::texto('Análisis y riesgos', 'narrativa'),
            self::plan('Plan de mejoras'),
            self::texto('Metodología y fuentes', 'pares'),
        ];
    }

    /** @return array{tipo: TipoSeccion, titulo: string, contenido: array<string, mixed>} */
    private static function kpis(string $titulo): array
    {
        return ['tipo' => TipoSeccion::Kpis, 'titulo' => $titulo, 'contenido' => EsquemaSeccion::vacio(TipoSeccion::Kpis)];
    }

    /** @return array{tipo: TipoSeccion, titulo: string, contenido: array<string, mixed>} */
    private static function hallazgos(string $titulo): array
    {
        return ['tipo' => TipoSeccion::Hallazgos, 'titulo' => $titulo, 'contenido' => EsquemaSeccion::vacio(TipoSeccion::Hallazgos)];
    }

    /** @return array{tipo: TipoSeccion, titulo: string, contenido: array<string, mixed>} */
    private static function plan(string $titulo): array
    {
        return ['tipo' => TipoSeccion::Plan, 'titulo' => $titulo, 'contenido' => EsquemaSeccion::vacio(TipoSeccion::Plan)];
    }

    /** @return array{tipo: TipoSeccion, titulo: string, contenido: array<string, mixed>} */
    private static function texto(string $titulo, string $formato): array
    {
        $contenido = EsquemaSeccion::vacio(TipoSeccion::Texto);
        $contenido['formato'] = $formato;

        return ['tipo' => TipoSeccion::Texto, 'titulo' => $titulo, 'contenido' => $contenido];
    }

    /**
     * @param  array<int, array{clave: string, titulo: string, eje?: string}>  $series
     * @return array{tipo: TipoSeccion, titulo: string, contenido: array<string, mixed>}
     */
    private static function serie(string $titulo, array $series): array
    {
        $contenido = EsquemaSeccion::vacio(TipoSeccion::Serie);
        $contenido['series'] = $series;

        return ['tipo' => TipoSeccion::Serie, 'titulo' => $titulo, 'contenido' => $contenido];
    }

    /**
     * @param  array<int, array<string, string>>  $columnas
     * @param  array<string, string>  $totales
     * @return array{tipo: TipoSeccion, titulo: string, contenido: array<string, mixed>}
     */
    private static function tabla(string $titulo, array $columnas, array $totales = []): array
    {
        $contenido = EsquemaSeccion::vacio(TipoSeccion::Tabla);
        $contenido['columnas'] = $columnas;
        $contenido['totales'] = $totales;

        return ['tipo' => TipoSeccion::Tabla, 'titulo' => $titulo, 'contenido' => $contenido];
    }

    /**
     * Las dimensiones son la agrupación que usa el semáforo del diseño: una tabla
     * de 17 columnas es ilegible en carta, así que los campos se resumen en seis
     * columnas de estado y el detalle vive en la ficha por registro.
     *
     * @param  array<int, array{clave: string, titulo: string, dimension?: string}>  $campos
     * @param  array<int, array{clave: string, titulo: string}>  $dimensiones
     * @return array{tipo: TipoSeccion, titulo: string, contenido: array<string, mixed>}
     */
    private static function ficha(string $titulo, array $campos, array $dimensiones = []): array
    {
        $contenido = EsquemaSeccion::vacio(TipoSeccion::Ficha);
        $contenido['campos'] = $campos;
        $contenido['dimensiones'] = $dimensiones;

        return ['tipo' => TipoSeccion::Ficha, 'titulo' => $titulo, 'contenido' => $contenido];
    }
}
