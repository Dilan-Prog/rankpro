<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fila única (id=1) con el SMTP que usa el panel en vez del .env, cuando
 * `activa` es true. `password` va cifrada en la BD (cast 'encrypted', usa
 * APP_KEY) — nunca se manda de vuelta a la vista en texto plano.
 */
class ConfiguracionSmtp extends Model
{
    use HasFactory;

    protected $table = 'configuracion_smtp';

    protected $fillable = [
        'activa', 'host', 'puerto', 'cifrado', 'usuario', 'password',
        'remitente_email', 'remitente_nombre', 'actualizado_por',
    ];

    protected $casts = [
        'activa' => 'boolean',
        'puerto' => 'integer',
        'password' => 'encrypted',
    ];

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    /** La única fila que existe (o null si nunca se guardó nada). */
    public static function actual(): ?self
    {
        return static::query()->find(1);
    }
}
