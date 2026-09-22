<?php

namespace App\Support\Api;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/**
 * Forma de respuesta uniforme para /api/v1/*: siempre {data: ...} o
 * {data: [...], meta, links} para listados paginados. Los controladores web
 * siguen respondiendo como hasta ahora (no se tocan); esto es solo para los
 * controladores nuevos en app/Http/Controllers/Api/V1.
 */
class Respuesta
{
    public static function recurso(mixed $datos, int $status = 200, array $meta = []): JsonResponse
    {
        $body = ['data' => $datos];
        if ($meta) {
            $body['meta'] = $meta;
        }

        return response()->json($body, $status);
    }

    public static function coleccion(iterable $items): JsonResponse
    {
        return response()->json(['data' => is_array($items) ? array_values($items) : iterator_to_array($items)]);
    }

    public static function paginada(LengthAwarePaginator $paginador, ?callable $transformar = null): JsonResponse
    {
        $items = $paginador->items();
        if ($transformar) {
            $items = array_map($transformar, $items);
        }

        return response()->json([
            'data' => $items,
            'meta' => [
                'pagina' => $paginador->currentPage(),
                'por_pagina' => $paginador->perPage(),
                'total' => $paginador->total(),
                'ultima_pagina' => $paginador->lastPage(),
            ],
            'links' => [
                'siguiente' => $paginador->nextPageUrl(),
                'anterior' => $paginador->previousPageUrl(),
            ],
        ]);
    }

    public static function eliminado(): JsonResponse
    {
        return response()->json(['data' => ['deleted' => true]]);
    }

    public static function mensaje(string $mensaje, int $status = 200, array $extra = []): JsonResponse
    {
        return response()->json(array_merge(['message' => $mensaje], $extra), $status);
    }
}
