<?php

namespace App\Support\Propuestas;

/**
 * Cálculos derivados del PDF/vista previa de una Propuesta — nunca se piden
 * como campo de formulario (ver Plan: "% de variación se calcula, no se
 * captura"), así el dato no puede quedar desincronizado de lo capturado.
 */
class Calculos
{
    public static function numero(?string $valor): ?float
    {
        if ($valor === null || trim($valor) === '') {
            return null;
        }

        $limpio = preg_replace('/[^0-9.\-]/', '', $valor);

        return $limpio !== '' && is_numeric($limpio) ? (float) $limpio : null;
    }

    /**
     * @return array{delta: ?string, sube: ?bool}
     */
    public static function variacion(?string $valor1, ?string $valor2): array
    {
        $n1 = self::numero($valor1);
        $n2 = self::numero($valor2);

        if ($n1 === null || $n2 === null || $n1 == 0.0) {
            return ['delta' => null, 'sube' => null];
        }

        $pct = (($n2 - $n1) / abs($n1)) * 100;

        return [
            'delta' => ($pct >= 0 ? '+' : '').number_format($pct, 2).'%',
            'sube' => $pct >= 0,
        ];
    }

    public static function subtotal(?int $horas, mixed $tarifa): ?float
    {
        if ($horas === null || $tarifa === null) {
            return null;
        }

        return round($horas * (float) $tarifa, 2);
    }
}
