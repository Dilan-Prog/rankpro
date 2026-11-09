<?php

namespace App\Support\Tablero\Bloques;

use App\Support\ResumenAgencia;
use App\Support\Tablero\Contexto;
use Illuminate\Support\Carbon;

/**
 * Alertas del cliente: las mismas de Inicio y el Dashboard (ResumenAgencia::alertas, una sola regla), acotadas
 * a este cliente y a los módulos que el usuario puede ver. Hoy cubren renovación de contrato, pagos vencidos y ROAS.
 */
class Alertas extends Bloque
{
    /** Colores con los que ResumenAgencia marca la gravedad de cada alerta. */
    private const COLOR_ROJO = '#EF4444';
    private const COLOR_AMARILLO = '#F59E0B';

    public function clave(): string
    {
        return 'alertas';
    }

    public function titulo(): string
    {
        return 'Alertas';
    }

    public function icono(): string
    {
        return 'fa-bell';
    }

    public function permisos(): array
    {
        return ['clientes', 'finanzas', 'ads'];
    }

    public function calcular(Contexto $ctx): array
    {
        $alertas = ResumenAgencia::alertas(Carbon::instance($ctx->hoy), $ctx->usuario, $ctx->cliente);

        $niveles = array_map(fn (array $a) => match ($a['color']) {
            self::COLOR_ROJO => self::ROJO,
            self::COLOR_AMARILLO => self::AMARILLO,
            default => self::NEUTRO,
        }, $alertas);

        $fuente = 'Alertas de la agencia (contratos, finanzas y Ads)';
        $cifras = array_map(fn (array $a, string $nivel) => self::cifra(
            $a['msg'],
            match ($nivel) {
                self::ROJO => 'Urgente',
                self::AMARILLO => 'Atención',
                default => 'Informativa',
            },
            self::fecha($ctx->hoy),
            $fuente,
            ['semaforo' => $nivel, 'nota' => null, 'enlace' => $a['href']],
        ), $alertas, $niveles);

        if ($alertas === []) {
            return $this->bloque(self::VERDE, 'Sin alertas activas para este cliente.', [], null);
        }

        $estado = in_array(self::ROJO, $niveles, true) ? self::ROJO : (in_array(self::AMARILLO, $niveles, true) ? self::AMARILLO : self::VERDE);

        return $this->bloque($estado, count($alertas).' '.(count($alertas) === 1 ? 'alerta activa' : 'alertas activas').'.', $cifras, null);
    }
}
