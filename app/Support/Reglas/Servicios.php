<?php

namespace App\Support\Reglas;

use App\Enums\EstadoClienteServicio;
use App\Enums\TipoServicio;
use Illuminate\Validation\Rule;

/**
 * Reglas de validación de Servicio, compartidas entre el controlador web
 * (Admin\ServiciosController) y la API (Api\V1\Crm\ServiciosApiController).
 */
class Servicios
{
    public static function guardar(): array
    {
        return [
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'responsable_id' => ['nullable', 'integer', 'exists:users,id'],
            'tipo' => ['required', Rule::enum(TipoServicio::class)],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'precio_mensual' => ['required', 'numeric', 'min:0'],
            'estado' => ['required', Rule::enum(EstadoClienteServicio::class)],
            'fecha_inicio' => ['nullable', 'date', 'required_with:fecha_fin'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
        ];
    }
}
