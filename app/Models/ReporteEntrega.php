<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro histórico de cada vez que un reporte se generó en un formato. No se
 * sobrescribe: regenerar añade una entrega nueva, de modo que quede rastro de
 * qué versión se envió al cliente y cuándo.
 */
class ReporteEntrega extends Model
{
    use HasFactory;

    protected $table = 'reporte_entregas';

    protected $fillable = [
        'reporte_id',
        'archivo_id',
        'formato',
        'version',
        'generado_por',
    ];

    protected $casts = [
        'version' => 'integer',
    ];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(Reporte::class);
    }

    public function archivo(): BelongsTo
    {
        return $this->belongsTo(Archivo::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por');
    }
}
