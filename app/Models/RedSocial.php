<?php

namespace App\Models;

use App\Support\CatalogoRedes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Enlace a una red social que se pinta en el pie del sitio público. */
class RedSocial extends Model
{
    public const CACHE_CLAVE = 'redes_sociales.activas';

    protected $table = 'redes_sociales';

    protected $fillable = ['red', 'nombre', 'url', 'orden', 'activa'];

    protected $casts = [
        'orden' => 'integer',
        'activa' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_CLAVE));
        static::deleted(fn () => Cache::forget(self::CACHE_CLAVE));
    }

    /**
     * Las que van al pie, en orden. Se lee en cada página pública: va en caché y,
     * si la tabla aún no existe (deploy sin migrar), el sitio sigue sin iconos.
     *
     * @return list<array{red: string, nombre: string, url: string, icon: string, perfil: bool}>
     */
    public static function delPie(): array
    {
        try {
            $filas = Cache::rememberForever(self::CACHE_CLAVE, fn () => static::query()
                ->where('activa', true)->orderBy('orden')->orderBy('id')
                ->get(['red', 'nombre', 'url'])->toArray());
        } catch (\Throwable) {
            return [];
        }

        $catalogo = CatalogoRedes::todas();

        // Una red retirada del catálogo se omite en vez de romper el pie.
        return array_values(array_map(static fn (array $f): array => [
            'red' => $f['red'],
            'nombre' => $f['nombre'] ?: $catalogo[$f['red']]['nombre'],
            'url' => $f['url'],
            'icon' => $catalogo[$f['red']]['icon'],
            'perfil' => $catalogo[$f['red']]['perfil'],
        ], array_filter($filas, static fn (array $f): bool => isset($catalogo[$f['red']]))));
    }
}
