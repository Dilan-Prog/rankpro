<?php

namespace App\Support\Reportes\Precarga;

use App\Enums\AreaReporte;
use App\Enums\EstadoKeyword;
use App\Models\GscMetricaMensual;
use App\Models\GscPropiedad;
use App\Models\KeywordMedicion;
use App\Models\SeoCampana;
use App\Models\SeoMetricaMensual;
use App\Models\Keyword;
use App\Support\Fecha;
use App\Support\Tablero\TableroCliente;
use Illuminate\Support\Collection;

/**
 * Precarga del reporte SEO. Fuentes (cada cifra sale de una y se cita en el KPI):
 *  - Métricas SEO mensuales (`SeoMetricaMensual`): tráfico orgánico, keywords en el top 3/10/100,
 *    backlinks y errores técnicos. Se suman las campañas SEO del cliente (o solo la elegida en el reporte).
 *  - Search Console (`GscMetricaMensual`): clics, impresiones, CTR y posición media del mes.
 *    Un mes marcado `parcial` se señala como desactualizado: Google aún no lo cierra.
 *  - Mediciones de keywords (`KeywordMedicion`, vía la lógica del Tablero por cliente): top 3/10 y posición
 *    media, calculados con la última medición HASTA fin de mes; si esa medición es de un mes anterior se dice.
 */
class Seo extends Precarga
{
    private const FUENTE_SEO = 'Métricas SEO (campaña)';

    private const FUENTE_GSC = 'Search Console';

    private const FUENTE_KW = 'Mediciones de keywords';

    /** @var array<string, Collection> */
    private array $cacheSeo = [];

    /** @var array<string, Collection> */
    private array $cacheGsc = [];

    public function area(): AreaReporte
    {
        return AreaReporte::Seo;
    }

    protected function tituloGrafica(): string
    {
        return 'Tráfico orgánico y clics en Google';
    }

    public function secciones(): array
    {
        $kw = $this->keywords();
        $kpis = $this->kpis($kw);
        $serie = $this->serie();

        $this->hechos['keywords'] = ['mejoraron' => $kw['mejoraron'], 'empeoraron' => $kw['empeoraron']];
        $this->hechos['posicion_media'] = $kw['posicion_media'];

        return [
            'kpis' => ['items' => $kpis],
            'serie' => $serie,
            'movimientos' => $this->movimientos($kw),
            'resumen' => $this->resumen($serie),
        ];
    }

    /** @return Collection<int, SeoMetricaMensual> */
    private function filasSeo(\Carbon\Carbon $mes): Collection
    {
        $clave = $mes->format('Y-m');

        return $this->cacheSeo[$clave] ??= SeoMetricaMensual::query()
            ->whereIn('seo_campana_id', SeoCampana::query()
                ->where('cliente_id', $this->cliente->id)
                ->when($this->campanaId, fn ($q) => $q->whereKey($this->campanaId))
                ->select('id'))
            ->where('mes', $mes->month)
            ->where('anio', $mes->year)
            ->get();
    }

    /** @return Collection<int, GscMetricaMensual> */
    private function filasGsc(\Carbon\Carbon $mes): Collection
    {
        $clave = $mes->format('Y-m');

        return $this->cacheGsc[$clave] ??= GscMetricaMensual::query()
            ->whereIn('propiedad_id', GscPropiedad::query()->where('cliente_id', $this->cliente->id)->select('id'))
            ->whereDate('periodo', $mes->copy()->startOfMonth()->toDateString())
            ->get();
    }

