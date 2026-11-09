<?php

namespace App\Support\Reportes\Precarga;

use App\Enums\AreaReporte;
use App\Models\AdsConversion;
use App\Models\AdsMetrica;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Precarga del reporte de Publicidad. Fuentes:
 *  - Métricas de Ads (`AdsMetrica`, una fila por campaña y mes): inversión, impresiones, clics, conversiones y
 *    valor de conversión. Los derivados (CTR, CPA, ROAS) se recalculan desde las SUMAS, no se promedian los
 *    ya guardados por campaña: promediar razones da cifras que no cuadran con el total.
 *  - Conversiones registradas por el seguimiento propio (`AdsConversion`, sin duplicadas), como cifra aparte.
 *
 * Un CPA con 0 conversiones o un ROAS sin valor de conversión no se calculan: sale vacío con el motivo en
 * el detalle, nunca 0 ni infinito.
 */
class Ads extends Precarga
{
    private const FUENTE = 'Métricas de Ads (campaña)';

    private const FUENTE_SEGUIMIENTO = 'Seguimiento de conversiones';

    /** @var array<string, Collection> */
    private array $cache = [];

    public function area(): AreaReporte
    {
        return AreaReporte::Ads;
    }

    protected function tituloGrafica(): string
    {
        return 'Inversión y conversiones';
    }

    public function secciones(): array
    {
        $kpis = $this->kpis();
        $serie = $this->serie();

        return [
            'kpis' => ['items' => $kpis],
            'serie' => $serie,
            'campanas' => $this->campanas(),
            'resumen' => $this->resumen($serie),
        ];
    }

    /** @return Collection<int, AdsMetrica> */
    private function filas(Carbon $mes): Collection
    {
        $clave = $mes->format('Y-m');

        return $this->cache[$clave] ??= AdsMetrica::query()
            ->with('adsCampana:id,nombre,plataforma')
            ->where('cliente_id', $this->cliente->id)
            ->when($this->campanaId, fn ($q) => $q->where('ads_campana_id', $this->campanaId))
            ->where('mes', $mes->month)
            ->where('anio', $mes->year)
            ->get();
    }

    private static function cpa(?float $inversion, ?float $conversiones): ?float
    {
        return ($inversion !== null && $conversiones !== null && $conversiones > 0) ? $inversion / $conversiones : null;
    }

    /** ROAS solo si hay inversión y al menos una fila con valor de conversión capturado. */
    private static function roas(Collection $filas): ?float
    {
        $inversion = self::suma($filas, 'inversion_real');
        $conValor = $filas->filter(fn ($f) => $f->valor_conversion !== null);

        return ($inversion !== null && $inversion > 0 && $conValor->isNotEmpty())
            ? (float) $conValor->sum('valor_conversion') / $inversion
            : null;
    }

