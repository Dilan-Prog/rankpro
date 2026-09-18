<?php

namespace App\Models;

use App\Enums\EstadoDestinatarioCorreo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CorreoDestinatario extends Model
{
    use HasFactory;

    protected $table = 'correo_destinatarios';

    protected $fillable = [
        'envio_id', 'cliente_id', 'email', 'nombre', 'variables', 'token', 'estado',
        'enviado_en', 'error', 'primera_apertura_en', 'aperturas', 'clics',
    ];

    protected $casts = [
        'estado' => EstadoDestinatarioCorreo::class,
        'variables' => 'array',
        'enviado_en' => 'datetime',
        'primera_apertura_en' => 'datetime',
        'aperturas' => 'integer',
        'clics' => 'integer',
    ];

    /** Token aleatorio de 48 caracteres: se asigna al crear si no viene. */
    protected static function booted(): void
    {
        static::creating(function (self $d) {
            $d->token = $d->token ?: Str::random(48);
        });
    }

    public function envio(): BelongsTo
    {
        return $this->belongsTo(CorreoEnvio::class, 'envio_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(CorreoEvento::class, 'destinatario_id')->orderByDesc('created_at');
    }

    /**
     * @return array<string, mixed>
     */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'nombre' => $this->nombre,
            'cliente_id' => $this->cliente_id,
            'cliente' => $this->cliente?->empresa ?: $this->cliente?->nombre,
            'estado' => $this->estado->value,
            'enviado_en' => $this->enviado_en?->format('Y-m-d H:i'),
            'error' => $this->error,
            'primera_apertura_en' => $this->primera_apertura_en?->format('Y-m-d H:i'),
            'aperturas' => $this->aperturas,
            'clics' => $this->clics,
        ];
    }
}
