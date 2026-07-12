<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProyectoControl extends Model
{
    use HasFactory;

    protected $table = 'proyecto_control';

    public const CHECKLIST = [
        'bugs_resueltos' => 'Todos los bugs resueltos',
        'qa_aprobado' => 'QA aprobado',
        'sitio_produccion' => 'Sitio en producción',
        'credenciales_entregadas' => 'Credenciales entregadas',
        'manual_entregado' => 'Manual entregado',
        'pago_final_recibido' => 'Pago final recibido',
        'cliente_firmo_conformidad' => 'Cliente firmó conformidad',
    ];

    protected $fillable = [
        'proyecto_id',
        'url_produccion',
        'credenciales_entregadas',
        'manual_entregado',
        'capacitacion_realizada',
        'pago_final_recibido',
        'monto_pago_final',
        'fecha_entrega_real',
        'satisfaccion_cliente',
        'notas_cierre',
        'checklist',
        'aprobado',
        'fecha_aprobacion',
    ];

    protected $casts = [
        'credenciales_entregadas' => 'boolean',
        'manual_entregado' => 'boolean',
        'capacitacion_realizada' => 'boolean',
        'pago_final_recibido' => 'boolean',
        'monto_pago_final' => 'decimal:2',
        'fecha_entrega_real' => 'date',
        'satisfaccion_cliente' => 'integer',
        'checklist' => 'array',
        'aprobado' => 'boolean',
        'fecha_aprobacion' => 'datetime',
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }
}
