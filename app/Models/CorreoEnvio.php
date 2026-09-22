<?php

namespace App\Models;

use App\Enums\EstadoDestinatarioCorreo;
use App\Enums\EstadoEnvioCorreo;
use App\Support\Correo\Bloques;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un envío: una plantilla (o HTML congelado) mandada a N destinatarios en un
 * momento. Ver la migración para el porqué de `html_congelado`.
 *
 * `bloques`/`marca`/`html_personalizado` son la personalización propia del
 * envío (opcional): nulos = usa la plantilla tal cual; con contenido = el
 * envío manda sobre la plantilla al renderizar (ver contenidoEfectivo()).
 */
class CorreoEnvio extends Model
{
    use HasFactory;

    protected $table = 'correo_envios';

    protected $fillable = [
        'plantilla_id', 'asunto', 'remitente_nombre', 'remitente_email', 'estado',
        'programado_para', 'enviado_en', 'variables', 'bloques', 'marca',
        'html_personalizado', 'html_congelado', 'creado_por',
    ];

    protected $casts = [
        'estado' => EstadoEnvioCorreo::class,
        'programado_para' => 'datetime',
        'enviado_en' => 'datetime',
        'variables' => 'array',
        'bloques' => 'array',
        'marca' => 'array',
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

    public function adjuntos(): HasMany
    {
        return $this->hasMany(CorreoAdjunto::class, 'envio_id')->orderBy('created_at');
    }

    /** True si el envío tiene contenido propio en vez de usar la plantilla tal cual. */
    public function personalizado(): bool
    {
        return $this->bloques !== null || $this->marca !== null || trim((string) $this->html_personalizado) !== '';
    }

    /**
     * Contenido con el que se renderiza este envío: el suyo propio si está
     * personalizado, si no el de la plantilla asociada. Único punto de
     * verdad que consumen EnviadorCorreo, el show() del panel y la previa.
     *
     * @return array{bloques: array<int, array<string, mixed>>, marca: array<string, mixed>, html_libre: ?string}
     */
    public function contenidoEfectivo(): array
    {
        if ($this->personalizado()) {
            return [
                'bloques' => $this->bloques ?? [],
                'marca' => array_replace(Bloques::marcaPorDefecto(), array_filter($this->marca ?? [], fn ($v) => $v !== null)),
                'html_libre' => trim((string) $this->html_personalizado) !== '' ? $this->html_personalizado : null,
            ];
        }

        $plantilla = $this->plantilla;
        if (! $plantilla) {
            return ['bloques' => [], 'marca' => Bloques::marcaPorDefecto(), 'html_libre' => null];
        }

        return [
            'bloques' => $plantilla->bloques ?? [],
            'marca' => $plantilla->marcaCompleta(),
            'html_libre' => $plantilla->esHtmlLibre() ? $plantilla->html_personalizado : null,
        ];
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
        $enviados = $dest->where('estado', EstadoDestinatarioCorreo::Enviado)->count();
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
            'personalizado' => $this->personalizado(),
            'destinatarios' => $total,
            'enviados' => $enviados,
            'fallidos' => $dest->where('estado', EstadoDestinatarioCorreo::Fallido)->count(),
            'abiertos' => $abiertos,
            // Los fallidos nunca pudieron abrirse: el denominador es lo que sí
            // salió, igual que el KPI del index. Así ambas cifras coinciden.
            'no_abiertos' => max($enviados - $abiertos, 0),
            // "Estimada": el píxel no es exacto (ver plan). La UI lo etiqueta así.
            'apertura' => $enviados > 0 ? (int) round($abiertos / $enviados * 100) : null,
            'clics' => (int) $dest->sum('clics'),
            'programado_para' => $this->programado_para?->format('Y-m-d H:i'),
            'enviado_en' => $this->enviado_en?->format('Y-m-d H:i'),
            'fecha' => ($this->enviado_en ?? $this->programado_para ?? $this->updated_at)?->format('Y-m-d H:i'),
            'responsable' => $this->creador?->name,
            'show_url' => route('admin.correo.envios.show', $this),
        ];
    }
}
