<?php

namespace App\Models;

use App\Enums\TipoSeccion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una sección de un reporte. `contenido` es JSON cuya forma depende de `tipo`;
 * el contrato de cada forma —y sus reglas de validación— vive en
 * App\Support\Reportes\EsquemaSeccion.
 */
class ReporteSeccion extends Model
{
    use HasFactory;

    protected $table = 'reporte_secciones';

    protected $fillable = [
        'reporte_id',
        'tipo',
        'titulo',
        'rotulo',
        'orden',
        'contenido',
        'aviso',
        'visible',
    ];

    protected $casts = [
        'tipo' => TipoSeccion::class,
        'contenido' => 'array',
        'aviso' => 'array',
        'orden' => 'integer',
        'visible' => 'boolean',
    ];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(Reporte::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo->value,
            'tipo_label' => $this->tipo->label(),
            'titulo' => $this->titulo,
            'rotulo' => $this->rotulo,
            'orden' => $this->orden,
            // `visible` colisiona con la propiedad protegida HidesAttributes::$visible
            // de Eloquent: dentro de la clase, `$this->visible` devuelve ese array
            // vacío en vez de pasar por el cast. Hay que leer el atributo a mano.
            'visible' => (bool) $this->getAttribute('visible'),
            'contenido' => $this->contenido ?? [],
            'aviso' => $this->aviso,
        ];
    }
}
