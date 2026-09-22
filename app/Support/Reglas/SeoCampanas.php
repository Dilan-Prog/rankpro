<?php

namespace App\Support\Reglas;

/**
 * Reglas de validación de SeoCampana, compartidas entre el controlador web
 * (App\Http\Controllers\Admin\SeoController) y el de la API
 * (App\Http\Controllers\Api\V1\Seo\SeoCampanasApiController). Extraídas para
 * que ninguno de los dos pueda divergir del otro con el tiempo.
 */
class SeoCampanas
{
    /** @return array<string, array<int, mixed>> */
    public static function crear(): array
    {
        return [
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'servicio_id' => ['required', 'integer', 'exists:servicios,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'url_sitio' => ['nullable', 'string', 'max:255'],
            'fecha_inicio' => ['nullable', 'date'],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public static function actualizar(): array
    {
        return self::crear() + [
            'estado' => ['required', 'in:activa,pausada,finalizada'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
