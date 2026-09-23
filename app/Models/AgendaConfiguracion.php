<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fila única (id=1) con los parámetros de la agenda pública: si está activa,
 * la duración de cada reunión, la anticipación mínima y cuántos días hacia
 * adelante se muestran. Mismo patrón que ConfiguracionSmtp.
 */
class AgendaConfiguracion extends Model
{
    protected $table = 'agenda_configuracion';

    protected $fillable = [
        'activa', 'duracion_minutos', 'anticipacion_minima_horas', 'dias_visibles', 'notificar_email',
    ];

    protected $casts = [
        'activa' => 'boolean',
        'duracion_minutos' => 'integer',
        'anticipacion_minima_horas' => 'integer',
        'dias_visibles' => 'integer',
    ];

    public static function actual(): ?self
    {
        return static::query()->find(1);
    }
}
