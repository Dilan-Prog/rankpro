<?php

namespace App\Support\SeoPlan;

use App\Models\SeoOnPageAccion;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Geometría de las vistas de solo lectura de las actividades del SEO Plan:
 * línea de tiempo y calendario mensual.
 *
 * Se calcula en servidor (px y semanas del calendario) para que la vista no
 * necesite JS de layout y para poder probarla sin montar Blade. La idea de la
 * línea de tiempo es la misma de Desarrollo (ProyectoProgreso::lineaTiempo),
 * copiada aquí para no acoplar ambos módulos. Son funciones puras: no tocan la BD.
 */
class VistasActividades
{
    /** Ancho en px de un día: una semana mide 56 px, suficiente para rotularla. */
    public const PX_DIA = 8;

    private const MESES_CORTO = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    private const MESES_LARGO = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    /**
     * Datos que el modal de edición lee de data-actividad. Mismo contorno que
     * el de la tarjeta del kanban, para abrir la misma ficha desde cualquier vista.
     *
     * @return array<string,mixed>
     */
    public static function datosModal(SeoOnPageAccion $a): array
    {
        return [
            'id' => $a->id,
            'accion' => $a->accion,
            'tipo' => $a->tipo?->value,
            'estado' => $a->estado->value,
            'cluster_id' => $a->cluster_id,
            'responsable_id' => $a->responsable_id,
            'fecha_limite' => $a->fecha_limite?->format('Y-m-d'),
            'url_pagina' => $a->url_pagina,
            'medicion_antes' => $a->medicion_antes,
            'medicion_despues' => $a->medicion_despues,
        ];
    }

    /**
     * Inicio de la barra: la fecha de inicio capturada o, si no hay, el día en
     * que se creó la actividad. Fin: la fecha límite (sin ella no hay barra honesta).
     *
     * @param  iterable<SeoOnPageAccion>  $actividades
     * @return array{barras: list<array<string,mixed>>, sin_fecha: list<SeoOnPageAccion>, meses: list<array<string,mixed>>, semanas: list<array<string,mixed>>, ancho: int, hoy_left: ?int}
     */
    public static function lineaTiempo(iterable $actividades, ?CarbonInterface $hoy = null): array
    {
        $hoy = ($hoy ?? Carbon::now())->copy()->startOfDay();
        $con = [];
        $sin = [];

        foreach ($actividades as $a) {
            if ($a->fecha_limite === null) {
                $sin[] = $a;

                continue;
            }

            $ini = ($a->fecha ?? $a->created_at ?? $a->fecha_limite)->copy()->startOfDay();
            $fin = $a->fecha_limite->copy()->startOfDay();
            // Fecha de inicio posterior al límite (captura al revés): se invierten para no dibujar ancho negativo.
            if ($fin->lt($ini)) {
                [$ini, $fin] = [$fin, $ini];
            }
            $con[] = ['actividad' => $a, 'ini' => $ini, 'fin' => $fin];
        }

        if ($con === []) {
            return ['barras' => [], 'sin_fecha' => $sin, 'meses' => [], 'semanas' => [], 'ancho' => 0, 'hoy_left' => null];
        }

        // Rango: del inicio de mes de la primera barra al fin de mes de la última, incluyendo hoy para que su línea caiga dentro.
        $min = min(array_map(fn ($c) => $c['ini']->timestamp, $con));
        $max = max(array_map(fn ($c) => $c['fin']->timestamp, $con));
        $desde = Carbon::createFromTimestamp(min($min, $hoy->timestamp))->startOfMonth()->startOfDay();
        $hasta = Carbon::createFromTimestamp(max($max, $hoy->timestamp))->endOfMonth()->startOfDay();
        $totalDias = (int) $desde->diffInDays($hasta) + 1;

        $meses = [];
        for ($m = $desde->copy(); $m->lte($hasta); $m->addMonthNoOverflow()->startOfMonth()) {
            $meses[] = [
                'etiqueta' => self::MESES_CORTO[$m->month].' '.$m->year,
                'left' => (int) $desde->diffInDays($m) * self::PX_DIA,
                'width' => $m->daysInMonth * self::PX_DIA,
            ];
        }

        // Una marca por lunes, rotulada con el día del mes.
        $semanas = [];
        for ($d = $desde->copy()->startOfWeek(Carbon::MONDAY); $d->lte($hasta); $d->addWeek()) {
            if ($d->lt($desde)) {
                continue;
            }
            $semanas[] = ['etiqueta' => (string) $d->day, 'left' => (int) $desde->diffInDays($d) * self::PX_DIA];
        }

        $barras = [];
        foreach ($con as $c) {
            $barras[] = [
                'actividad' => $c['actividad'],
                'left' => (int) $desde->diffInDays($c['ini']) * self::PX_DIA,
                'width' => ((int) $c['ini']->diffInDays($c['fin']) + 1) * self::PX_DIA,
                'inicio' => $c['ini']->format('d/m/Y'),
                'fin' => $c['fin']->format('d/m/Y'),
                'vencida' => $c['actividad']->estaVencida(),
            ];
        }

        return [
            'barras' => $barras,
            'sin_fecha' => $sin,
            'meses' => $meses,
            'semanas' => $semanas,
            'ancho' => $totalDias * self::PX_DIA,
            'hoy_left' => (int) $desde->diffInDays($hoy) * self::PX_DIA + intdiv(self::PX_DIA, 2),
        ];
    }

