<?php

namespace App\Models;

use App\Enums\CategoriaPlantillaCorreo;
use App\Support\Correo\Bloques;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Plantilla de correo: bloques + marca, o HTML libre. La forma del JSON vive en
 * App\Support\Correo\Bloques.
 */
class CorreoPlantilla extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'correo_plantillas';

    protected $fillable = [
        'nombre', 'categoria', 'estado', 'asunto', 'bloques', 'marca', 'html_personalizado', 'creado_por',
    ];

    protected $casts = [
        'categoria' => CategoriaPlantillaCorreo::class,
        'bloques' => 'array',
        'marca' => 'array',
    ];

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function envios(): HasMany
    {
        return $this->hasMany(CorreoEnvio::class, 'plantilla_id');
    }

    /** Marca con los valores por defecto rellenando lo que falte. */
    public function marcaCompleta(): array
    {
        return array_replace(Bloques::marcaPorDefecto(), array_filter($this->marca ?? [], fn ($v) => $v !== null));
    }

    /** True si la plantilla va en modo HTML libre (manda sobre los bloques). */
    public function esHtmlLibre(): bool
    {
        return trim((string) $this->html_personalizado) !== '';
    }

    /**
     * Shape compartido entre el listado y las respuestas AJAX. Los contadores
     * (usos, apertura) se derivan de los envíos: no se guardan en la plantilla.
     * Cargar `envios.destinatarios` antes para no disparar N+1.
     *
     * @return array<string, mixed>
     */
    public function toRow(): array
    {
        $enviados = $this->envios->where('estado', \App\Enums\EstadoEnvioCorreo::Enviado);
        // Denominador = destinatarios que sí recibieron el correo: los fallidos
        // nunca pudieron abrirlo. Mismo criterio que CorreoEnvio::toRow() y el index.
        $destinatarios = $enviados->sum(fn ($e) => $e->destinatarios->where('estado', \App\Enums\EstadoDestinatarioCorreo::Enviado)->count());
        $abiertos = $enviados->sum(fn ($e) => $e->destinatarios->where('aperturas', '>', 0)->count());

        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'categoria' => $this->categoria->value,
            'categoria_label' => $this->categoria->label(),
            'estado' => $this->estado,
            'asunto' => $this->asunto,
            'html_libre' => $this->esHtmlLibre(),
            'usos' => $enviados->count(),
            'apertura' => $destinatarios > 0 ? (int) round($abiertos / $destinatarios * 100) : null,
            'autor' => $this->creador?->name,
            'actualizada' => $this->updated_at?->format('Y-m-d'),
            'show_url' => route('admin.correo.plantillas.show', $this),
        ];
    }
}