    /** @return array<int, array<string, mixed>> */
    private function kpis(): array
    {
        $actual = $this->filas($this->mes);
        $previo = $this->filas($this->mes->copy()->subMonthNoOverflow());
        $fecha = self::fechaDe($actual);

        $inversion = self::suma($actual, 'inversion_real');
        $conversiones = self::suma($actual, 'conversiones');
        $clics = self::suma($actual, 'clics');
        $impresiones = self::suma($actual, 'impresiones');
        $valor = $actual->contains(fn ($f) => $f->valor_conversion !== null) ? (float) $actual->sum('valor_conversion') : null;

        $inversionPrev = self::suma($previo, 'inversion_real');
        $conversionesPrev = self::suma($previo, 'conversiones');
        $cpa = self::cpa($inversion, $conversiones);
        $cpaPrev = self::cpa($inversionPrev, $conversionesPrev);
        $roas = self::roas($actual);
        $ctr = ($clics !== null && $impresiones !== null && $impresiones > 0) ? $clics / $impresiones : null;

        $this->hechos['metricas'] = [
            ['label' => 'Las conversiones', 'actual' => $conversiones, 'previo' => $conversionesPrev, 'sube_es_bueno' => true, 'formato' => 'numero'],
            ['label' => 'El costo por conversión', 'actual' => $cpa, 'previo' => $cpaPrev, 'sube_es_bueno' => false, 'formato' => 'moneda'],
            ['label' => 'Los clics', 'actual' => $clics, 'previo' => self::suma($previo, 'clics'), 'sube_es_bueno' => true, 'formato' => 'numero'],
        ];

        // Hay datos del mes pero no se puede calcular: se explica en el detalle en vez de marcarlo "sin dato".
        $nota = fn (?float $calculado, bool $hayFilas, string $motivo) => ($calculado === null && $hayFilas) ? ['detalle' => $motivo] : [];
        $hay = $actual->isNotEmpty();
        // Con filas del mes el dato "no calculable" no es "sin dato del mes": se quita la marca de desactualizado.
        $calculable = fn (array $item) => ($hay && $item['valor'] === '') ? array_replace($item, ['desactualizado' => '']) : $item;

        $seguimiento = $this->seguimiento();

        return [
            $this->kpi('inversion', 'Inversión', $inversion, 'moneda', self::FUENTE, $fecha, ['destacado' => true] + $this->comparar($inversion, $inversionPrev, true)),
            $this->kpi('conversiones', 'Conversiones', $conversiones, 'numero', self::FUENTE, $fecha, ['destacado' => true] + $this->comparar($conversiones, $conversionesPrev, true)),
            $calculable($this->kpi('cpa', 'Costo por conversión (CPA)', $cpa, 'moneda', self::FUENTE, $fecha, array_replace(['destacado' => true], $this->comparar($cpa, $cpaPrev, false), $nota($cpa, $hay, 'No calculable: 0 conversiones')))),
            $calculable($this->kpi('roas', 'ROAS', $roas, 'decimal', self::FUENTE, $fecha, ['destacado' => true] + $nota($roas, $hay, 'Sin valor de conversión capturado'))),
            $this->kpi('clics', 'Clics', $clics, 'numero', self::FUENTE, $fecha, $this->comparar($clics, self::suma($previo, 'clics'), true)),
            $this->kpi('impresiones', 'Impresiones', $impresiones, 'numero', self::FUENTE, $fecha, $this->comparar($impresiones, self::suma($previo, 'impresiones'), true)),
            $this->kpi('ctr', 'CTR', $ctr, 'porcentaje', self::FUENTE, $fecha),
            $this->kpi('valor_conversion', 'Valor de conversión', $valor, 'moneda', self::FUENTE, $fecha),
            $seguimiento,
        ];
    }

    /**
     * Conversiones del mes según el seguimiento propio. Un 0 solo es creíble si el cliente
     * ya registra conversiones en algún momento; si nunca registró ninguna, es "sin dato".
     *
     * @return array<string, mixed>
     */
    private function seguimiento(): array
    {
        $base = AdsConversion::query()->noDuplicadas()->where('cliente_id', $this->cliente->id)
            ->when($this->campanaId, fn ($q) => $q->where('ads_campana_id', $this->campanaId));

        $delMes = (clone $base)->whereBetween('created_at', [$this->mes->copy()->startOfMonth(), $this->mes->copy()->endOfMonth()]);
        $total = (clone $delMes)->count();
        $registraAlgunaVez = $total > 0 || (clone $base)->exists();

        return $this->kpi(
            'conv_seguimiento',
            'Conversiones registradas (seguimiento)',
            $registraAlgunaVez ? (float) $total : null,
            'numero',
            self::FUENTE_SEGUIMIENTO,
            $registraAlgunaVez ? $this->mes->copy()->endOfMonth()->toDateString() : null,
        );
    }

    /** @return array<string, mixed> */
    private function serie(): array
    {
        return $this->serieMensual(
            [
                ['clave' => 'inversion', 'titulo' => 'Inversión', 'eje' => 'izq'],
                ['clave' => 'conversiones', 'titulo' => 'Conversiones', 'eje' => 'der'],
            ],
            fn ($mes) => [
                'inversion' => self::suma($this->filas($mes), 'inversion_real'),
                'conversiones' => self::suma($this->filas($mes), 'conversiones'),
            ]
        );
    }

    /** @return array<string, mixed> */
    private function campanas(): array
    {
        $filas = $this->filas($this->mes)->map(function (AdsMetrica $f) {
            $clics = (float) $f->clics;
            $impresiones = (float) $f->impresiones;
            $inversion = (float) $f->inversion_real;
            $conversiones = (float) $f->conversiones;

            return ['valores' => [
                'campana' => (string) ($f->adsCampana?->nombre ?? 'Campaña eliminada'),
                'plataforma' => (string) ($f->adsCampana?->plataforma ?? ''),
                'inversion' => $inversion,
                'impresiones' => $impresiones,
                'clics' => $clics,
                'ctr' => $impresiones > 0 ? $clics / $impresiones : null,
                'conversiones' => $conversiones,
                'cpa' => $conversiones > 0 ? $inversion / $conversiones : null,
                'roas' => ($inversion > 0 && $f->valor_conversion !== null) ? (float) $f->valor_conversion / $inversion : null,
            ]];
        })->values()->all();

        return ['filas' => $filas];
    }
}
