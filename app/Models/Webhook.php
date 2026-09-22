<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Webhook extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'webhooks';

    protected $fillable = ['nombre', 'url', 'secreto', 'eventos', 'activo', 'creado_por', 'ultimo_disparo_en'];

    protected $casts = [
        'eventos' => 'array',
        'activo' => 'boolean',
        'ultimo_disparo_en' => 'datetime',
    ];

    protected $hidden = ['secreto'];

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(WebhookEntrega::class);
    }

    /** True si el webhook escucha $evento: soporta '*' y 'cliente.*'. */
    public function escuchaA(string $evento): bool
    {
        foreach ($this->eventos ?? [] as $patron) {
            if ($patron === '*' || $patron === $evento) {
                return true;
            }
            if (str_ends_with($patron, '.*') && str_starts_with($evento, substr($patron, 0, -1))) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'url' => $this->url,
            'secreto_mascara' => '••••'.substr($this->secreto, -4),
            'eventos' => $this->eventos,
            'activo' => $this->activo,
            'ultimo_disparo_en' => $this->ultimo_disparo_en?->format('Y-m-d H:i'),
            'creado_por' => $this->creador?->name,
            'creado_en' => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
