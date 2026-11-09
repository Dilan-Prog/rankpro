<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Enlace mágico al portal de solo lectura de UN cliente. Identifica un enlace, no a una persona:
 * por eso no es Authenticatable y no reutiliza `Cliente::api_token` (que identifica al sitio).
 * Solo existe el SHA-256 del token; el claro vive únicamente en la respuesta que lo crea.
 */
class PortalAcceso extends Model
{
    protected $table = 'portal_accesos';

    public const VIGENCIAS = [7, 30, 90];

    public const DIAS_VIGENCIA = 30;

    public const LONGITUD_TOKEN = 48;

    protected $fillable = ['cliente_id', 'etiqueta', 'correo', 'token_hash', 'expira_en', 'revocado_en', 'ultimo_acceso_en', 'accesos', 'creado_por'];

    // Aunque nadie serialice el modelo hoy, el hash nunca debe salir en un JSON por descuido.
    protected $hidden = ['token_hash'];

    protected $casts = [
        'expira_en' => 'datetime',
        'revocado_en' => 'datetime',
        'ultimo_acceso_en' => 'datetime',
        'accesos' => 'integer',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function visitas(): HasMany
    {
        return $this->hasMany(PortalVisita::class);
    }

    /** @return array{acceso: self, token: string, url: string} */
    public static function crearPara(Cliente $cliente, ?int $usuarioId = null, int $dias = self::DIAS_VIGENCIA, ?string $etiqueta = null, ?string $correo = null): array
    {
        $token = Str::random(self::LONGITUD_TOKEN);

        $acceso = self::create([
            'cliente_id' => $cliente->id,
            'etiqueta' => $etiqueta,
            'correo' => $correo,
            'token_hash' => self::hash($token),
            'expira_en' => now()->addDays($dias),
            'creado_por' => $usuarioId,
        ]);

        return ['acceso' => $acceso, 'token' => $token, 'url' => route('portal.inicio', ['token' => $token])];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function buscarPorToken(string $token): ?self
    {
        $hash = self::hash($token);
        $acceso = self::where('token_hash', $hash)->first();

        // La búsqueda ya es por igualdad; hash_equals evita depender de la comparación del motor.
        return $acceso !== null && hash_equals($acceso->token_hash, $hash) ? $acceso : null;
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

        return $this->expira_en->isPast() ? 'vencido' : 'vigente';
    }

    /** UPDATE atómico (dos visitas simultáneas no pisan el contador) + fila de auditoría sin IP en claro ni token. */
    public function registrarVisita(Request $request): void
    {
        self::whereKey($this->id)->update([
            'accesos' => DB::raw('accesos + 1'),
            'ultimo_acceso_en' => now(),
        ]);

        PortalVisita::create([
            'portal_acceso_id' => $this->id,
            'ruta' => PortalVisita::rutaSinToken($request),
            // HMAC con la clave de la app: la IP no se recupera de la base ni se cruza con otras tablas sin la sal.
            'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
            'agente' => $request->userAgent() !== null ? mb_substr($request->userAgent(), 0, 255) : null,
        ]);
    }

    /** @return array<string, mixed> Nunca incluye el token ni su hash. */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'etiqueta' => $this->etiqueta,
            'correo' => $this->correo,
            'estado' => $this->estado(),
            'expira_en' => $this->expira_en?->format('Y-m-d'),
            'ultimo_acceso_en' => $this->ultimo_acceso_en?->format('Y-m-d H:i'),
            'accesos' => $this->accesos,
            'creado' => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
