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
        'user_id',
        'cliente_id',
        'titulo',
        'descripcion',
        'responsable',
        'prioridad',
        'estado',
        'fecha_limite',
        'fecha_inicio',
        'orden',
    ];

    protected $casts = [
        'estado' => EstadoTarea::class,
        'fecha_limite' => 'date',
        'fecha_inicio' => 'date',
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    /** Dueño de la tarea personal de «Mi espacio»; las de Desarrollo no lo usan (ahí manda `responsable`). */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
