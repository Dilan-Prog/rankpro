<?php

namespace App\Support\Reportes\Precarga;

use App\Enums\AreaReporte;
use App\Enums\SentidoMetrica;
use App\Models\MetricaCliente;
use App\Models\MetricaClienteValor;
use App\Support\Metricas\Semaforo;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Precarga del reporte de Sitio Web. Fuente única: las métricas personalizadas del cliente
 * (`MetricaCliente` y su valor del mes en `MetricaClienteValor`), evaluadas contra su meta
 * con el mismo `Semaforo` que ve el panel de Métricas. No hay otra fuente de rendimiento web
 * capturada en el sistema (Core Web Vitals y auditoría siguen siendo captura manual).
 *
 * El valor se entrega como texto con su unidad ("3.5 %"): la unidad es libre y el formato
 * `porcentaje` del reporte interpreta 0.5 como 50 %, que aquí sería falso.
 */
class Web extends Precarga
{
    private const FUENTE = 'Métricas del cliente';

    /** @var Collection<int, MetricaCliente>|null */
    private ?Collection $metricas = null;

    /** @var array<string, Collection> */
    private array $valores = [];

    public function area(): AreaReporte
    {
        return AreaReporte::Web;
    }

    protected function tituloGrafica(): string
    {
        return 'Evolución de las métricas del sitio';
    }

    public function secciones(): array
    {
        $serie = $this->serie();

        return [
            'kpis' => ['items' => $this->kpis()],
            'serie' => $serie,
            'metricas' => $this->tabla(),
            'resumen' => $this->resumen($serie),
        ];
    }

    /** @return Collection<int, MetricaCliente> */
    private function metricas(): Collection
    {
        return $this->metricas ??= MetricaCliente::query()
            ->where('cliente_id', $this->cliente->id)
            ->where('activa', true)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
    }

    /** Valor de cada métrica en el mes, por id; ausente = sin dato. @return Collection<int, MetricaClienteValor> */
    private function valoresDe(Carbon $mes): Collection
    {
        $clave = $mes->format('Y-m');

        return $this->valores[$clave] ??= MetricaClienteValor::query()
            ->whereIn('metrica_id', $this->metricas()->pluck('id'))
            ->whereDate('periodo', $mes->copy()->startOfMonth()->toDateString())
            ->get()
            ->keyBy('metrica_id');
    }

    private static function texto(float $valor, ?string $unidad): string
    {
        $unidad = trim((string) $unidad);

        return trim(Precarga::numeroTexto($valor).($unidad !== '' ? ' '.$unidad : ''));
    }

    /** @return array<int, array<string, mixed>> */
    private function kpis(): array
    {
        $actuales = $this->valoresDe($this->mes);
        $previos = $this->valoresDe($this->mes->copy()->subMonthNoOverflow());
        $items = [];
        $hechos = [];

        foreach ($this->metricas() as $i => $m) {
            $v = $actuales->get($m->id);
            $valor = $v ? (float) $v->valor : null;
            $previo = $previos->get($m->id) ? (float) $previos->get($m->id)->valor : null;
            $subeEsBueno = $m->sentido === SentidoMetrica::Mayor;

            $extra = ['destacado' => $i < 4, 'formato' => 'texto'];
            if ($valor !== null) {
                $semaforo = Semaforo::evaluar($valor, $m->meta, $m->sentido, (int) ($m->umbral_amarillo_pct ?: 80));
                $extra += $this->comparar($valor, $previo, $subeEsBueno);
                $extra['direccion'] = match ($semaforo) {
                    Semaforo::VERDE => 'positiva',
                    Semaforo::ROJO => 'negativa',
                    default => $extra['direccion'] ?? 'neutral',
                };
                if ($m->meta !== null) {
                    $extra['detalle'] = 'Meta: '.self::texto((float) $m->meta, $m->unidad);
                }
            }

            $item = $this->kpi('metrica_'.$m->id, (string) $m->nombre, $valor, 'texto', self::FUENTE, $v?->updated_at?->toDateString(), $extra);
            if ($valor !== null) {
                $item['valor'] = self::texto($valor, $m->unidad);
            }
            $items[] = $item;

            $hechos[] = ['label' => $m->nombre, 'actual' => $valor, 'previo' => $previo, 'sube_es_bueno' => $subeEsBueno, 'formato' => 'decimal'];
        }

        $this->hechos['metricas'] = $hechos;

        return $items;
    }

    /** @return array<string, mixed> */
    private function serie(): array
    {
        $metricas = $this->metricas()->take(2)->values();
        $series = $metricas->map(fn (MetricaCliente $m, int $i) => ['clave' => 'm'.$m->id, 'titulo' => mb_substr((string) $m->nombre, 0, 60), 'eje' => $i === 0 ? 'izq' : 'der'])->all();

        return $this->serieMensual($series, function (Carbon $mes) use ($metricas) {
            $valores = $this->valoresDe($mes);

            return $metricas->mapWithKeys(fn (MetricaCliente $m) => ['m'.$m->id => $valores->has($m->id) ? (float) $valores->get($m->id)->valor : null])->all();
        });
    }

    /** @return array<string, mixed> */
    private function tabla(): array
    {
        $actuales = $this->valoresDe($this->mes);

        $filas = $this->metricas()->map(function (MetricaCliente $m) use ($actuales) {
            $v = $actuales->get($m->id);
            $estado = $v ? Semaforo::evaluar((float) $v->valor, $m->meta, $m->sentido, (int) ($m->umbral_amarillo_pct ?: 80)) : Semaforo::SIN_DATO;

            return ['valores' => [
                'metrica' => (string) $m->nombre,
                'valor' => $v ? self::texto((float) $v->valor, $m->unidad) : null,
                'meta' => $m->meta !== null ? self::texto((float) $m->meta, $m->unidad) : null,
                'estado' => match ($estado) {
                    Semaforo::VERDE => 'En meta',
                    Semaforo::AMARILLO => 'Cerca de la meta',
                    Semaforo::ROJO => 'Fuera de meta',
                    Semaforo::SIN_META => 'Sin meta definida',
                    default => 'Sin dato del mes',
                },
            ]];
        })->values()->all();

        return [
            'columnas' => [
                ['clave' => 'metrica', 'titulo' => 'Métrica', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
                ['clave' => 'valor', 'titulo' => 'Valor del mes', 'tipo' => 'texto', 'alineacion' => 'derecha'],
                ['clave' => 'meta', 'titulo' => 'Meta', 'tipo' => 'texto', 'alineacion' => 'derecha'],
                ['clave' => 'estado', 'titulo' => 'Estado', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
            ],
            'filas' => $filas,
            'filas_excluidas' => [],
            'nota_excluidas' => '',
            'totales' => [],
            'nota' => '',
        ];
    }
}
