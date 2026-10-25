<?php

namespace App\Models;

use App\Enums\AreaReporte;
use App\Enums\EstadoClienteServicio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Envío mensual automático del reporte de un cliente. No genera datos: manda el
 * reporte del mes anterior que el equipo ya dejó listo (los reportes se capturan
 * a mano), así nunca llega al cliente una copia con cifras viejas.
 */
class ReporteProgramacion extends Model
{
    use HasFactory;

    protected $table = 'reporte_programaciones';

    protected $fillable = [
        'cliente_id', 'area', 'plantilla_id', 'asunto', 'destinatarios', 'dia_mes', 'hora',
        'activo', 'proximo_envio_en', 'ultimo_envio_en', 'ultimo_intento_en', 'ultimo_ok', 'ultimo_resultado', 'ultima_prueba',
        'ultimo_reporte_id', 'ultimo_correo_envio_id', 'creado_por',
    ];

    protected $casts = [
        'area' => AreaReporte::class,
        'destinatarios' => 'array',
        'dia_mes' => 'integer',
        'activo' => 'boolean',
        'proximo_envio_en' => 'datetime',
        'ultimo_envio_en' => 'datetime',
        'ultimo_intento_en' => 'datetime',
        'ultimo_ok' => 'boolean',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(CorreoPlantilla::class, 'plantilla_id');
    }

    public function ultimoCorreoEnvio(): BelongsTo
    {
        return $this->belongsTo(CorreoEnvio::class, 'ultimo_correo_envio_id');
    }

    /** Activas cuyo momento ya llegó: lo que consume el comando. */
    public function scopeVencidas($query)
    {
        return $query->where('activo', true)
            ->whereNotNull('proximo_envio_en')
            ->where('proximo_envio_en', '<=', now())
            // cliente() usa withTrashed, así que el borrado lógico se excluye a mano.
            ->whereHas('cliente', fn ($q) => $q->where('estado', EstadoClienteServicio::Activo->value)->whereNull('clientes.deleted_at'));
    }

    /** Solo un cliente activo y no eliminado recibe reportes. */
    public function clienteActivo(): bool
    {
        $cliente = $this->cliente;

        return $cliente && ! $cliente->trashed() && $cliente->estado === EstadoClienteServicio::Activo;
    }

    /**
     * Próxima fecha de envío estrictamente posterior a $desde. El día va de 1 a
     * 28, así que existe en todos los meses y no hace falta lógica de fin de mes.
     */
    public static function calcularProximo(int $dia, string $hora, ?Carbon $desde = null): Carbon
    {
        $desde = ($desde ?? now())->copy();
        [$h, $m] = array_map('intval', explode(':', $hora) + [0, 0]);

        $candidato = $desde->copy()->startOfMonth()->day($dia)->setTime($h, $m, 0);

        if ($candidato->lessThanOrEqualTo($desde)) {
            $candidato = $desde->copy()->startOfMonth()->addMonthNoOverflow()->day($dia)->setTime($h, $m, 0);
        }

        return $candidato;
    }

    public function recalcularProximo(?Carbon $desde = null): void
    {
        $this->proximo_envio_en = self::calcularProximo($this->dia_mes, (string) $this->hora, $desde);
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'cliente_id' => $this->cliente_id,
            'cliente' => $this->cliente?->empresa ?: $this->cliente?->nombre,
            'area' => $this->area->value,
            'area_label' => $this->area->label(),
            'plantilla_id' => $this->plantilla_id,
            'asunto' => $this->asunto,
            'destinatarios' => $this->destinatarios ?? [],
            'dia_mes' => $this->dia_mes,
            'hora' => substr((string) $this->hora, 0, 5),
            'activo' => $this->activo,
            'proximo_envio_en' => $this->proximo_envio_en?->format('Y-m-d H:i'),
            'ultimo_envio_en' => $this->ultimo_envio_en?->format('Y-m-d H:i'),
            'ultimo_ok' => $this->ultimo_ok,
            'ultimo_resultado' => $this->ultimo_resultado,
            'ultimo_intento_en' => $this->ultimo_intento_en?->format('Y-m-d H:i'),
            'ultima_prueba' => $this->ultima_prueba,
            'ultimo_envio_url' => $this->ultimo_correo_envio_id ? route('admin.correo.envios.show', $this->ultimo_correo_envio_id) : null,
        ];
    }
}
