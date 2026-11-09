<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Enlace público de solo lectura a la versión para cliente de un reporte. Solo se
 * guarda el SHA-256 del token: el token en claro existe únicamente en la respuesta
 * que lo crea, así que una fuga de la base no regala accesos.
 */
class ReporteEnlace extends Model
{
    protected $table = 'reporte_enlaces';

    public const DIAS_VIGENCIA = 30;

    protected $fillable = ['reporte_id', 'token_hash', 'expira_en', 'revocado_en', 'ultimo_acceso_en', 'accesos', 'creado_por'];

    protected $casts = [
        'expira_en' => 'datetime',
        'revocado_en' => 'datetime',
        'ultimo_acceso_en' => 'datetime',
        'accesos' => 'integer',
    ];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(Reporte::class)->withTrashed();
    }

    /**
     * Crea el enlace y devuelve también el token en claro (única vez que existe).
     *
     * @return array{enlace: self, token: string, url: string}
     */
    public static function crearPara(Reporte $reporte, ?int $usuarioId = null, int $dias = self::DIAS_VIGENCIA): array
    {
        $token = Str::random(48);

        $enlace = self::create([
            'reporte_id' => $reporte->id,
            'token_hash' => self::hash($token),
            'expira_en' => now()->addDays($dias),
            'creado_por' => $usuarioId,
        ]);

        return ['enlace' => $enlace, 'token' => $token, 'url' => route('reporte-publico.show', ['token' => $token])];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function buscarPorToken(string $token): ?self
    {
        return self::where('token_hash', self::hash($token))->first();
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull('revocado_en')->where('expira_en', '>', now());
    }

    public function estado(): string
    {
        if ($this->revocado_en !== null) {
            return 'revocado';
        }

        return $this->expira_en->isPast() ? 'expirado' : 'vigente';
    }

    /** @return array<string, mixed> Nunca incluye el token ni su hash. */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'estado' => $this->estado(),
            'expira_en' => $this->expira_en?->format('Y-m-d'),
            'ultimo_acceso_en' => $this->ultimo_acceso_en?->format('Y-m-d H:i'),
            'accesos' => $this->accesos,
            'creado' => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
