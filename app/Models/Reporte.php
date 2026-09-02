<?php

namespace App\Models;

use App\Enums\AreaReporte;
use App\Enums\EstadoReporte;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Reporte entregable al cliente. El contenido no vive en columnas: vive en una
 * lista ordenada de secciones tipadas (ver ReporteSeccion), de modo que las tres
 * áreas —SEO, Ads y Sitio Web— comparten tablas, editor y renderizadores, y
 * añadir un área nueva sea solo una plantilla de secciones.
 */
class Reporte extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'reportes';

    protected $fillable = [
        'cliente_id',
        'area',
        'sitio_web',
        'seo_campana_id',
        'ads_campana_id',
        'titulo',
        'periodo_inicio',
        'periodo_fin',
        'comparativa_inicio',
        'comparativa_fin',
        'fecha_emision',
        'estado',
        'numero',
        'version_etiqueta',
        'notas_alcance',
        'fuentes',
        'creado_por',
    ];

    protected $casts = [
        'area' => AreaReporte::class,
        'estado' => EstadoReporte::class,
        'periodo_inicio' => 'date',
        'periodo_fin' => 'date',
        'comparativa_inicio' => 'date',
        'comparativa_fin' => 'date',
        'fecha_emision' => 'date',
        'fuentes' => 'array',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function seoCampana(): BelongsTo
    {
        return $this->belongsTo(SeoCampana::class, 'seo_campana_id');
    }

    public function adsCampana(): BelongsTo
    {
        return $this->belongsTo(AdsCampana::class, 'ads_campana_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function secciones(): HasMany
    {
        return $this->hasMany(ReporteSeccion::class)->orderBy('orden');
    }

    /** Solo las secciones que se imprimen en el entregable. */
    public function seccionesVisibles(): HasMany
    {
        return $this->secciones()->where('visible', true);
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(ReporteEntrega::class)->orderByDesc('created_at');
    }

    /**
     * Shape compartido entre el render inicial del listado y las respuestas AJAX
     * de store/update, para que la fila de la tabla se repinte sin recargar.
     *
     * @return array<string, mixed>
     */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'cliente_id' => $this->cliente_id,
            'cliente' => $this->cliente?->empresa ?: $this->cliente?->nombre,
            'area' => $this->area->value,
            'area_label' => $this->area->label(),
            'titulo' => $this->titulo,
            'periodo' => $this->periodo_inicio?->format('Y-m-d').' — '.$this->periodo_fin?->format('Y-m-d'),
            'periodo_inicio' => $this->periodo_inicio?->format('Y-m-d'),
            'periodo_fin' => $this->periodo_fin?->format('Y-m-d'),
            'estado' => $this->estado->value,
            'numero' => $this->numero,
            'entregas' => $this->entregas_count ?? $this->entregas()->count(),
            'actualizado' => $this->updated_at?->format('Y-m-d H:i'),
            'show_url' => route('admin.reportes.show', $this),
        ];
    }

    /**
     * Scoped binding de las rutas `reportes/{reporte}/secciones/{seccion}`.
     *
     * Laravel deriva el nombre de la relación pluralizando el parámetro en
     * inglés ({seccion} -> `seccions`), que aquí no existe. Se mapea a mano en
     * vez de renombrar la relación: devolver null hace que el router lance el
     * 404, que es justo la comprobación de pertenencia que se busca.
     */
    public function resolveChildRouteBinding($childType, $value, $field)
    {
        if ($childType === 'seccion') {
            return $this->secciones()->where($field ?: 'id', $value)->first();
        }

        return parent::resolveChildRouteBinding($childType, $value, $field);
    }

    protected static function booted(): void
    {
        static::deleting(function (self $reporte) {
            if (! $reporte->isForceDeleting()) {
                return;
            }

            // Las secciones y las entregas ya caen solas: sus FK son
            // ON DELETE CASCADE. Lo que no cae es el Archivo de cada entrega
            // —su FK apunta al revés y es nullOnDelete— ni el fichero en el
            // disco, que quedaba ocupando espacio para siempre. Mismo patrón
            // que el borrado en cascada de Cliente.
            $reporte->entregas()->with('archivo')->get()
                ->each(function (ReporteEntrega $entrega) {
                    $archivo = $entrega->archivo;

                    if (! $archivo) {
                        return;
                    }

                    if (Storage::disk('local')->exists($archivo->ruta_archivo)) {
                        Storage::disk('local')->delete($archivo->ruta_archivo);
                    }

                    $archivo->delete();
                });
        });
    }
}
