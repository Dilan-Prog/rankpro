<?php

namespace App\Models;

use App\Enums\EstadoTarea;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tarea extends Model
{
    use HasFactory;

    protected $fillable = [
        'proyecto_id',
        'titulo',
        'descripcion',
        'responsable',
        'prioridad',
        'estado',
        'fecha_limite',
    ];

    protected $casts = [
        'estado' => EstadoTarea::class,
        'fecha_limite' => 'date',
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }
}
