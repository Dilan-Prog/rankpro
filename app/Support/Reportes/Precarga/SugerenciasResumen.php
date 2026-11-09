<?php

namespace App\Support\Reportes\Precarga;

/**
 * Redacta SUGERENCIAS de logros y retos para el resumen del cliente a partir de
 * variaciones reales. Función pura (sin BD) para poder probarla: cada cifra del
 * texto sale de $hechos; si el dato no está, no se escribe nada. El equipo los
 * edita o los reemplaza: son un borrador, nunca la palabra final.
 *
 * $hechos:
 *   metricas  [{label, actual, previo, sube_es_bueno, formato: numero|decimal|moneda}]  en orden de prioridad
 *   keywords  {mejoraron:[{nombre, anterior, actual}], empeoraron:[...]}  ordenadas por magnitud
 *   posicion_media {actual, anterior, mejora}  (mejora > 0 = subió en el ranking)
 *   mes_previo_label
 */
class SugerenciasResumen
{
    public const MAXIMO = 3;

    /**
     * @param  array<string, mixed>  $hechos
     * @return array{logros: array<int, array{texto: string, precargada: bool}>, retos: array<int, array{texto: string, precargada: bool}>}
     */
    public static function generar(array $hechos): array
    {
        $logros = [];
        $retos = [];
        $previoLabel = (string) ($hechos['mes_previo_label'] ?? 'el mes anterior');

        foreach ((array) ($hechos['metricas'] ?? []) as $m) {
            $actual = $m['actual'] ?? null;
            $previo = $m['previo'] ?? null;
            if ($actual === null || $previo === null || (float) $previo == 0.0 || (float) $actual == (float) $previo) {
                continue;
            }

            $pct = ((float) $actual - (float) $previo) / abs((float) $previo) * 100;
            $mejora = (($pct > 0) === (bool) ($m['sube_es_bueno'] ?? true));
            $base = sprintf(
                '%s %s %s%% frente a %s (de %s a %s)',
                $m['label'],
                $pct > 0 ? 'subió' : 'bajó',
                self::pct($pct),
                $previoLabel,
                self::num($previo, $m['formato'] ?? 'numero'),
                self::num($actual, $m['formato'] ?? 'numero')
            );

            if ($mejora) {
                $logros[] = $base.'.';
            } else {
                $retos[] = $base.'; conviene revisar qué cambió.';
            }
        }

        $kw = (array) ($hechos['keywords'] ?? []);
        if (! empty($kw['mejoraron'])) {
            $top = $kw['mejoraron'][0];
            $n = count($kw['mejoraron']);
            $logros[] = sprintf('%d %s %s su posición; la mayor mejora fue «%s» (de la posición %d a la %d).', $n, $n === 1 ? 'keyword' : 'keywords', $n === 1 ? 'mejoró' : 'mejoraron', $top['nombre'], $top['anterior'], $top['actual']);
        }
        if (! empty($kw['empeoraron'])) {
            $top = $kw['empeoraron'][0];
            $n = count($kw['empeoraron']);
            $retos[] = sprintf('%d %s %s de posición; la mayor caída fue «%s» (de la posición %d a la %d).', $n, $n === 1 ? 'keyword' : 'keywords', $n === 1 ? 'bajó' : 'bajaron', $top['nombre'], $top['anterior'], $top['actual']);
        }

        $pm = (array) ($hechos['posicion_media'] ?? []);
        if (isset($pm['mejora'], $pm['actual'], $pm['anterior']) && (float) $pm['mejora'] != 0.0) {
            $lugares = self::num(abs((float) $pm['mejora']), 'decimal');
            $mejoro = $pm['mejora'] > 0;
            $logros[] = $mejoro
                ? sprintf('La posición media de las keywords mejoró %s lugares (de %s a %s).', $lugares, self::num($pm['anterior'], 'decimal'), self::num($pm['actual'], 'decimal'))
                : null;
            if (! $mejoro) {
                $retos[] = sprintf('La posición media de las keywords empeoró %s lugares (de %s a %s).', $lugares, self::num($pm['anterior'], 'decimal'), self::num($pm['actual'], 'decimal'));
            }
        }

        $armar = fn (array $textos) => array_map(
            fn ($t) => ['texto' => mb_substr($t, 0, 280), 'precargada' => true],
            array_slice(array_values(array_filter($textos)), 0, self::MAXIMO)
        );

        return ['logros' => $armar($logros), 'retos' => $armar($retos)];
    }

    private static function pct(float $pct): string
    {
        return str_replace('.', ',', number_format(abs($pct), 1, '.', ''));
    }

    private static function num(mixed $valor, string $formato): string
    {
        $v = (float) $valor;

        return match ($formato) {
            'moneda' => '$'.number_format($v, 2, '.', ','),
            'decimal' => number_format($v, 1, '.', ','),
            default => number_format($v, 0, '.', ','),
        };
    }
}
