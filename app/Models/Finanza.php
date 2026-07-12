<?php

namespace App\Models;

use App\Enums\EstadoFinanza;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Finanza extends Model
{
    use HasFactory;

    protected $fillable = [
        'cliente_id',
        'servicio_id',
        'concepto',
        'tipo',
        'monto',
        'estado',
        'fecha_emision',
        'fecha_vencimiento',
        'fecha_pago',
        'mes',
        'anio',
        'notas',
    ];

    protected $casts = [
        'estado' => EstadoFinanza::class,
        'monto' => 'decimal:2',
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
        'fecha_pago' => 'date',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }
}
