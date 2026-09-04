<?php

namespace App\Models;

use App\Enums\EstadoKeyword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KeywordLista extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'keyword_listas';

    protected $fillable = [
        'cliente_id',
        'responsable_id',
        'nombre',
        'canal',
        'estado',
    ];

    protected $casts = [
        'estado' => EstadoKeyword::class,
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class, 'lista_id');
    }

    /**
     * Mediciones que se tomaron estando la keyword en esta lista. Es trazabilidad:
     * la matriz del histórico NO sale de aquí, sino de las keywords que la lista
     * tiene ahora (ver KeywordMedicionController::index).
     */
    public function mediciones(): HasMany
    {
        return $this->hasMany(KeywordMedicion::class, 'lista_id');
    }

    /**
     * Deleting a list must not delete its keywords, only detach them — this
     * runs on soft delete too, unlike the FK's nullOnDelete which only fires
     * on a physical row removal.
     */
    protected static function booted(): void
    {
        static::deleting(function (KeywordLista $lista) {
            $lista->keywords()->update(['lista_id' => null]);
        });
    }

    /** Shared shape for admin.keywords index rows and the CRUD AJAX responses. */
    public function toRow(): array
    {
        $keywords = $this->keywords->map(fn (Keyword $k) => $k->toRow());
        $count = $keywords->count();
        $fuentes = $keywords->pluck('herramienta_origen')->filter()->unique()->values();

        return [
            'id' => $this->id,
            'cliente_id' => $this->cliente_id,
            'cliente' => $this->cliente?->nombre ?? '—',
            'responsable_id' => $this->responsable_id,
            'responsable_nombre' => $this->responsable?->name,
            'nombre' => $this->nombre,
            'canal' => $this->canal,
            'estado' => $this->estado->value,
            'updated_at' => $this->updated_at?->format('Y-m-d'),
            'keywords' => $keywords->all(),
            'keywords_count' => $count,
            'volumen_total' => $keywords->sum('volumen_busqueda'),
            'kd_promedio' => self::promedio($keywords, 'dificultad', fn ($v) => (int) round($v)),
            'cpc_promedio' => self::promedio($keywords, 'cpc_estimado', fn ($v) => round($v, 2)),
            'posicion_promedio' => self::promedio($keywords, 'posicion_actual', fn ($v) => round($v, 1)),
            'fuentes' => $fuentes->all(),
        ];
    }

    /**
     * Averages only over keywords that actually have a value for $field —
     * unlike a plain sum()/count(), this doesn't let nulls silently count as
     * zero and drag the average down when some keywords lack the metric.
     */
    private static function promedio($keywords, string $field, callable $round)
    {
        $valores = $keywords->pluck($field)->filter(fn ($v) => $v !== null);

        return $valores->isEmpty() ? null : $round($valores->sum() / $valores->count());
    }
}
