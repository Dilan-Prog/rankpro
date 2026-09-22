<?php

namespace App\Support\Reglas;

use App\Enums\EstadoKeyword;
use Illuminate\Validation\Rule;

/**
 * Reglas de validación de KeywordLista, compartidas entre el controlador web
 * (App\Http\Controllers\Admin\KeywordListasController) y el de la API
 * (App\Http\Controllers\Api\V1\Keywords\KeywordListasApiController).
 */
class KeywordListas
{
    /** @return array<string, array<int, mixed>> */
    public static function guardar(): array
    {
        return [
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'responsable_id' => ['nullable', 'integer', 'exists:users,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'canal' => ['nullable', 'string', 'max:100'],
            'estado' => ['required', Rule::enum(EstadoKeyword::class)],
        ];
    }
}