    /**
     * Estado de las keywords hasta fin de mes. Mide por keyword sus DOS últimas posiciones
     * (solo si hay posición en ambas, igual que el Tablero) para contar mejoras y caídas reales.
     *
     * @return array<string, mixed>
     */
    private function keywords(): array
    {
        $finDeMes = $this->mes->copy()->endOfMonth()->toDateString();

        $seguimiento = Keyword::query()
            ->where('cliente_id', $this->cliente->id)
            ->where('estado', '!=', EstadoKeyword::Descartada->value)
            ->count();

        $mediciones = KeywordMedicion::query()
            ->join('keywords', 'keywords.id', '=', 'keyword_mediciones.keyword_id')
            ->where('keywords.cliente_id', $this->cliente->id)
            ->where('keywords.estado', '!=', EstadoKeyword::Descartada->value)
            ->whereDate('keyword_mediciones.fecha', '<=', $finDeMes)
            ->orderBy('keyword_mediciones.fecha')
            ->get(['keyword_mediciones.keyword_id', 'keyword_mediciones.fecha', 'keyword_mediciones.posicion', 'keywords.keyword as nombre'])
            ->map(fn ($m) => [
                'keyword_id' => (int) $m->keyword_id,
                'nombre' => (string) $m->nombre,
                'fecha' => $m->fecha->toDateString(),
                'posicion' => $m->posicion,
            ])
            ->all();

        $analisis = TableroCliente::analizar($mediciones, $seguimiento);

        $porKeyword = [];
        foreach ($mediciones as $m) {
            $pos = isset($m['posicion']) && $m['posicion'] >= 1 ? (int) $m['posicion'] : null;
            $porKeyword[$m['keyword_id']]['nombre'] = $m['nombre'];
            $porKeyword[$m['keyword_id']]['puntos'][$m['fecha']] = $pos;
        }

        $cambios = [];
        foreach ($porKeyword as $k) {
            ksort($k['puntos']);
            $puntos = array_values($k['puntos']);
            $n = count($puntos);
            if ($n >= 2 && $puntos[$n - 1] !== null && $puntos[$n - 2] !== null && $puntos[$n - 1] !== $puntos[$n - 2]) {
                $cambios[] = ['nombre' => $k['nombre'], 'anterior' => $puntos[$n - 2], 'actual' => $puntos[$n - 1], 'mejora' => $puntos[$n - 2] - $puntos[$n - 1]];
            }
        }

        $ordenar = function (array $lista, bool $mejoras): array {
            usort($lista, fn ($a, $b) => ($mejoras ? $b['mejora'] <=> $a['mejora'] : $a['mejora'] <=> $b['mejora']) ?: strcmp($a['nombre'], $b['nombre']));

            return $lista;
        };

        return [
            'medidas' => $analisis['keywords']['medidas'],
            'top' => $analisis['top'],
            'posicion_media' => $analisis['posicion_media'],
            'mejoraron' => $ordenar(array_values(array_filter($cambios, fn ($c) => $c['mejora'] > 0)), true),
            'empeoraron' => $ordenar(array_values(array_filter($cambios, fn ($c) => $c['mejora'] < 0)), false),
        ];
    }

    /**
     * @param  array<string, mixed>  $kw
     * @return array<int, array<string, mixed>>
     */
    private function kpis(array $kw): array
    {
        $actual = $this->filasSeo($this->mes);
        $previo = $this->filasSeo($this->mes->copy()->subMonthNoOverflow());
        $fechaSeo = self::fechaDe($actual);

        $deSeo = function (string $clave, string $label, string $campo, bool $subeEsBueno, bool $destacado, string $formato = 'numero') use ($actual, $previo, $fechaSeo) {
            $valor = self::suma($actual, $campo);
            $prev = self::suma($previo, $campo);

            return $this->kpi($clave, $label, $valor, $formato, self::FUENTE_SEO, $fechaSeo, ['destacado' => $destacado] + $this->comparar($valor, $prev, $subeEsBueno));
        };

        $trafico = self::suma($actual, 'trafico_organico');
        $backlinks = self::suma($actual, 'backlinks_total');
        $errores = self::suma($actual, 'errores_pendientes');

        // Hechos para el resumen: solo con mes previo real; sin él SugerenciasResumen no redacta nada.
        $this->hechos['metricas'] = [
            ['label' => 'El tráfico orgánico', 'actual' => $trafico, 'previo' => self::suma($previo, 'trafico_organico'), 'sube_es_bueno' => true, 'formato' => 'numero'],
        ];

        $gscActual = $this->filasGsc($this->mes);
        $gscPrevio = $this->filasGsc($this->mes->copy()->subMonthNoOverflow());
        $clics = self::suma($gscActual, 'clics');
        $impresiones = self::suma($gscActual, 'impresiones');
        $parcial = $gscActual->contains(fn ($f) => $f->parcial);
        $fechaGsc = self::fechaDe($gscActual);
        $extraGsc = fn () => $parcial ? ['desactualizado' => 'Mes parcial en Search Console'] : [];

        $this->hechos['metricas'][] = ['label' => 'Los clics desde Google', 'actual' => $clics, 'previo' => self::suma($gscPrevio, 'clics'), 'sube_es_bueno' => true, 'formato' => 'numero'];
        $this->hechos['metricas'][] = ['label' => 'El número de backlinks', 'actual' => $backlinks, 'previo' => self::suma($this->filasSeo($this->mes->copy()->subMonthNoOverflow()), 'backlinks_total'), 'sube_es_bueno' => true, 'formato' => 'numero'];

        $ctr = ($clics !== null && $impresiones !== null && $impresiones > 0) ? $clics / $impresiones : null;
        $posGsc = $gscActual->pluck('posicion_media')->filter(fn ($p) => $p !== null)->avg();

        $pm = $kw['posicion_media'];
        $sinMedicion = $kw['medidas'] === 0;
        $fechaMedicion = $pm['fecha_actual'] ?? null;
        // La última medición puede ser de un mes anterior: se dice, en vez de presentarla como del mes.
        $viejaMedicion = $fechaMedicion !== null && $fechaMedicion < $this->mes->toDateString();
        $extraKw = $viejaMedicion ? ['desactualizado' => 'Medido el '.Fecha::mx($fechaMedicion)] : [];

        $top = fn (string $k) => $sinMedicion ? null : (float) $kw['top'][$k]['cantidad'];

        $posExtra = $extraKw;
        if (! $sinMedicion && $pm['mejora'] !== null && (float) $pm['mejora'] !== 0.0) {
            $posExtra += [
                'comparativo' => str_replace('.', ',', sprintf('%+.1f', $pm['mejora'])).' lugares',
                'direccion' => $pm['mejora'] > 0 ? 'positiva' : ($pm['mejora'] < 0 ? 'negativa' : 'neutral'),
                'detalle' => 'vs. medición del '.Fecha::mx($pm['fecha_anterior']),
            ];
        }

        return [
            $deSeo('trafico_organico', 'Tráfico orgánico', 'trafico_organico', true, true),
            $this->kpi('kw_top3', 'Keywords en el top 3', $top('top3'), 'numero', self::FUENTE_KW, $fechaMedicion, ['destacado' => true] + $extraKw),
            $this->kpi('kw_top10', 'Keywords en el top 10', $top('top10'), 'numero', self::FUENTE_KW, $fechaMedicion, ['destacado' => true] + $extraKw),
            $this->kpi('kw_posicion_media', 'Posición media de keywords', $sinMedicion ? null : $pm['actual'], 'decimal', self::FUENTE_KW, $fechaMedicion, ['destacado' => true] + $posExtra),
            $this->kpi('kw_top20', 'Keywords en el top 20', $top('top20'), 'numero', self::FUENTE_KW, $fechaMedicion, $extraKw),
            $deSeo('keywords_top100', 'Keywords en el top 100', 'keywords_top100', true, false),
            $deSeo('backlinks_total', 'Backlinks', 'backlinks_total', true, false),
            $this->kpi('errores_pendientes', 'Errores técnicos pendientes', $errores, 'numero', self::FUENTE_SEO, $fechaSeo, $this->comparar($errores, self::suma($previo, 'errores_pendientes'), false)),
            $this->kpi('gsc_clics', 'Clics en Google', $clics, 'numero', self::FUENTE_GSC, $fechaGsc, $this->comparar($clics, self::suma($gscPrevio, 'clics'), true) + $extraGsc()),
            $this->kpi('gsc_impresiones', 'Impresiones en Google', $impresiones, 'numero', self::FUENTE_GSC, $fechaGsc, $this->comparar($impresiones, self::suma($gscPrevio, 'impresiones'), true) + $extraGsc()),
            $this->kpi('gsc_ctr', 'CTR en Google', $ctr, 'porcentaje', self::FUENTE_GSC, $fechaGsc, $extraGsc()),
            $this->kpi('gsc_posicion', 'Posición media en Google', $posGsc === null ? null : (float) $posGsc, 'decimal', self::FUENTE_GSC, $fechaGsc, $extraGsc()),
        ];
    }

