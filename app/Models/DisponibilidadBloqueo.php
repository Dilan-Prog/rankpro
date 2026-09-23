<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un día puntual sin disponibilidad (feriado, vacaciones) aunque ese día de
 * la semana tenga horario activo.
 */
class DisponibilidadBloqueo extends Model
{
    protected $table = 'disponibilidad_bloqueos';

    protected $fillable = ['fecha', 'motivo'];

    protected $casts = [
        'fecha' => 'date',
    ];
}
