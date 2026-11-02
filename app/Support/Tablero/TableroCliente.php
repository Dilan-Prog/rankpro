<?php

namespace App\Support\Tablero;

use App\Enums\EstadoKeyword;
use App\Enums\EstadoOnPageAccion;
use App\Enums\EstadoTarea;
use App\Models\Cliente;
use App\Models\Keyword;
use App\Models\KeywordMedicion;
use App\Models\SeoOnPageAccion;
use App\Models\Tarea;
use Carbon\CarbonInterface;

/**
 * Cálculo del «Tablero por cliente» (sección de Reportes). Solo lectura sobre
 * datos que ya existen: keywords, mediciones y tareas de Desarrollo. No guarda
 * nada ni inventa nada: si falta un dato devuelve null o una lista vacía y la
 * vista decide cómo mostrar el hueco.
 *
 * La lógica está separada en dos capas a propósito: `calcular()` solo consulta
 * la base y delega en `analizar()`, que trabaja sobre arreglos y se prueba sin
 * HTTP ni base de datos. Una «ronda» es una fecha de medición (igual que en
 * Keywords: lo que se captura de una sentada); aquí se agrupan por fecha las
 * mediciones de todas las listas del cliente.
 *
 * Convención de signo: `mejora` = anterior − actual. Una posición MENOR es
 * mejor, así que un valor positivo significa que subió en el ranking.
 */
class TableroCliente
{
    /** Rondas que se dibujan en la serie; más que eso no cabe legible en la gráfica. */
    public const RONDAS_EN_SERIE = 12;

    /** Filas de mayor mejora / mayor caída. */
    public const FILAS_RANKING = 5;

    /**
     * Rangos de la distribución de posiciones. Una posición mayor a 100 (o
     * «no aparece», null) cae en «fuera», porque el seguimiento llega al top 100.
     *
     * @return list<array{clave: string, etiqueta: string, desde: int, hasta: int}>
     */
    public static function rangos(): array
    {
        return [
            ['clave' => 'top3', 'etiqueta' => '1–3', 'desde' => 1, 'hasta' => 3],
            ['clave' => 'top10', 'etiqueta' => '4–10', 'desde' => 4, 'hasta' => 10],
            ['clave' => 'top20', 'etiqueta' => '11–20', 'desde' => 11, 'hasta' => 20],
            ['clave' => 'top50', 'etiqueta' => '21–50', 'desde' => 21, 'hasta' => 50],
            ['clave' => 'top100', 'etiqueta' => '51–100', 'desde' => 51, 'hasta' => 100],
        ];
    }

    public function __construct(private readonly ?CarbonInterface $hoy = null)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function calcular(Cliente $cliente): array
    {
        // Las descartadas ya no se persiguen: contarlas inflaría el «fuera del top 100».
        $totalKeywords = Keyword::query()
            ->where('cliente_id', $cliente->id)
            ->where('estado', '!=', EstadoKeyword::Descartada->value)
            ->count();

        $mediciones = KeywordMedicion::query()
            ->join('keywords', 'keywords.id', '=', 'keyword_mediciones.keyword_id')
            ->where('keywords.cliente_id', $cliente->id)
            ->where('keywords.estado', '!=', EstadoKeyword::Descartada->value)
            ->orderBy('keyword_mediciones.fecha')
            ->get(['keyword_mediciones.keyword_id', 'keyword_mediciones.fecha', 'keyword_mediciones.posicion', 'keywords.keyword as nombre'])
            ->map(fn ($m) => [
                'keyword_id' => (int) $m->keyword_id,
                'nombre' => (string) $m->nombre,
                'fecha' => $m->fecha->toDateString(),
                'posicion' => $m->posicion,
            ])
            ->all();

        $datos = self::analizar($mediciones, $totalKeywords);
        $datos['cliente'] = [
            'id' => $cliente->id,
            'nombre' => $cliente->empresa ?: $cliente->nombre,
        ];
        $datos['tareas'] = $this->tareas($cliente);
        $datos['actividades_seo'] = $this->actividadesSeo($cliente);

        return $datos;
    }

    /**
     * Tareas de Desarrollo del cliente (las que cuelgan de un proyecto suyo;
     * las personales de «Mi espacio» no cuentan). Vencida = abierta con fecha
     * límite anterior a hoy.
     *
     * @return array{abiertas: int, vencidas: int, porcentaje_vencidas: ?float}
     */
    private function tareas(Cliente $cliente): array
    {
        $hoy = ($this->hoy ?? now())->toDateString();

        $abiertas = Tarea::query()
            ->whereHas('proyecto', fn ($q) => $q->where('cliente_id', $cliente->id))
            ->where('estado', '!=', EstadoTarea::Completada->value);

        $totalAbiertas = (clone $abiertas)->count();
        $vencidas = (clone $abiertas)->whereNotNull('fecha_limite')->whereDate('fecha_limite', '<', $hoy)->count();

        return [
            'abiertas' => $totalAbiertas,
            'vencidas' => $vencidas,
            'porcentaje_vencidas' => self::porcentaje($vencidas, $totalAbiertas),
        ];
    }

