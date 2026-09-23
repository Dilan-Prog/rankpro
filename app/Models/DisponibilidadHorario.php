<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Rango horario agendable de un día de la semana (0=domingo..6=sábado, único
 * por día). Usado por CalculadorDisponibilidad para generar los slots.
 */
class DisponibilidadHorario extends Model
{
    protected $table = 'disponibilidad_horarios';

    protected $fillable = ['dia_semana', 'hora_inicio', 'hora_fin', 'activo'];

    protected $casts = [
        'dia_semana' => 'integer',
        'activo' => 'boolean',
    ];
}
