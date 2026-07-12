<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProyectoDireccion extends Model
{
    use HasFactory;

    protected $table = 'proyecto_direccion';

    public const CHECKLIST = [
        'frontend_completado' => 'Frontend completado',
        'backend_completado' => 'Backend completado',
        'integraciones_completadas' => 'Integraciones completadas',
        'revision_interna' => 'Revisión interna hecha',
        'cliente_informado' => 'Cliente informado del avance',
        'pago_avance_recibido' => 'Pago de avance recibido',
    ];

    protected $fillable = [
        'proyecto_id',
        'porcentaje_avance',
        'pagos_recibidos_fase',
        'checklist',
        'aprobado',
        'fecha_aprobacion',
    ];

    protected $casts = [
        'porcentaje_avance' => 'integer',
        'pagos_recibidos_fase' => 'decimal:2',
        'checklist' => 'array',
        'aprobado' => 'boolean',
        'fecha_aprobacion' => 'datetime',
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }
}
