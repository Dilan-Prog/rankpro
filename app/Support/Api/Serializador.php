<?php

namespace App\Support\Api;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Serializa un modelo para la API sin escribir un API Resource por cada uno
 * de los ~57 modelos del proyecto: usa toRow() cuando el modelo lo define
 * (ya es el contrato que consume el panel) y si no toArray() (respeta
 * $hidden). OCULTOS es un cinturón de seguridad adicional por si algún
 * modelo con datos sensibles no tiene $hidden bien puesto.
 */
class Serializador
{
    /** @var array<class-string, array<int, string>> */
    private const OCULTOS = [
        Cliente::class => ['api_token', 'api_token_regenerated_at'],
        User::class => ['password', 'remember_token'],
    ];

    /**
     * @param  array<int, string>  $incluir  relaciones ya cargadas (eager
     *   loaded por el llamador) a añadir a la salida, serializadas igual.
     */
    public static function modelo(Model $modelo, array $incluir = []): array
    {
        $datos = method_exists($modelo, 'toRow') ? $modelo->toRow() : $modelo->toArray();

        foreach (self::OCULTOS[get_class($modelo)] ?? [] as $campo) {
            unset($datos[$campo]);
        }

        foreach ($datos as $clave => $valor) {
            if ($valor instanceof Carbon) {
                $datos[$clave] = $valor->toIso8601String();
            }
        }

        foreach ($incluir as $relacion) {
            if (! $modelo->relationLoaded($relacion)) {
                continue;
            }
            $valor = $modelo->getRelation($relacion);
            $datos[$relacion] = $valor instanceof \Illuminate\Support\Collection
                ? self::coleccion($valor)
                : ($valor instanceof Model ? self::modelo($valor) : $valor);
        }

        return $datos;
    }

    /**
     * @param  iterable<Model>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function coleccion(iterable $items, array $incluir = []): array
    {
        $out = [];
        foreach ($items as $item) {
            $out[] = self::modelo($item, $incluir);
        }

        return $out;
    }
}