    /**
     * Actividades del SEO Plan del cliente (cuelgan de sus campañas SEO). Solo
     * cuentan las cerradas CON fecha límite: sin ella no hay contra qué medir
     * «a tiempo», y contarlas falsearía el porcentaje. A tiempo = cerrada_en <=
     * fecha_limite (cerrar el mismo día límite cuenta como a tiempo).
     *
     * @return array{con_fecha_limite: int, a_tiempo: int, porcentaje_a_tiempo: ?float}
     */
    private function actividadesSeo(Cliente $cliente): array
    {
        $cerradas = SeoOnPageAccion::query()
            ->whereHas('seoCampana', fn ($q) => $q->where('cliente_id', $cliente->id))
            ->where('estado', EstadoOnPageAccion::Completada->value)
            ->whereNotNull('fecha_limite')
            ->whereNotNull('cerrada_en');

        $total = (clone $cerradas)->count();
        $aTiempo = (clone $cerradas)->whereColumn('cerrada_en', '<=', 'fecha_limite')->count();

        return [
            'con_fecha_limite' => $total,
            'a_tiempo' => $aTiempo,
            'porcentaje_a_tiempo' => self::porcentaje($aTiempo, $total),
        ];
    }

    /**
     * Parte pura del cálculo.
     *
     * @param  list<array{keyword_id: int, nombre: string, fecha: string, posicion: ?int}>  $mediciones  Cualquier orden.
     * @param  int  $totalKeywords  Keywords en seguimiento del cliente (con o sin mediciones).
     * @return array<string, mixed>
     */
    public static function analizar(array $mediciones, int $totalKeywords): array
    {
        $porKeyword = []; // id => ['nombre', 'puntos' => [fecha => posicion]]
        $porFecha = [];   // fecha => [id => posicion]

        foreach ($mediciones as $m) {
            // Posición 0 o negativa no existe: se trata como «sin posición» para no falsear promedios.
            $pos = isset($m['posicion']) && $m['posicion'] >= 1 ? (int) $m['posicion'] : null;
            $porKeyword[$m['keyword_id']]['nombre'] = $m['nombre'];
            $porKeyword[$m['keyword_id']]['puntos'][$m['fecha']] = $pos;
            $porFecha[$m['fecha']][$m['keyword_id']] = $pos;
        }

        $medidas = count($porKeyword);
        // Si hay más keywords medidas que el total recibido (dato inconsistente) no se inventa un negativo.
        $sinMedir = max(0, $totalKeywords - $medidas);

        $ultimas = [];
        $cambios = [];
        foreach ($porKeyword as $id => $k) {
            ksort($k['puntos']);
            $fechas = array_keys($k['puntos']);
            $n = count($fechas);
            $ultimas[$id] = $k['puntos'][$fechas[$n - 1]];

            if ($n >= 2) {
                $anterior = $k['puntos'][$fechas[$n - 2]];
                $actual = $k['puntos'][$fechas[$n - 1]];
                // Solo se compara si ambas rondas tienen posición: pasar a «no aparece» no tiene un número honesto.
                if ($anterior !== null && $actual !== null) {
                    $cambios[] = [
                        'nombre' => $k['nombre'],
                        'anterior' => $anterior,
                        'actual' => $actual,
                        'mejora' => $anterior - $actual,
                        'fecha' => $fechas[$n - 1],
                    ];
                }
            }
        }

        ksort($porFecha);

        return [
            'keywords' => [
                'seguimiento' => $totalKeywords,
                'medidas' => $medidas,
                'sin_medir' => $sinMedir,
            ],
            'top' => self::acumulados($ultimas, $medidas),
            'distribucion' => self::distribuir($ultimas, $medidas),
            'posicion_media' => self::posicionMedia($porFecha),
            'serie' => self::serie($porFecha),
            'mejores' => self::ranking($cambios, true),
            'peores' => self::ranking($cambios, false),
        ];
    }

    /**
     * Cubetas de posición de la última medición de cada keyword.
     *
     * @param  array<int|string, ?int>  $ultimas  Última posición por keyword (null = no aparece).
     * @return list<array{clave: string, etiqueta: string, cantidad: int, porcentaje: ?float}>
     */
    public static function distribuir(array $ultimas, ?int $medidas = null): array
    {
        $medidas ??= count($ultimas);
        $filas = [];

        foreach (self::rangos() as $r) {
            $n = count(array_filter($ultimas, fn ($p) => $p !== null && $p >= $r['desde'] && $p <= $r['hasta']));
            $filas[] = ['clave' => $r['clave'], 'etiqueta' => $r['etiqueta'], 'cantidad' => $n, 'porcentaje' => self::porcentaje($n, $medidas)];
        }

        $dentro = array_sum(array_column($filas, 'cantidad'));
        $fuera = max(0, $medidas - $dentro);
        $filas[] = ['clave' => 'fuera', 'etiqueta' => 'Fuera del top 100', 'cantidad' => $fuera, 'porcentaje' => self::porcentaje($fuera, $medidas)];

        return $filas;
    }

