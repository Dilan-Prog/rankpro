<?php

namespace App\Models;

use App\Enums\EstadoPropuesta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Propuesta de Continuidad SEO: documento de venta de 5 páginas que un
 * account manager envía a un cliente cuyo contrato de SEO está por terminar.
 * A diferencia de Reporte (contenido en secciones dinámicas), esta tiene una
 * estructura fija de 5 páginas — una columna JSON por pestaña del editor.
 */
class Propuesta extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'cliente_id',
        'seo_campana_id',
        'folio',
        'titulo',
        'estado',
        'precio_mensual',
        'horas_mensuales',
        'tarifa_hora',
        'fecha_emision',
        'resumen',
        'situacion_actual',
        'contexto_continuidad',
        'plan_detalle',
        'condiciones_proyeccion',
        'creado_por',
    ];

    protected $casts = [
        'estado' => EstadoPropuesta::class,
        'precio_mensual' => 'decimal:2',
        'tarifa_hora' => 'decimal:2',
        'horas_mensuales' => 'integer',
        'fecha_emision' => 'date',
        'resumen' => 'array',
        'situacion_actual' => 'array',
        'contexto_continuidad' => 'array',
        'plan_detalle' => 'array',
        'condiciones_proyeccion' => 'array',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function seoCampana(): BelongsTo
    {
        return $this->belongsTo(SeoCampana::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /**
     * Shape compartido entre el listado inicial y las respuestas AJAX de
     * store/update, para repintar la fila sin recargar.
     *
     * @return array<string, mixed>
     */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'folio' => $this->folio,
            'titulo' => $this->titulo,
            'cliente_id' => $this->cliente_id,
            'cliente' => $this->cliente?->empresa ?: $this->cliente?->nombre,
            'estado' => $this->estado->value,
            'estado_label' => $this->estado->label(),
            'precio_mensual' => $this->precio_mensual !== null ? (float) $this->precio_mensual : null,
            'fecha_emision' => $this->fecha_emision?->format('Y-m-d'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i'),
            'show_url' => route('admin.propuestas.show', $this),
        ];
    }

    /**
     * Filas reales del banco de keywords del cliente vinculado, listas para
     * agregarse a `situacion_actual.tabla_consultas` — a diferencia del
     * mockup (una lista fija de 3 keywords ajenas al cliente), esto trae la
     * posición real ya capturada en el banco de ese cliente. impresiones,
     * clics y oportunidad quedan vacíos: no existen en ningún lado de la app
     * (no hay integración con Search Console), se completan a mano.
     *
     * @return array<int, array{consulta: string, posicion: mixed, impresiones: string, clics: string, oportunidad: string}>
     */
    public function sugerirConsultasDesdeBanco(): array
    {
        return Keyword::where('cliente_id', $this->cliente_id)
            ->orderBy('keyword')
            ->get()
            ->map(fn (Keyword $keyword) => [
                'consulta' => $keyword->keyword,
                'posicion' => $keyword->posicion_actual !== null ? (string) $keyword->posicion_actual : '',
                'impresiones' => '',
                'clics' => '',
                'oportunidad' => '',
            ])
            ->all();
    }
}
