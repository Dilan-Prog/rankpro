<?php

namespace App\Support\Reglas;

use App\Enums\EstadoClienteServicio;
use App\Enums\TipoCobro;
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
            'cliente_id' => ['required', 'integer', Rule::exists('clientes', 'id')->whereNull('deleted_at')],
            'responsable_id' => ['nullable', 'integer', 'exists:users,id'],
            'tipo' => ['required', Rule::enum(TipoServicio::class)],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'tipo_cobro' => ['sometimes', 'required', Rule::enum(TipoCobro::class)],
            // Un servicio gratuito no lleva precio; para los demás es obligatorio.
            'precio_mensual' => ['required_unless:tipo_cobro,gratuito', 'nullable', 'numeric', 'min:0'],
            'estado' => ['required', Rule::enum(EstadoClienteServicio::class)],
            'fecha_inicio' => ['nullable', 'date', 'required_with:fecha_fin'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
        ];
    }

    /** Un servicio gratuito se guarda siempre con precio 0, aunque el formulario mande otro valor. */
    public static function normalizar(array $datos): array
    {
        if (($datos['tipo_cobro'] ?? null) === TipoCobro::Gratuito->value) {
            $datos['precio_mensual'] = 0;
        }
        $datos['precio_mensual'] = $datos['precio_mensual'] ?? 0;

        return $datos;
    }
}
