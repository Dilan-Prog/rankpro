<?php

namespace App\Support\Api;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Filtros y paginación comunes a todos los listados de /api/v1: search,
 * filtros exactos por query param, updated_after/created_after, sort y
 * per_page. Un solo lugar para que todos los módulos se comporten igual.
 */
class Consulta
{
    public static function aplicar(Builder|Relation $query, Request $request, ConsultaOpciones $opciones): LengthAwarePaginator
    {
        if ($request->filled('search') && $opciones->buscarEn) {
            $buscar = trim((string) $request->string('search'));
            $query->where(function ($q) use ($buscar, $opciones) {
                foreach ($opciones->buscarEn as $columna) {
                    $q->orWhere($columna, 'like', "%{$buscar}%");
                }
            });
        }

        foreach ($opciones->filtrosExactos as $filtro) {
            if ($request->filled($filtro)) {
                $query->where($filtro, $request->input($filtro));
            }
        }

        if ($request->filled('updated_after')) {
            $query->where('updated_at', '>', $request->date('updated_after'));
        }
        if ($request->filled('created_after')) {
            $query->where('created_at', '>', $request->date('created_after'));
        }

        $sort = (string) $request->string('sort', $opciones->ordenPorDefecto);
        $direccion = 'asc';
        if (str_starts_with($sort, '-')) {
            $direccion = 'desc';
            $sort = substr($sort, 1);
        }
        if (! in_array($sort, $opciones->ordenables, true)) {
            throw ValidationException::withMessages(['sort' => ["El campo de orden '{$sort}' no es válido."]]);
        }
        $query->orderBy($sort, $direccion);

        if ($opciones->incluibles) {
            $pedidos = array_filter(explode(',', (string) $request->string('incluir')));
            $validos = array_values(array_intersect($pedidos, $opciones->incluibles));
            if ($validos) {
                $query->with($validos);
            }
        }

        $porPagina = min(max((int) $request->integer('per_page', 50), 1), (int) config('api.per_page_max', 200));

        return $query->paginate($porPagina)->withQueryString();
    }
}