    /** @return array<string, mixed> */
    private function serie(): array
    {
        return $this->serieMensual(
            [
                ['clave' => 'trafico', 'titulo' => 'Tráfico orgánico', 'eje' => 'izq'],
                ['clave' => 'clics', 'titulo' => 'Clics en Google', 'eje' => 'der'],
            ],
            fn ($mes) => [
                'trafico' => self::suma($this->filasSeo($mes), 'trafico_organico'),
                'clics' => self::suma($this->filasGsc($mes), 'clics'),
            ]
        );
    }

    /**
     * Mayores mejoras y caídas entre las dos últimas mediciones de cada keyword.
     *
     * @param  array<string, mixed>  $kw
     * @return array<string, mixed>
     */
    private function movimientos(array $kw): array
    {
        $fila = fn (array $c) => ['valores' => [
            'keyword' => $c['nombre'],
            'anterior' => $c['anterior'],
            'actual' => $c['actual'],
            'cambio' => $c['mejora'],
        ]];

        $filas = array_merge(
            array_map($fila, array_slice($kw['mejoraron'], 0, TableroCliente::FILAS_RANKING)),
            array_map($fila, array_slice($kw['empeoraron'], 0, TableroCliente::FILAS_RANKING)),
        );

        return [
            'columnas' => [
                ['clave' => 'keyword', 'titulo' => 'Keyword', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
                ['clave' => 'anterior', 'titulo' => 'Posición anterior', 'tipo' => 'numero', 'alineacion' => 'derecha'],
                ['clave' => 'actual', 'titulo' => 'Posición actual', 'tipo' => 'numero', 'alineacion' => 'derecha'],
                ['clave' => 'cambio', 'titulo' => 'Lugares ganados', 'tipo' => 'numero', 'alineacion' => 'derecha'],
            ],
            'filas' => $filas,
            'filas_excluidas' => [],
            'nota_excluidas' => '',
            'totales' => [],
            'nota' => $filas === [] ? '' : 'Compara las dos últimas mediciones de cada keyword hasta fin de mes; un cambio positivo es que subió en el ranking.',
        ];
    }
}
