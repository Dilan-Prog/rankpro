<?php

namespace App\Support\Tablero\Bloques;

use App\Models\AdsCampana;
use App\Models\AdsMetrica;
use App\Support\Tablero\Contexto;

/**
 * Publicidad de una plataforma (Google Ads o Meta Ads). Ninguna tiene integración con su API: lo que se
 * ve es lo que alguien capturó a mano en las campañas del módulo Ads, filtrado por `plataforma`. Meta Ads sin
 * campañas se rotula «Sin integración» (no «sin dato») para no sugerir que falta capturar algo que no se puede traer.
 */
class Ads extends Bloque
{
    private const PLATAFORMAS = [
        'google_ads' => ['titulo' => 'Google Ads', 'icono' => 'fa-brands fa-google'],
        'meta_ads' => ['titulo' => 'Meta Ads', 'icono' => 'fa-brands fa-meta'],
    ];

    public function __construct(private readonly string $plataforma = 'google_ads')
    {
        if (! isset(self::PLATAFORMAS[$plataforma])) {
            throw new \InvalidArgumentException("Plataforma sin bloque: {$plataforma}");
        }
    }

    public function clave(): string
    {
        return $this->plataforma;
    }

    public function titulo(): string
    {
        return self::PLATAFORMAS[$this->plataforma]['titulo'];
    }

    public function icono(): string
    {
        return self::PLATAFORMAS[$this->plataforma]['icono'];
    }

    public function permisos(): array
    {
        return ['ads'];
    }

    public function calcular(Contexto $ctx): array
    {
        $datos = $ctx->recordar('ads', fn () => $this->consultar($ctx));
        $campanas = (int) ($datos['campanas'][$this->plataforma] ?? 0);
        $filas = $datos['filas']->where('plataforma', $this->plataforma);
        $enlace = ['url' => route('admin.ads.index'), 'etiqueta' => 'Ir a Ads'];
        $titulo = $this->titulo();

        if ($filas->isEmpty()) {
            if ($campanas === 0) {
                $esMeta = $this->plataforma === 'meta_ads';

                return $this->bloque(
                    self::NEUTRO,
                    $esMeta
                        ? 'Meta Ads no tiene integración y este cliente no tiene campañas de Meta capturadas a mano.'
                        : 'Este cliente no tiene campañas de Google Ads.',
                    [],
                    $enlace,
                    ['leyenda' => $esMeta ? 'Sin integración' : 'Sin dato'],
                );
            }

            return $this->bloque(
                self::NEUTRO,
                'Hay '.$campanas.' '.($campanas === 1 ? 'campaña' : 'campañas').' de '.$titulo.', pero ninguna tiene métricas capturadas.',
                [],
                $enlace,
            );
        }

        $primera = $filas->first();
        $mes = (int) $primera->mes;
        $anio = (int) $primera->anio;
        $delMes = $filas->filter(fn ($f) => (int) $f->mes === $mes && (int) $f->anio === $anio)->values();

        $periodo = self::periodo($mes, $anio);
        $capturado = self::masReciente($delMes->pluck('updated_at'));
        $fuente = 'Captura manual · Módulo Ads ('.$delMes->count().' '.($delMes->count() === 1 ? 'campaña' : 'campañas').' de '.$titulo.')';
        $extra = ['capturado' => self::fecha($capturado)];

        $r = self::resumir($delMes->all());

        $cifras = [
            self::cifra('Inversión', $r['inversion'] === null ? null : self::dinero($r['inversion']), $periodo, $fuente, $extra),
            self::cifra('Clics', self::entero($r['clics']), $periodo, $fuente, $extra),
            self::cifra('Conversiones', self::entero($r['conversiones']), $periodo, $fuente, $extra),
            self::cifra('CPA', $r['cpa'] === null ? null : self::dinero($r['cpa']), $periodo, $fuente, $extra + ['nota' => $r['cpa'] === null ? 'Sin conversiones o sin inversión: no se divide.' : 'Inversión entre conversiones del mes.']),
            self::cifra('ROAS', $r['roas'] === null ? null : number_format($r['roas'], 2).'x', $periodo, $fuente, $extra + ['nota' => $r['roas'] === null ? 'Sin ingreso atribuido capturado.' : null]),
        ];

        if (self::desactualizado($capturado, $ctx->hoy)) {
            $estado = self::AMARILLO;
            $motivo = 'Sin actualizar desde el '.self::fecha($capturado).' (más de '.self::DIAS_SIN_ACTUALIZAR.' días).';
        } else {
            $estado = self::VERDE;
            $motivo = 'Métricas de '.$periodo.' capturadas el '.self::fecha($capturado).'.';
        }

        return $this->bloque($estado, $motivo, $cifras, $enlace);
    }

    /**
     * Totales de un mes sin dividir entre cero. El ROAS usa el ingreso atribuido (valor de conversión, o
     * inversión × ROAS capturado si no hay valor) sobre la inversión de las campañas CON ingreso, igual que el módulo Ads.
     *
     * @param  list<object>  $filas  Filas de AdsMetrica del mismo mes.
     * @return array{inversion: ?float, clics: ?int, conversiones: ?int, cpa: ?float, roas: ?float}
     */
    public static function resumir(array $filas): array
    {
        $col = collect($filas);
        $suma = fn (string $campo) => $col->contains(fn ($f) => $f->{$campo} !== null) ? $col->sum(fn ($f) => (float) $f->{$campo}) : null;

        $inversion = $suma('inversion_real');
        $clics = $suma('clics');
        $conversiones = $suma('conversiones');

        $ingreso = 0.0;
        $inversionConIngreso = 0.0;
        foreach ($col as $f) {
            $valor = (float) ($f->valor_conversion ?? 0);
            if ($valor <= 0 && $f->roas !== null && (float) $f->roas > 0) {
                $valor = (float) $f->inversion_real * (float) $f->roas;
            }
            if ($valor > 0 && (float) $f->inversion_real > 0) {
                $ingreso += $valor;
                $inversionConIngreso += (float) $f->inversion_real;
            }
        }

        return [
            'inversion' => $inversion,
            'clics' => $clics === null ? null : (int) $clics,
            'conversiones' => $conversiones === null ? null : (int) $conversiones,
            'cpa' => ($inversion !== null && $conversiones > 0) ? round($inversion / $conversiones, 2) : null,
            'roas' => $inversionConIngreso > 0 ? round($ingreso / $inversionConIngreso, 2) : null,
        ];
    }

    /** Una consulta de métricas de ambas plataformas y una de conteo de campañas, compartidas por los dos bloques. */
    private function consultar(Contexto $ctx): array
    {
        $filas = AdsMetrica::query()
            ->join('ads_campanas', 'ads_campanas.id', '=', 'ads_metricas.ads_campana_id')
            ->where('ads_campanas.cliente_id', $ctx->cliente->id)
            ->whereIn('ads_campanas.plataforma', array_keys(self::PLATAFORMAS))
            ->whereNull('ads_campanas.deleted_at')
            ->orderByDesc('ads_metricas.anio')->orderByDesc('ads_metricas.mes')
            ->limit(300)
            ->get(['ads_metricas.*', 'ads_campanas.plataforma as plataforma']);

        $campanas = AdsCampana::query()
            ->where('cliente_id', $ctx->cliente->id)
            ->groupBy('plataforma')
            ->selectRaw('plataforma, count(*) as total')
            ->pluck('total', 'plataforma');

        return ['filas' => $filas, 'campanas' => $campanas->all()];
    }
}
