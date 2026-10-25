<?php

namespace App\Support\Metricas;

use App\Models\MetricaClienteValor;
use Carbon\Carbon;
use Illuminate\Database\QueryException;

/**
 * Única forma de guardar el valor de una métrica en un mes, usada por la
 * pantalla web y por la API, para que la regla "vacío borra, 0 se guarda"
 * no pueda divergir entre las dos.
 */
class GuardadorValores
{
    /**
     * @param  bool  $conservarNota  la API no obliga a reenviar la nota en cada reintento
     * @return MetricaClienteValor|null null cuando el valor vacío borró el registro
     */
    public static function guardar(int $metricaId, Carbon $periodo, mixed $valor, ?string $nota, ?int $usuarioId, bool $conservarNota = false): ?MetricaClienteValor
    {
        // Vacío = sin dato: se borra lo que hubiera, jamás se guarda un 0 implícito.
        if ($valor === null || $valor === '') {
            // get()->each->delete() y no delete() masivo: así el Observer avisa a n8n.
            MetricaClienteValor::query()->where('metrica_id', $metricaId)->whereDate('periodo', $periodo)->get()->each->delete();

            return null;
        }

        $campos = ['valor' => $valor, 'registrado_por' => $usuarioId];
        if (! $conservarNota) {
            $campos['nota'] = $nota === '' ? null : $nota;
        }

        $llave = ['metrica_id' => $metricaId, 'periodo' => $periodo->toDateString()];

        try {
            return MetricaClienteValor::updateOrCreate($llave, $campos);
        } catch (QueryException $e) {
            // Otra escritura creó el (métrica, mes) entre el SELECT y el INSERT: el reintento ya lo encuentra y actualiza.
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }

            return MetricaClienteValor::updateOrCreate($llave, $campos);
        }
    }
}
