<?php

namespace App\Support\Reglas;

use App\Enums\TipoServicio;
use Illuminate\Validation\Rule;

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
            'cliente_id' => ['required', 'integer', Rule::exists('clientes', 'id')->whereNull('deleted_at')],
            'servicio_id' => ['required', 'integer', new ServicioDelCliente([TipoServicio::Seo->value], 'SEO')],
            'nombre' => ['required', 'string', 'max:255'],
            'url_sitio' => ['nullable', 'url:http,https', 'max:255'], // se pinta como enlace en reportes: solo http(s)
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
