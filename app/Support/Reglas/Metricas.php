<?php

namespace App\Support\Reglas;

use App\Enums\SentidoMetrica;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

/**
 * Reglas de validación y normalización de Métricas, compartidas entre el módulo
 * web (Admin\MetricaClienteController, Admin\MetricasController) y la API
 * (Api\V1\Metricas\*) para que una cifra se acepte igual por ambas puertas.
 */
class Metricas
{
    public const LIMITE = 'between:-999999999999.99,999999999999.99';

    /** Definición de una métrica (sin cliente_id: cada llamador lo resuelve a su manera). */
    public static function definicion(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'unidad' => ['nullable', 'string', 'max:20'],
            // min:0 porque con meta negativa meta*umbral > meta y el amarillo sería inalcanzable.
            'meta' => ['nullable', 'numeric', 'min:0', self::LIMITE],
            'sentido' => ['required', Rule::enum(SentidoMetrica::class)],
            'umbral_amarillo_pct' => ['required', 'integer', 'between:1,100'],
            'orden' => ['nullable', 'integer', 'between:0,65535'],
        ];
    }

    /** Valor de una métrica en un periodo: vacío significa "sin dato" (borra). */
    public static function valor(): array
    {
        return ['nullable', 'numeric', self::LIMITE];
    }

    /** Admite coma decimal ("1,5") y espacios; deja null los vacíos. */
    public static function normalizarDecimal(mixed $v): mixed
    {
        if (! is_string($v)) {
            return $v;
        }
        $v = trim(str_replace(' ', '', $v));
        if ($v !== '' && ! str_contains($v, '.')) {
            $v = str_replace(',', '.', $v);
        }

        return $v === '' ? null : $v;
    }

    /** "2026-10" (o cualquier día de ese mes) -> día 1; null si no es un periodo válido. */
    public static function periodo(mixed $texto): ?Carbon
    {
        if (! is_string($texto) || ! preg_match('/^(\d{4})-(0[1-9]|1[0-2])(?:-(0[1-9]|[12]\d|3[01]))?$/', $texto, $m)) {
            return null;
        }

        return Carbon::createFromFormat('!Y-m', "{$m[1]}-{$m[2]}");
    }
}
