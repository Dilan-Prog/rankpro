<?php

namespace App\Support\Contenido;

use App\Models\ElementoContenido;
use Illuminate\Support\Collection;

/**
 * Reglas del árbol de «Contenido»: quién puede ser padre de quién y qué cuelga
 * de una carpeta. Vive aparte del controlador porque la validación del padre se
 * necesita al crear, al subir un archivo y al mover, y porque es lo que impide
 * ciclos (una carpeta dentro de sí misma o de su descendiente) y fugas entre
 * clientes (un elemento colgado de la carpeta de otro cliente).
 */
class ArbolContenido
{
    /**
     * Devuelve el mensaje de error si `$padreId` no es un destino válido, o null
     * si lo es. `$padreId` null significa «raíz» y siempre es válido.
     *
     * @param  ElementoContenido|null  $elemento  el elemento que se mueve (null al crear)
     */
    public static function errorDePadre(int $clienteId, ?int $padreId, ?ElementoContenido $elemento = null): ?string
    {
        if ($padreId === null) {
            return null;
        }

        $padre = ElementoContenido::query()->find($padreId);

        if (! $padre || $padre->cliente_id !== $clienteId) {
            return 'La carpeta de destino no existe en este cliente.';
        }

        if (! $padre->esCarpeta()) {
            return 'Solo se puede guardar dentro de una carpeta.';
        }

        if ($elemento !== null) {
            if ($padre->id === $elemento->id) {
                return 'Una carpeta no puede guardarse dentro de sí misma.';
            }

            if (in_array($padre->id, self::idsDescendientes($elemento), true)) {
                return 'No se puede mover una carpeta dentro de una de sus subcarpetas.';
            }
        }

        return null;
    }

    /**
     * Ids de todo lo que cuelga (a cualquier profundidad) de `$elemento`.
     * Recorre por niveles con una consulta por nivel: el árbol de un cliente es
     * pequeño y así no depende de CTE recursivas (MySQL 5.7 en hosting compartido).
     *
     * @return list<int>
     */
    public static function idsDescendientes(ElementoContenido $elemento): array
    {
        $encontrados = [];
        $nivel = [$elemento->id];

        while ($nivel) {
            $nivel = ElementoContenido::query()
                ->whereIn('parent_id', $nivel)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->diff($encontrados)
                ->values()
                ->all();

            $encontrados = array_merge($encontrados, $nivel);
        }

        return $encontrados;
    }

    /**
     * Cadena de carpetas desde la raíz hasta `$elemento` (incluido), para las
     * migas de pan. `$porId` es el mapa id => elemento de las carpetas del
     * cliente, cargado una sola vez por el controlador.
     *
     * @param  Collection<int, ElementoContenido>  $porId
     * @return list<ElementoContenido>
     */
    public static function cadena(?ElementoContenido $elemento, Collection $porId): array
    {
        $cadena = [];
        $visitados = [];

        while ($elemento && ! in_array($elemento->id, $visitados, true)) {
            array_unshift($cadena, $elemento);
            $visitados[] = $elemento->id;
            $elemento = $elemento->parent_id ? $porId->get($elemento->parent_id) : null;
        }

        return $cadena;
    }
}
