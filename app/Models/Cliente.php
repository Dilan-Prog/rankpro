<?php

namespace App\Models;

use App\Enums\EstadoClienteServicio;
use App\Enums\FormaPago;
use App\Enums\MetodoPago;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Cliente extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nombre',
        'empresa',
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

    protected $casts = [
        'estado' => EstadoClienteServicio::class,
        'forma_pago' => FormaPago::class,
        'metodo_pago' => MetodoPago::class,
        'fecha_inicio_contrato' => 'date',
        'fecha_renovacion_contrato' => 'date',
    ];

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
    }
}
