<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Cálculos de lectura de las tarjetas y vistas de Proyectos (Desarrollo).
 *
 * Viven aquí, y no en el controlador ni en Blade, para que tarjeta, tabla y
 * línea de tiempo muestren siempre el mismo número y para poder probarlos
 * sin montar la vista. Son funciones puras: no tocan la BD.
 */
class ProyectoProgreso
{
    /**
     * Avance 0-100 a partir de las tareas (hechas/total). Sin tareas no hay
     * base para calcular, así que devuelve null y quien llama conserva el
     * avance manual que ya guarda el proyecto (fase Dirección).
     */
    public static function porTareas(int $total, int $hechas): ?int
    {
        if ($total <= 0) {
            return null;
        }

        return (int) round(min($hechas, $total) / $total * 100);
    }

    /**
     * Días naturales hasta la entrega estimada (negativo = vencida). Null si
     * no hay fecha o el proyecto ya está cerrado: una entrega pasada de un
     * proyecto terminado no es un retraso y no debe pintarse en rojo.
     */
    public static function diasRestantes(?CarbonInterface $entrega, bool $cerrado = false, ?CarbonInterface $hoy = null): ?int
    {
        if ($entrega === null || $cerrado) {
            return null;
        }

        $hoy = ($hoy ?? Carbon::now())->copy()->startOfDay();

        return (int) $hoy->diffInDays($entrega->copy()->startOfDay(), false);
    }

    /**
     * Parte el texto libre del stack ("Laravel, MySQL / Vue") en chips. El
     * campo es un string de 255, no un arreglo, y cada quien lo separa a su
     * modo, de ahí los varios separadores.
     *
     * @return list<string>
     */
    public static function chipsStack(?string $stack, int $max = 8): array
    {
        if ($stack === null || trim($stack) === '') {
            return [];
        }

        $partes = preg_split('/[,;\/|\n]+/u', $stack) ?: [];
        $partes = array_values(array_unique(array_filter(array_map('trim', $partes), fn ($s) => $s !== '')));

        return array_slice($partes, 0, $max);
    }

    /** Ancho en px de un día en la línea de tiempo: legible sin volver eterno el scroll horizontal. */
    public const PX_DIA = 6;

    private const MESES = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    /**
     * Geometría de la línea de tiempo tipo Gantt a partir de las filas que ya
     * arma DesarrolloController::toRow(). Se calcula en servidor (posiciones
     * en px) para que la vista no necesite JS y para poder probarla.
     *
     * Solo entran proyectos con fecha de inicio y de entrega estimada: sin
     * ambas no hay barra honesta que dibujar, así que van aparte en `sin_fechas`.
     *
     * @param  iterable<array<string,mixed>>  $filas
     * @return array{barras: list<array<string,mixed>>, sin_fechas: list<array<string,mixed>>, meses: list<array<string,mixed>>, ancho: int, hoy_left: ?int}
     */
    public static function lineaTiempo(iterable $filas, ?CarbonInterface $hoy = null): array
    {
        $hoy = ($hoy ?? Carbon::now())->copy()->startOfDay();
        $con = [];
        $sin = [];

        foreach ($filas as $f) {
            $ini = ! empty($f['fecha_inicio']) ? Carbon::parse($f['fecha_inicio'])->startOfDay() : null;
            $fin = ! empty($f['fecha_entrega_estimada']) ? Carbon::parse($f['fecha_entrega_estimada'])->startOfDay() : null;

            if ($ini === null || $fin === null) {
                $sin[] = $f;

                continue;
            }
            // Fechas capturadas al revés: se invierten para que la barra no salga negativa.
            if ($fin->lt($ini)) {
                [$ini, $fin] = [$fin, $ini];
            }
            $con[] = ['fila' => $f, 'ini' => $ini, 'fin' => $fin];
        }

        if ($con === []) {
            return ['barras' => [], 'sin_fechas' => $sin, 'meses' => [], 'ancho' => 0, 'hoy_left' => null];
        }

        // Rango = de inicio de mes del primer proyecto a fin de mes del último,
        // incluyendo hoy para que la línea de hoy siempre tenga dónde caer.
        $min = collect($con)->min(fn ($c) => $c['ini']->timestamp);
        $max = collect($con)->max(fn ($c) => $c['fin']->timestamp);
        $desde = Carbon::createFromTimestamp(min($min, $hoy->timestamp))->startOfMonth()->startOfDay();
        $hasta = Carbon::createFromTimestamp(max($max, $hoy->timestamp))->endOfMonth()->startOfDay();
        $totalDias = (int) $desde->diffInDays($hasta) + 1;

        $meses = [];
        for ($m = $desde->copy(); $m->lte($hasta); $m->addMonthNoOverflow()->startOfMonth()) {
            $dias = $m->daysInMonth;
            $meses[] = [
                'etiqueta' => self::MESES[$m->month].' '.$m->year,
                'left' => (int) $desde->diffInDays($m) * self::PX_DIA,
                'width' => $dias * self::PX_DIA,
            ];
        }

        $barras = [];
        foreach ($con as $c) {
            $f = $c['fila'];
            $left = (int) $desde->diffInDays($c['ini']) * self::PX_DIA;
            $width = ((int) $c['ini']->diffInDays($c['fin']) + 1) * self::PX_DIA;
            $dias = $f['dias_restantes'] ?? null;
            $barras[] = [
                'fila' => $f,
                'left' => $left,
                'width' => $width,
                'avance' => max(0, min(100, (int) ($f['porcentaje_avance'] ?? 0))),
                'vencido' => $dias !== null && $dias < 0,
            ];
        }

        return [
            'barras' => $barras,
            'sin_fechas' => $sin,
            'meses' => $meses,
            'ancho' => $totalDias * self::PX_DIA,
            'hoy_left' => (int) $desde->diffInDays($hoy) * self::PX_DIA + intdiv(self::PX_DIA, 2),
        ];
    }
}
