<?php

namespace App\Support\SeoPlan;

use App\Enums\EstadoOnPageAccion;
use Illuminate\Support\Collection;

/**
 * Resumen de solo lectura de la pestaña «Resultados». Son funciones puras
 * sobre datos ya cargados: todo sale de lo capturado, nada se estima, y un
 * conteo en cero se muestra como estado vacío en la vista, no como éxito.
 */
class Resultados
{
    /**
     * Cuántas keywords hay en cada rango de posición actual.
     *
     * @param  array<int, array{actual: ?int, anterior: ?int}>  $posiciones
     * @return array{top3: int, top10: int, top20: int, resto: int, sin_posicion: int, con_posicion: int}
     */
    public static function rangos(array $posiciones): array
    {
        $r = ['top3' => 0, 'top10' => 0, 'top20' => 0, 'resto' => 0, 'sin_posicion' => 0, 'con_posicion' => 0];

        foreach ($posiciones as $p) {
            $pos = $p['actual'] ?? null;
            if ($pos === null || $pos < 1) {
                $r['sin_posicion']++;

                continue;
            }
            $r['con_posicion']++;
            $r[match (true) {
                $pos <= 3 => 'top3',
                $pos <= 10 => 'top10',
                $pos <= 20 => 'top20',
                default => 'resto',
            }]++;
        }

        return $r;
    }

    /**
     * Cierre de actividades contra su fecha límite.
     * Una actividad hecha sin fecha límite no cuenta ni a tiempo ni tarde.
     *
     * @param  Collection<int, \App\Models\SeoOnPageAccion>  $actividades
     * @return array{total: int, cerradas: int, a_tiempo: int, tarde: int, sin_fecha_limite: int, abiertas: int, vencidas: int}
     */
    public static function cierres(Collection $actividades): array
    {
        $cerradas = $actividades->filter(fn ($a) => $a->estado === EstadoOnPageAccion::Completada);
        $abiertas = $actividades->reject(fn ($a) => $a->estado === EstadoOnPageAccion::Completada);

        $conFecha = $cerradas->filter(fn ($a) => $a->fecha_limite !== null && $a->cerrada_en !== null);

        return [
            'total' => $actividades->count(),
            'cerradas' => $cerradas->count(),
            'a_tiempo' => $conFecha->filter(fn ($a) => $a->cerrada_en->lte($a->fecha_limite))->count(),
            'tarde' => $conFecha->filter(fn ($a) => $a->cerrada_en->gt($a->fecha_limite))->count(),
            'sin_fecha_limite' => $cerradas->count() - $conFecha->count(),
            'abiertas' => $abiertas->count(),
            'vencidas' => $abiertas->filter(fn ($a) => $a->estaVencida())->count(),
        ];
    }

    /**
     * Actividades cerradas que tienen al menos una medición capturada.
     *
     * @param  Collection<int, \App\Models\SeoOnPageAccion>  $actividades
     * @return Collection<int, \App\Models\SeoOnPageAccion>
     */
    public static function conMedicion(Collection $actividades): Collection
    {
        return $actividades
            ->filter(fn ($a) => $a->estado === EstadoOnPageAccion::Completada
                && (filled($a->medicion_antes) || filled($a->medicion_despues)))
            ->sortByDesc(fn ($a) => $a->cerrada_en?->timestamp ?? 0)
            ->values();
    }
}
