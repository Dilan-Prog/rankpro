<?php

namespace App\Models;

use App\Enums\EstadoBug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bug extends Model
{
    use HasFactory;

    protected $fillable = [
        'proyecto_id',
        'responsable_id',
        'titulo',
        'descripcion',
        'prioridad',
        'estado',
        'fecha_resolucion',
    ];

    protected $casts = [
        'estado' => EstadoBug::class,
        'fecha_resolucion' => 'date',
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    /** Usuario del panel a cargo del bug; null = sin asignar. */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }
}
