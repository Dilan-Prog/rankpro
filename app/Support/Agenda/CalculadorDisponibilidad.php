<?php

namespace App\Support\Agenda;

use App\Enums\EstadoReunion;
use App\Models\AgendaConfiguracion;
use App\Models\DisponibilidadBloqueo;
use App\Models\DisponibilidadHorario;
use App\Models\Reunion;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

/**
 * Calcula los huecos agendables de un día y si un rango puntual sigue libre,
 * a partir de AgendaConfiguracion + DisponibilidadHorario + DisponibilidadBloqueo
 * + las reuniones ya confirmadas. Sin timezone explícito: todo en hora local
 * del servidor, igual que programado_para en el módulo Correo.
 */
class CalculadorDisponibilidad
{
    /** @return array<int, Carbon> horas de inicio disponibles ese día */
    public function disponiblesEn(CarbonImmutable $fecha): array
    {
        $config = AgendaConfiguracion::actual();
        if (! $config || ! $config->activa) {
            return [];
        }

        $limiteMinimo = now()->addHours($config->anticipacion_minima_horas);
        $limiteMaximo = today()->addDays($config->dias_visibles);
        if ($fecha->startOfDay()->gt($limiteMaximo)) {
            return [];
        }

        $horario = DisponibilidadHorario::where('dia_semana', $fecha->dayOfWeek)->where('activo', true)->first();
        if (! $horario) {
            return [];
        }

        if (DisponibilidadBloqueo::where('fecha', $fecha->toDateString())->exists()) {
            return [];
        }

        $duracion = $config->duracion_minutos;
        $inicio = $fecha->setTimeFromTimeString($horario->hora_inicio);
        $fin = $fecha->setTimeFromTimeString($horario->hora_fin);

        $reunionesDelDia = Reunion::where('estado', '!=', EstadoReunion::Cancelada)
            ->whereDate('inicia_en', $fecha->toDateString())
            ->get(['inicia_en', 'termina_en']);

        $slots = [];
        $cursor = $inicio;
        while ($cursor->copy()->addMinutes($duracion)->lte($fin)) {
            // CarbonImmutable no tiene toCarbon(): el método correcto es
            // toMutable(), necesario para poder mutar $slotFin con copy()/add
            // sin arrastrar el tipo inmutable a lo que se compara y se retorna.
            $slotInicio = $cursor->toMutable();
            $slotFin = $slotInicio->copy()->addMinutes($duracion);

            $antesDeAnticipacion = $slotInicio->lt($limiteMinimo);
            $ocupado = $reunionesDelDia->contains(fn ($r) => $slotInicio->lt($r->termina_en) && $slotFin->gt($r->inicia_en));

            if (! $antesDeAnticipacion && ! $ocupado) {
                $slots[] = $slotInicio;
            }

            $cursor = $cursor->addMinutes($duracion);
        }

        return $slots;
    }

    /**
     * $ignorarReunionId excluye una reunión de la comprobación de traslape —
     * lo usa el reagendado, para que la reunión no choque contra su propio
     * horario actual al validar el nuevo.
     */
    public function estaLibre(Carbon $inicio, Carbon $fin, ?int $ignorarReunionId = null): bool
    {
        $config = AgendaConfiguracion::actual();
        if (! $config || ! $config->activa) {
            return false;
        }
        if ($inicio->lt(now()->addHours($config->anticipacion_minima_horas))) {
            return false;
        }
        if (DisponibilidadBloqueo::where('fecha', $inicio->toDateString())->exists()) {
            return false;
        }
        $horario = DisponibilidadHorario::where('dia_semana', $inicio->dayOfWeek)->where('activo', true)->first();
        if (! $horario) {
            return false;
        }
        $horaInicio = $inicio->copy()->setTimeFromTimeString($horario->hora_inicio);
        $horaFin = $inicio->copy()->setTimeFromTimeString($horario->hora_fin);
        if ($inicio->lt($horaInicio) || $fin->gt($horaFin)) {
            return false;
        }

        return ! Reunion::where('estado', '!=', EstadoReunion::Cancelada)
            ->when($ignorarReunionId, fn ($q) => $q->where('id', '!=', $ignorarReunionId))
            ->whereDate('inicia_en', $inicio->toDateString())
            ->where(fn ($q) => $q->where('inicia_en', '<', $fin)->where('termina_en', '>', $inicio))
            ->exists();
    }
}
