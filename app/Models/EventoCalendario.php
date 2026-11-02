<?php

namespace App\Models;

use App\Enums\TipoEventoCalendario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Evento manual del Calendario. Lo que viene de otros módulos lo junta App\Support\Calendario\AgregadorEventos. */
class EventoCalendario extends Model
{
    protected $table = 'eventos_calendario';

    protected $fillable = ['titulo', 'tipo', 'fecha', 'hora', 'cliente_id', 'user_id'];

    protected $casts = [
        'tipo' => TipoEventoCalendario::class,
        'fecha' => 'date',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
