<?php

namespace App\Support\Api;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Filtros y paginación comunes a todos los listados de /api/v1: search,
 * filtros exactos por query param, updated_after/created_after, sort y
 * per_page. Un solo lugar para que todos los módulos se comporten igual.
 */
class Consulta
{
    /** Tope de largo de cualquier texto de consulta, para no armar LIKE/consultas con kilobytes. */
    public const MAX_TEXTO = 255;

    /**
     * Lee un parámetro de consulta que debe ser texto. `?x[]=a` llega como
     * array y rompía con "Array to string conversion" (500); se responde 422
     * igual que un `sort` inválido para que el cliente vea qué corregir.
     */
    public static function texto(Request $request, string $clave, ?string $porDefecto = null): ?string
    {
        $valor = $request->input($clave, $porDefecto);

        if ($valor === null || $valor === '') {
            return $porDefecto;
        }
        if (is_int($valor) || is_float($valor)) {
            $valor = (string) $valor;
        }
        if (! is_string($valor)) {
            throw ValidationException::withMessages([$clave => ["El parámetro '{$clave}' debe ser un texto."]]);
        }
        if (mb_strlen($valor) > self::MAX_TEXTO) {
            throw ValidationException::withMessages([$clave => ["El parámetro '{$clave}' no puede superar ".self::MAX_TEXTO.' caracteres.']]);
        }

        return $valor;
    }

    /** Fecha de consulta (updated_after, etc.): texto parseable o 422, nunca 500. */
    public static function fecha(Request $request, string $clave): ?Carbon
    {
        $texto = self::texto($request, $clave);
        if ($texto === null) {
            return null;
        }
        try {
            return Carbon::parse($texto);
        } catch (\Throwable) {
            throw ValidationException::withMessages([$clave => ["El parámetro '{$clave}' no es una fecha válida."]]);
        }
    }

    /** per_page: no numérico o menor a 1 cae al valor por defecto; el máximo se recorta. */
    public static function porPagina(Request $request): int
    {
        $defecto = (int) config('api.per_page_default', 50);
        $maximo = (int) config('api.per_page_max', 200);
        $valor = $request->input('per_page');

        if (! is_string($valor) && ! is_int($valor)) {
            return $defecto;
        }
        $valor = trim((string) $valor);
        if (! preg_match('/^\d{1,9}$/', $valor) || (int) $valor < 1) {
            return $defecto;
        }

        return min((int) $valor, $maximo);
    }

    public static function aplicar(Builder|Relation $query, Request $request, ConsultaOpciones $opciones): LengthAwarePaginator
    {
        $buscar = self::texto($request, 'search');
        if ($buscar !== null && $opciones->buscarEn) {
            $buscar = trim($buscar);
            $query->where(function ($q) use ($buscar, $opciones) {
                foreach ($opciones->buscarEn as $columna) {
                    $q->orWhere($columna, 'like', "%{$buscar}%");
                }
            });
        }

        foreach ($opciones->filtrosExactos as $filtro) {
            $valor = self::texto($request, $filtro);
            if ($valor !== null) {
                $query->where($filtro, $valor);
            }
        }

        if ($desde = self::fecha($request, 'updated_after')) {
            $query->where('updated_at', '>', $desde);
        }
        if ($desde = self::fecha($request, 'created_after')) {
            $query->where('created_at', '>', $desde);
        }

        $sort = self::texto($request, 'sort', $opciones->ordenPorDefecto);
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
            $validos = self::incluir($request, $opciones->incluibles);
            if ($validos) {
                $query->with($validos);
            }
        }

        return $query->paginate(self::porPagina($request))->withQueryString();
    }

    /**
     * @param  array<int, string>  $incluibles
     * @return array<int, string>
     */
    public static function incluir(Request $request, array $incluibles): array
    {
        $pedidos = array_filter(explode(',', self::texto($request, 'incluir') ?? ''));

        return array_values(array_intersect($pedidos, $incluibles));
    }
}
