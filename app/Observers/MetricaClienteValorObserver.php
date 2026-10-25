<?php

namespace App\Observers;

use App\Models\Cliente;
use App\Models\MetricaClienteValor;
use App\Support\Metricas\Semaforo;

class MetricaClienteValorObserver
{
    public bool $afterCommit = true;

    public function created(MetricaClienteValor $registro): void
    {
        $this->emitir($registro);
    }

    public function updated(MetricaClienteValor $registro): void
    {
        // Editar solo la nota no es un dato nuevo para n8n.
        if ($registro->valorCambio) {
            $this->emitir($registro);
        }
    }

    public function deleted(MetricaClienteValor $registro): void
    {
        webhook('metrica.valor_eliminado', $this->datos($registro)['datos']);
    }

    private function emitir(MetricaClienteValor $registro): void
    {
        ['datos' => $datos, 'semaforo' => $semaforo] = $this->datos($registro);

        webhook('metrica.valor_registrado', $datos);

        if ($semaforo === Semaforo::ROJO) {
            webhook('metrica.semaforo_rojo', $datos);
        }
    }

    /** @return array{datos: array<string, mixed>, semaforo: string} */
    private function datos(MetricaClienteValor $registro): array
    {
        $metrica = $registro->metrica;
        $cliente = Cliente::withTrashed()->find($metrica->cliente_id);
        $anterior = $metrica->valores()
            // where y no whereDate: periodo es DATE (día 1) y whereDate anula el índice.
            ->where('periodo', $registro->periodo->copy()->subMonthNoOverflow()->startOfMonth()->toDateString())
            ->value('valor');
        $semaforo = Semaforo::evaluar($registro->valor, $metrica->meta, $metrica->sentido, (int) $metrica->umbral_amarillo_pct);

        return ['semaforo' => $semaforo, 'datos' => [
            'cliente_id' => $metrica->cliente_id,
            'cliente' => $cliente?->nombre,
            'metrica_id' => $metrica->id,
            'nombre' => $metrica->nombre,
            'unidad' => $metrica->unidad,
            'periodo' => $registro->periodo->format('Y-m'),
            'valor' => (float) $registro->valor,
            'valor_anterior' => $anterior === null ? null : (float) $anterior,
            'meta' => $metrica->meta === null ? null : (float) $metrica->meta,
            'sentido' => $metrica->sentido->value,
            'semaforo' => $semaforo,
        ]];
    }
}
