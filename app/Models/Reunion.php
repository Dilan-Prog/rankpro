<?php

namespace App\Models;

use App\Enums\EstadoReunion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Una reunión agendada desde la página pública /agendar. `cliente_id` se
 * vincula solo si el email coincide con un Cliente existente; si no, la
 * reunión igual vive con nombre/email/teléfono propios.
 */
class Reunion extends Model
{
    use HasFactory;

    protected $table = 'reuniones';

    protected $fillable = [
        'cliente_id', 'nombre', 'email', 'telefono', 'notas', 'inicia_en', 'termina_en', 'estado', 'token',
    ];

    protected $casts = [
        'inicia_en' => 'datetime',
        'termina_en' => 'datetime',
        'estado' => EstadoReunion::class,
    ];

    /** Token aleatorio de 48 caracteres: se asigna al crear si no viene (mismo patrón que CorreoDestinatario). */
    protected static function booted(): void
    {
        static::creating(function (self $reunion) {
            $reunion->token = $reunion->token ?: Str::random(48);
        });
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
