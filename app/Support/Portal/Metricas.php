<?php

namespace App\Support\Portal;

use App\Models\Cliente;
use App\Models\MetricaCliente;
use App\Support\Metricas\Semaforo;
use Carbon\CarbonImmutable;

/**
 * Métricas con semáforo del portal.
 *
 * LISTA BLANCA de campos por métrica: nombre, unidad, meta, sentido (etiqueta), semáforo, valor
 * actual con su mes y el historial de los últimos 6 meses (mes y valor). Nunca `nota`,
 * `registrado_por`, `fuente`, `metrica_estandar`, el umbral ni los ids.
 *
 * Solo métricas ACTIVAS. Sin meta el semáforo es neutro ("sin meta"), nunca verde; sin dato, neutro.
 */
class Metricas
{
    public const MESES = 6;

    private const ETIQUETAS = [
        Semaforo::VERDE => 'En meta',
        Semaforo::AMARILLO => 'Cerca de la meta',
        Semaforo::ROJO => 'Por debajo de la meta',
        Semaforo::SIN_META => 'Sin meta',
        Semaforo::SIN_DATO => 'Sin dato',
    ];

    /** @return list<array<string, mixed>> */
    public static function listar(Cliente $cliente): array
    {
        $hoy = CarbonImmutable::today()->startOfMonth();
        $desde = $hoy->subMonths(self::MESES - 1);

        $metricas = MetricaCliente::query()
            ->where('cliente_id', $cliente->id)
            ->where('activa', true)
            ->orderBy('orden')
            ->orderBy('id')
            ->with(['valores' => fn ($q) => $q->select('metrica_id', 'periodo', 'valor')
                ->whereDate('periodo', '>=', $desde->toDateString())
                ->whereDate('periodo', '<=', $hoy->toDateString())])
            ->get();

        return $metricas->map(function (MetricaCliente $metrica) use ($hoy, $desde) {
            $porMes = $metrica->valores->mapWithKeys(fn ($v) => [$v->periodo->format('Y-m') => (float) $v->valor]);

            $historial = [];
            for ($mes = $desde; $mes <= $hoy; $mes = $mes->addMonth()) {
                $valor = $porMes[$mes->format('Y-m')] ?? null;
                $historial[] = ['mes' => Meses::etiqueta($mes), 'valor' => $valor, 'texto' => $valor === null ? '—' : self::numero($valor)];
            }

            $actual = collect($historial)->reverse()->first(fn ($h) => $h['valor'] !== null);
            $meta = $metrica->meta !== null ? (float) $metrica->meta : null;
            $semaforo = Semaforo::evaluar($actual['valor'] ?? null, $meta, $metrica->sentido, (int) $metrica->umbral_amarillo_pct);

            return [
                'nombre' => (string) $metrica->nombre,
                'unidad' => (string) $metrica->unidad,
                'meta' => $meta,
                'meta_texto' => $meta === null ? null : self::numero($meta),
                'sentido_label' => $metrica->sentido->label(),
                'semaforo' => $semaforo,
                'semaforo_label' => self::ETIQUETAS[$semaforo],
                'valor' => $actual['texto'] ?? null,
                'mes_valor' => $actual['mes'] ?? null,
                'historial' => $historial,
            ];
        })->all();
    }

    /**
     * Semáforo general: rojo si alguna métrica con meta y dato está en rojo, amarillo si alguna en amarillo,
     * verde solo si hay al menos una calificable y todas están en verde; si ninguna es calificable, neutro.
     *
     * @param  list<array<string, mixed>>  $metricas
     * @return array{clave: string, etiqueta: string, calificables: int, total: int}
     */
    public static function general(array $metricas): array
    {
        $colores = array_column($metricas, 'semaforo');
        $calificables = array_filter($colores, fn ($c) => in_array($c, [Semaforo::VERDE, Semaforo::AMARILLO, Semaforo::ROJO], true));

        $clave = match (true) {
            $calificables === [] => 'neutro',
            in_array(Semaforo::ROJO, $calificables, true) => Semaforo::ROJO,
            in_array(Semaforo::AMARILLO, $calificables, true) => Semaforo::AMARILLO,
            default => Semaforo::VERDE,
        };

        $etiqueta = match ($clave) {
            Semaforo::ROJO => 'Hay métricas por debajo de su meta',
            Semaforo::AMARILLO => 'Hay métricas cerca de su meta',
            Semaforo::VERDE => 'Las métricas con meta están en meta',
            default => 'Aún no hay métricas con meta y dato para calificar',
        };

        return ['clave' => $clave, 'etiqueta' => $etiqueta, 'calificables' => count($calificables), 'total' => count($metricas)];
    }

    private static function numero(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 2, '.', ','), '0'), '.');
    }
}
