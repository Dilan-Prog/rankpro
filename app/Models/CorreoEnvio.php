<?php

namespace App\Models;

use App\Enums\EstadoDestinatarioCorreo;
use App\Enums\EstadoEnvioCorreo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un envío: una plantilla (o HTML congelado) mandada a N destinatarios en un
 * momento. Ver la migración para el porqué de `html_congelado`.
 */
class CorreoEnvio extends Model
{
    use HasFactory;

    protected $table = 'correo_envios';

    protected $fillable = [
        'plantilla_id', 'asunto', 'remitente_nombre', 'remitente_email', 'estado',
        'programado_para', 'enviado_en', 'variables', 'html_congelado', 'creado_por',
    ];

    protected $casts = [
        'estado' => EstadoEnvioCorreo::class,
        'programado_para' => 'datetime',
        'enviado_en' => 'datetime',
        'variables' => 'array',
    ];

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(CorreoPlantilla::class, 'plantilla_id')->withTrashed();
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function destinatarios(): HasMany
    {
        return $this->hasMany(CorreoDestinatario::class, 'envio_id');
    }

    /** Envíos programados cuya hora ya venció: lo que consume el scheduler. */
    public function scopeVencidos($query)
    {
        return $query->where('estado', EstadoEnvioCorreo::Programado)
            ->whereNotNull('programado_para')
            ->where('programado_para', '<=', now());
    }

    /**
     * Cargar `destinatarios` antes para no disparar N+1.
     *
     * @return array<string, mixed>
     */
    public function toRow(): array
    {
        $dest = $this->destinatarios;
        $total = $dest->count();
        $abiertos = $dest->where('aperturas', '>', 0)->count();

        return [
            'id' => $this->id,
            'asunto' => $this->asunto,
            'plantilla' => $this->plantilla?->nombre,
            'plantilla_id' => $this->plantilla_id,
            'remitente_nombre' => $this->remitente_nombre,
            'remitente_email' => $this->remitente_email,
            'estado' => $this->estado->value,
            'estado_label' => $this->estado->label(),
            'editable' => $this->estado->editable(),
            'destinatarios' => $total,
            'enviados' => $dest->where('estado', EstadoDestinatarioCorreo::Enviado)->count(),
            'fallidos' => $dest->where('estado', EstadoDestinatarioCorreo::Fallido)->count(),
            'abiertos' => $abiertos,
            // "Estimada": el píxel no es exacto (ver plan). La UI lo etiqueta así.
            'apertura' => $total > 0 ? (int) round($abiertos / $total * 100) : null,
            'clics' => (int) $dest->sum('clics'),
            'programado_para' => $this->programado_para?->format('Y-m-d H:i'),
            'enviado_en' => $this->enviado_en?->format('Y-m-d H:i'),
            'fecha' => ($this->enviado_en ?? $this->programado_para ?? $this->updated_at)?->format('Y-m-d H:i'),
            'responsable' => $this->creador?->name,
            'show_url' => route('admin.correo.envios.show', $this),
        ];
    }
}