    /**
     * Keywords en el top 3 / 10 / 20 (acumulado: el top 10 incluye al top 3).
     *
     * @param  array<int|string, ?int>  $ultimas
     * @return array<string, array{cantidad: int, porcentaje: ?float}>
     */
    public static function acumulados(array $ultimas, ?int $medidas = null): array
    {
        $medidas ??= count($ultimas);
        $salida = [];

        foreach ([3, 10, 20] as $limite) {
            $n = count(array_filter($ultimas, fn ($p) => $p !== null && $p >= 1 && $p <= $limite));
            $salida['top'.$limite] = ['cantidad' => $n, 'porcentaje' => self::porcentaje($n, $medidas)];
        }

        return $salida;
    }

    /**
     * Posición media de la última ronda y su variación contra la anterior.
     * La variación se calcula solo con las keywords que tienen posición en
     * AMBAS rondas: si no, entrar o salir keywords movería el promedio sin que
     * ninguna se haya movido de verdad.
     *
     * @param  array<string, array<int, ?int>>  $porFecha  fecha => [keyword_id => posición], ordenado por fecha.
     * @return array{actual: ?float, anterior: ?float, mejora: ?float, comparables: int, fecha_actual: ?string, fecha_anterior: ?string}
     */
    public static function posicionMedia(array $porFecha): array
    {
        $fechas = array_keys($porFecha);
        $n = count($fechas);

        $res = ['actual' => null, 'anterior' => null, 'mejora' => null, 'comparables' => 0, 'fecha_actual' => null, 'fecha_anterior' => null];
        if ($n === 0) {
            return $res;
        }

        $fechaActual = (string) $fechas[$n - 1];
        $actual = $porFecha[$fechas[$n - 1]];
        $res['actual'] = self::media($actual);
        $res['fecha_actual'] = $fechaActual;

        // Sin ronda anterior no hay variación: null, nunca 0.
        if ($n < 2) {
            return $res;
        }

        $anterior = $porFecha[$fechas[$n - 2]];
        $res['anterior'] = self::media($anterior);
        $res['fecha_anterior'] = (string) $fechas[$n - 2];

        $comunes = [];
        foreach ($actual as $id => $pos) {
            if ($pos !== null && ($anterior[$id] ?? null) !== null) {
                $comunes[$id] = $pos;
            }
        }
        $res['comparables'] = count($comunes);

        if ($comunes !== []) {
            $mediaAnt = self::media(array_intersect_key($anterior, $comunes));
            $res['mejora'] = round($mediaAnt - self::media($comunes), 1);
        }

        return $res;
    }

    /**
     * Posición media por ronda, de la más antigua a la más reciente.
     *
     * @param  array<string, array<int, ?int>>  $porFecha
     * @return list<array{fecha: string, media: ?float, con_posicion: int, medidas: int}>
     */
    public static function serie(array $porFecha): array
    {
        $serie = [];
        foreach ($porFecha as $fecha => $posiciones) {
            $serie[] = [
                'fecha' => (string) $fecha,
                'media' => self::media($posiciones),
                'con_posicion' => count(array_filter($posiciones, fn ($p) => $p !== null)),
                'medidas' => count($posiciones),
            ];
        }

        return array_slice($serie, -self::RONDAS_EN_SERIE);
    }

    /**
     * @param  list<array{nombre: string, anterior: int, actual: int, mejora: int, fecha: string}>  $cambios
     * @return list<array{nombre: string, anterior: int, actual: int, mejora: int, fecha: string}>
     */
    private static function ranking(array $cambios, bool $mejores): array
    {
        $filtrados = array_values(array_filter($cambios, fn ($c) => $mejores ? $c['mejora'] > 0 : $c['mejora'] < 0));

        usort($filtrados, fn ($a, $b) => ($mejores ? $b['mejora'] <=> $a['mejora'] : $a['mejora'] <=> $b['mejora'])
            ?: strcmp($a['nombre'], $b['nombre']));

        return array_slice($filtrados, 0, self::FILAS_RANKING);
    }

    /** Media de las posiciones no nulas, a un decimal; null si no hay ninguna. */
    public static function media(array $posiciones): ?float
    {
        $valores = array_values(array_filter($posiciones, fn ($p) => $p !== null));

        return $valores === [] ? null : round(array_sum($valores) / count($valores), 1);
    }

    /** Porcentaje con un decimal; null si el total es 0 (nunca divide entre cero ni devuelve 0 inventado). */
    public static function porcentaje(int $parte, int $total): ?float
    {
        return $total > 0 ? round($parte / $total * 100, 1) : null;
    }
}
