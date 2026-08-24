<?php

namespace App\Models;

use App\Enums\EstadoArticulo;
use App\Support\Clusters;
use App\Support\Servicios;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Articulo del blog publico.
 *
 * El contenido se guarda en Markdown y se renderiza a HTML al guardar
 * (ver App\Support\Contenido\RenderizadorMarkdown), no en cada visita.
 */
class Articulo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'articulos';

    protected $fillable = [
        'slug', 'titulo', 'meta_title', 'meta_description', 'resumen',
        'contenido', 'contenido_html', 'toc', 'palabras',
        'cluster', 'estado', 'autor_id',
        'fecha_publicacion', 'fecha_actualizacion',
        'imagen_destacada', 'imagen_alt', 'og_image',
    ];

    protected $casts = [
        'estado' => EstadoArticulo::class,
        'toc' => 'array',
        'palabras' => 'integer',
        'fecha_publicacion' => 'date',
        'fecha_actualizacion' => 'date',
    ];

    // ------------------------------------------------------------------
    // Relaciones
    // ------------------------------------------------------------------

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }

    /**
     * Articulos relacionados declarados a mano.
     *
     * Si esta vacio, usar relacionadosAutomaticos(): la derivacion por cluster
     * cubre el caso normal y evita tener que mantener 30 listas a mano.
     */
    public function relacionados(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'articulo_relacionado', 'articulo_id', 'relacionado_id')
            ->withPivot('orden')
            ->orderByPivot('orden');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopePublicados(Builder $query): Builder
    {
        return $query->where('estado', EstadoArticulo::Publicado)
            ->whereNotNull('fecha_publicacion')
            // Permite programar: un articulo con fecha futura queda en cola.
            ->whereDate('fecha_publicacion', '<=', now())
            ->orderByDesc('fecha_publicacion')
            ->orderByDesc('id');
    }

    public function scopeDelCluster(Builder $query, string $cluster): Builder
    {
        return $query->where('cluster', $cluster);
    }

    public function scopeDelServicio(Builder $query, string $slugServicio): Builder
    {
        return $query->whereExists(fn ($q) => $q
            ->selectRaw(1)
            ->from('articulo_servicio')
            ->whereColumn('articulo_servicio.articulo_id', 'articulos.id')
            ->where('articulo_servicio.servicio_slug', $slugServicio));
    }

    // ------------------------------------------------------------------
    // Accesores de presentacion
    // ------------------------------------------------------------------

    public function url(): string
    {
        return route('blog.show', $this->slug);
    }

    /** Metadatos del cluster (nombre, url, gradiente...). */
    public function clusterInfo(): ?array
    {
        return Clusters::encontrar($this->cluster);
    }

    /**
     * Servicios del catalogo a los que apoya este articulo.
     *
     * Se resuelven contra App\Support\Servicios en lugar de duplicar nombre y
     * resumen en la tabla: el catalogo es la fuente unica y el copy no debe
     * divergir. Los slugs que ya no existan se descartan en silencio (el
     * comando blog:validar los reporta).
     *
     * @return list<array<string, mixed>>
     */
    public function serviciosRelacionados(): array
    {
        $slugs = $this->slugsServicios();
        $catalogo = Servicios::navegacion();

        return array_values(array_filter(
            $catalogo,
            static fn (array $s) => in_array($s['slug'], $slugs, true)
        ));
    }

    /** @return list<string> */
    public function slugsServicios(): array
    {
        return $this->relacionServicios()->pluck('servicio_slug')->all();
    }

    /**
     * Minutos de lectura. 200 palabras/minuto es la media aceptada para
     * lectura en pantalla en español; se redondea hacia arriba y nunca baja de 1.
     */
    public function minutosLectura(): int
    {
        return max(1, (int) ceil($this->palabras / 200));
    }

    /**
     * Fecha que va a dateModified del schema y al <lastmod> del sitemap.
     * Si no se ha declarado actualizacion, la de publicacion.
     */
    public function fechaEfectiva(): ?\Illuminate\Support\Carbon
    {
        return $this->fecha_actualizacion ?? $this->fecha_publicacion;
    }

    public function fueActualizado(): bool
    {
        return $this->fecha_actualizacion
            && $this->fecha_publicacion
            && $this->fecha_actualizacion->gt($this->fecha_publicacion);
    }

    // ------------------------------------------------------------------
    // Interno
    // ------------------------------------------------------------------

    /** Relacion cruda con la tabla pivote de servicios. */
    public function relacionServicios(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ArticuloServicio::class, 'articulo_id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
