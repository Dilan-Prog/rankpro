<?php

namespace App\Support\Clientes;

use App\Enums\EstadoClienteServicio;
use App\Enums\TipoCobro;
use App\Models\Servicio;
use App\Support\FinanzasMetrics;

/**
 * Totales de una lista de servicios aún no guardados (paso 3 del asistente). El MRR sale de la misma fuente única
 * del sistema (FinanzasMetrics::mrrDeServicios / Servicio::aportaMrr) con servicios en memoria: así lo que ve el
 * usuario mientras captura es exactamente lo que el Dashboard sumará al guardar.
 */
class ResumenServicios
{
    /**
     * @param  array<int, array{tipo?: ?string, nombre?: ?string, tipo_cobro?: ?string, precio_mensual?: mixed}>  $servicios
     * @return array{mrr: float, unico: float, mensuales: int, unicos: int, gratuitos: int, sin_precio: int}
     */
    public static function calcular(array $servicios): array
    {
        $enMemoria = [];
        $unico = 0.0;
        $conteo = ['mensuales' => 0, 'unicos' => 0, 'gratuitos' => 0];
        $sinPrecio = 0;

        foreach ($servicios as $s) {
            $cobro = TipoCobro::tryFrom((string) ($s['tipo_cobro'] ?? '')) ?? TipoCobro::Mensual;
            $precio = $cobro === TipoCobro::Gratuito ? 0.0 : max(0.0, (float) str_replace(',', '.', (string) ($s['precio_mensual'] ?? 0)));

            $servicio = new Servicio(['tipo_cobro' => $cobro->value, 'estado' => EstadoClienteServicio::Activo->value, 'precio_mensual' => $precio]);
            $enMemoria[] = $servicio;

            match ($cobro) {
                TipoCobro::Mensual => $conteo['mensuales']++,
                TipoCobro::Unico => $conteo['unicos']++,
                TipoCobro::Gratuito => $conteo['gratuitos']++,
            };
            if ($cobro === TipoCobro::Unico) {
                $unico += $precio;
            }
            if ($cobro !== TipoCobro::Gratuito && $precio <= 0) {
                $sinPrecio++;
            }
        }

        return [
            'mrr' => FinanzasMetrics::mrrDeServicios($enMemoria),
            'unico' => $unico,
            'sin_precio' => $sinPrecio,
        ] + $conteo;
    }
}
