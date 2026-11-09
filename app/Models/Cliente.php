<?php

namespace App\Models;

use App\Enums\EstadoClienteServicio;
use App\Enums\FormaPago;
use App\Enums\MetodoPago;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Cliente extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nombre',
        'empresa',
        'giro',
        'sitio_web',
        'ciudad',
        'email',
        'telefono',
        'contacto_nombre',
        'estado',
        'fecha_inicio_contrato',
        'fecha_renovacion_contrato',
        'forma_pago',
        'metodo_pago',
        'notas',
    ];

    // El token del snippet de tracking no debe filtrarse en ninguna serialización
    // (vistas, API); se expone explícitamente solo donde hace falta (regenerarToken,
    // la vista de integraciones vía acceso directo a la propiedad).
    protected $hidden = ['api_token', 'api_token_regenerated_at'];

    protected $casts = [
        'estado' => EstadoClienteServicio::class,
        'forma_pago' => FormaPago::class,
        'metodo_pago' => MetodoPago::class,
        'fecha_inicio_contrato' => 'date',
        'fecha_renovacion_contrato' => 'date',
    ];

    /**
     * `nombre` guarda a la persona de contacto; la identidad del cliente en todo el panel es la
     * empresa. Con `nombre_comercial` una sola regla decide qué se muestra (y cae a `nombre` si
     * la empresa está vacía), en vez de repetir `empresa ?: nombre` en cada vista.
     */
    protected function nombreComercial(): Attribute
    {
        return Attribute::get(function () {
            $empresa = trim((string) $this->empresa);

            return $empresa !== '' ? $empresa : (string) $this->nombre;
        });
    }

    /** Campos que hacen "completo" un perfil; el alta rápida puede dejarlos vacíos y el tablero de captura los persigue. */
    public const CAMPOS_PERFIL = [
        'contacto_nombre' => 'Persona de contacto',
        'email' => 'Correo',
        'telefono' => 'WhatsApp',
        'fecha_renovacion_contrato' => 'Fecha de renovación',
    ];

    /**
     * `nombre` guarda a la persona en la mayoría de las fichas viejas y `contacto_nombre` quedó vacío, así que
     * el contacto cuenta si hay contacto_nombre o si `nombre` es distinto del nombre comercial (misma regla que el CRM).
     */
    public function tieneContacto(): bool
    {
        return filled($this->contacto_nombre)
            || (filled($this->nombre) && $this->nombre !== $this->nombre_comercial);
    }

    /** @return array<string, string> campo => etiqueta de lo que falta para tener el perfil completo */
    public function camposFaltantes(): array
    {
        return array_filter(
            self::CAMPOS_PERFIL,
            fn (string $etiqueta, string $campo) => $campo === 'contacto_nombre' ? ! $this->tieneContacto() : blank($this->{$campo}),
            ARRAY_FILTER_USE_BOTH
        );
    }

    public function perfilCompleto(): bool
    {
        return $this->camposFaltantes() === [];
    }

    /** Misma regla que el accessor, para ORDER BY y SELECT crudos. */
    public const SQL_NOMBRE_COMERCIAL = "COALESCE(NULLIF(TRIM(clientes.empresa), ''), clientes.nombre)";

    /** Ordena por nombre comercial (empresa, o nombre si no hay) sin romper el orden alfabético de la lista. */
    public function scopeOrdenadoPorNombreComercial(Builder $query, string $direccion = 'asc'): Builder
    {
        $direccion = strtolower($direccion) === 'desc' ? 'desc' : 'asc';

        return $query->orderByRaw(self::SQL_NOMBRE_COMERCIAL.' '.$direccion);
    }

    public function servicios(): HasMany
    {
        return $this->hasMany(Servicio::class);
    }

    public function seoCampanas(): HasMany
    {
        return $this->hasMany(SeoCampana::class);
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class);
    }

    public function keywordListas(): HasMany
    {
        return $this->hasMany(KeywordLista::class);
    }

    public function adsCampanas(): HasMany
    {
        return $this->hasMany(AdsCampana::class);
    }

    public function proyectos(): HasMany
    {
        return $this->hasMany(Proyecto::class);
    }

    public function finanzas(): HasMany
    {
        return $this->hasMany(Finanza::class);
    }

    public function archivos(): HasMany
    {
        return $this->hasMany(Archivo::class);
    }

    public function adsClics(): HasMany
    {
        return $this->hasMany(AdsClic::class);
    }

    public function adsConversiones(): HasMany
    {
        return $this->hasMany(AdsConversion::class);
    }

    public function embudoEtapas(): HasMany
    {
        return $this->hasMany(AdsEmbudoEtapa::class)->orderBy('orden');
    }

    public function identidad(): HasOne
    {
        return $this->hasOne(IdentidadMarca::class);
    }

    public function buyerPersonas(): HasMany
    {
        return $this->hasMany(BuyerPersona::class)->orderBy('orden')->orderBy('id');
    }

    /**
     * Cascades a delete (soft or force) to every child relation. Children
     * that also use SoftDeletes (servicios, seoCampanas, adsCampanas,
     * proyectos) are deleted one-by-one via ->each->delete() rather than a
     * bulk relation delete() — a bulk delete is a single UPDATE/DELETE
     * query that skips Eloquent model events entirely, which would break
     * *their* own cascade hooks (e.g. servicio -> seoCampanas -> posiciones).
     * Leaf relations with no children of their own (keywords, finanzas)
     * are fine as a bulk delete. archivos is the one exception: each row
     * owns a real file on the 'local' disk, so it's deleted one-by-one to
     * clean up the underlying file alongside the DB row — a bulk delete()
     * would silently orphan every uploaded file on disk.
     */
    protected static function booted(): void
    {
        static::deleting(function (Cliente $cliente) {
            $cliente->servicios()->get()->each->delete();
            $cliente->seoCampanas()->get()->each->delete();
            $cliente->adsCampanas()->get()->each->delete();
            $cliente->proyectos()->get()->each->delete();
            $cliente->keywordListas()->get()->each->delete();
            $cliente->keywords()->delete();
            $cliente->finanzas()->delete();
            $cliente->archivos()->get()->each(function (Archivo $archivo) {
                if (Storage::disk('local')->exists($archivo->ruta_archivo)) {
                    Storage::disk('local')->delete($archivo->ruta_archivo);
                }
                $archivo->delete();
            });
            $cliente->adsClics()->delete();
            $cliente->adsConversiones()->delete();
            $cliente->embudoEtapas()->delete();
        });

        // El logo de Identidad se conserva mientras el cliente exista en soft delete (puede
        // restaurarse y recuperar su ficha completa); solo se limpia al eliminarlo
        // definitivamente. Va en «forceDeleting» (antes del DELETE) porque la cascada de la
        // BD borra la fila de identidad con el cliente y ya no habría ruta que leer.
        static::forceDeleting(function (Cliente $cliente) {
            $cliente->identidad?->borrarArchivoDelLogo();
        });
    }
}