    /**
     * Un calendario mensual por cada mes del rango contiguo que va del primer
     * al último mes con fecha límite (incluyendo el mes de hoy), para que el
     * navegador solo alterne cuál se ve. Las semanas empiezan en lunes.
     *
     * @param  iterable<SeoOnPageAccion>  $actividades
     * @return array{meses: list<array<string,mixed>>, sin_fecha: list<SeoOnPageAccion>, indice_hoy: int}
     */
    public static function calendario(iterable $actividades, ?CarbonInterface $hoy = null): array
    {
        $hoy = ($hoy ?? Carbon::now())->copy()->startOfDay();

        /** @var Collection<string, list<SeoOnPageAccion>> $porDia */
        $porDia = collect();
        $sin = [];
        foreach ($actividades as $a) {
            if ($a->fecha_limite === null) {
                $sin[] = $a;

                continue;
            }
            $dia = $a->fecha_limite->format('Y-m-d');
            $porDia[$dia] = [...($porDia[$dia] ?? []), $a];
        }

        $primero = $hoy->copy()->startOfMonth();
        $ultimo = $hoy->copy()->startOfMonth();
        foreach ($porDia->keys() as $dia) {
            $mes = Carbon::parse($dia)->startOfMonth();
            $primero = $mes->lt($primero) ? $mes : $primero;
            $ultimo = $mes->gt($ultimo) ? $mes : $ultimo;
        }

        $meses = [];
        $indiceHoy = 0;
        for ($m = $primero->copy(); $m->lte($ultimo); $m->addMonthNoOverflow()->startOfMonth()) {
            if ($m->isSameMonth($hoy)) {
                $indiceHoy = count($meses);
            }

            $semanas = [];
            $dia = $m->copy()->startOfWeek(Carbon::MONDAY);
            $fin = $m->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
            while ($dia->lte($fin)) {
                $semana = [];
                for ($i = 0; $i < 7; $i++) {
                    $clave = $dia->format('Y-m-d');
                    $semana[] = [
                        'fecha' => $clave,
                        'numero' => $dia->day,
                        'del_mes' => $dia->month === $m->month,
                        'hoy' => $dia->isSameDay($hoy),
                        'actividades' => $porDia[$clave] ?? [],
                    ];
                    $dia->addDay();
                }
                $semanas[] = $semana;
            }

            $meses[] = [
                'clave' => $m->format('Y-m'),
                'etiqueta' => self::MESES_LARGO[$m->month].' '.$m->year,
                'semanas' => $semanas,
            ];
        }

        return ['meses' => $meses, 'sin_fecha' => $sin, 'indice_hoy' => $indiceHoy];
    }
}
