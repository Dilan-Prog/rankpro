<?php

namespace App\Support\Tablero\Bloques;

use App\Models\MetricaCliente;
use App\Models\MetricaClienteValor;
use App\Models\User;
use App\Support\Metricas\Semaforo;
use App\Support\Tablero\Contexto;

/**
 * Métricas propias del cliente (llamadas, ventas, cotizaciones…). Es el único bloque con META, así que es el único
 * que califica con Semaforo::evaluar; una métrica sin meta se muestra pero no se califica (gris).
 */
class Metricas extends Bloque
{
    public function clave(): string
    {
        return 'metricas';
    }

    public function titulo(): string
    {
        return 'Métricas propias';
    }

    public function icono(): string
    {
        return 'fa-gauge-high';
    }

    public function permisos(): array
    {
        return ['metricas'];
    }

    public function calcular(Contexto $ctx): array
    {
        $enlace = ['url' => route('admin.metricas.index', ['cliente' => $ctx->cliente->id]), 'etiqueta' => 'Ir a Métricas'];

        $definiciones = MetricaCliente::query()->where('cliente_id', $ctx->cliente->id)->where('activa', true)->orderBy('orden')->orderBy('id')->get();
        if ($definiciones->isEmpty()) {
            return $this->bloque(self::NEUTRO, 'Este cliente no tiene métricas propias definidas.', [], $enlace);
        }

        // El valor más reciente de cada métrica, en una sola consulta (no una por métrica).
        $valores = MetricaClienteValor::query()
            ->whereIn('metrica_id', $definiciones->pluck('id'))
            ->whereRaw('periodo = (select max(v2.periodo) from metrica_cliente_valores v2 where v2.metrica_id = metrica_cliente_valores.metrica_id)')
            ->get()
            ->keyBy('metrica_id');
        $autores = User::query()->whereIn('id', $valores->pluck('registrado_por')->filter()->unique())->pluck('name', 'id');

        $cifras = [];
        $estados = [];
        foreach ($definiciones as $m) {
            $v = $valores->get($m->id);
            $unidad = filled($m->unidad) ? ' '.$m->unidad : '';
            $fuente = 'Métricas · captura manual';

            if ($v === null) {
                $cifras[] = self::cifra($m->nombre, null, null, $fuente, ['nota' => 'Sin valor capturado.']);

                continue;
            }

            $calificacion = Semaforo::evaluar($v->valor, $m->meta, $m->sentido, (int) $m->umbral_amarillo_pct);
            $estado = self::estadoDeSemaforo($calificacion);
            // Un verde con captura vieja no se da por bueno: se baja a amarillo. Un rojo se queda rojo.
            if ($estado === self::VERDE && self::desactualizado($v->updated_at, $ctx->hoy)) {
                $estado = self::AMARILLO;
            }
            if ($estado !== self::NEUTRO) {
                $estados[] = $estado;
            }

            $cifras[] = self::cifra(
                $m->nombre,
                rtrim(rtrim(number_format((float) $v->valor, 2), '0'), '.').$unidad,
                self::periodo((int) $v->periodo->month, (int) $v->periodo->year),
                $fuente,
                [
                    'capturado' => self::fecha($v->updated_at),
                    'registrado_por' => $v->registrado_por ? ($autores[$v->registrado_por] ?? null) : null,
                    'semaforo' => $estado,
                    'nota' => $m->meta === null
                        ? 'Sin meta definida: no se califica.'
                        : 'Meta: '.rtrim(rtrim(number_format((float) $m->meta, 2), '0'), '.').$unidad.' ('.($m->sentido->value === 'menor' ? 'menos es mejor' : 'más es mejor').').',
                ],
            );
        }

        if ($estados === []) {
            $hayValores = $valores->isNotEmpty();

            return $this->bloque(self::NEUTRO, $hayValores ? 'Ninguna métrica tiene meta: se muestran los valores sin calificarlos.' : 'Hay métricas definidas, pero ninguna tiene valor capturado.', $cifras, $enlace);
        }

        $estado = self::peor($estados);
        $motivo = match ($estado) {
            self::ROJO => 'Al menos una métrica está por debajo de su meta.',
            self::AMARILLO => 'Alguna métrica está cerca de su meta o su captura tiene más de '.self::DIAS_SIN_ACTUALIZAR.' días.',
            default => 'Las métricas con meta la cumplen.',
        };

        return $this->bloque($estado, $motivo, $cifras, $enlace);
    }

    /** Traduce el resultado de Semaforo::evaluar a un estado de bloque; sin meta o sin dato es gris. */
    public static function estadoDeSemaforo(string $calificacion): string
    {
        return match ($calificacion) {
            Semaforo::VERDE => self::VERDE,
            Semaforo::AMARILLO => self::AMARILLO,
            Semaforo::ROJO => self::ROJO,
            default => self::NEUTRO,
        };
    }
}
