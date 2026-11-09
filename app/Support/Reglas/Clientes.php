<?php

namespace App\Support\Reglas;

use App\Enums\EstadoClienteServicio;
use App\Enums\FormaPago;
use App\Enums\MetodoPago;
use App\Models\Cliente;
use App\Support\Plantillas\Giros;
use Illuminate\Validation\Rule;

/**
 * Reglas de validación de Cliente, compartidas entre el controlador web
 * (Admin\ClientesController) y la API (Api\V1\Crm\ClientesApiController) para
 * que no diverjan con el tiempo.
 */
class Clientes
{
    public static function guardar(?Cliente $cliente = null): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'empresa' => ['nullable', 'string', 'max:255'],
            // Aditivos y opcionales: la API v1 los acepta sin que ningún cliente existente tenga que mandarlos.
            'giro' => ['nullable', 'string', Rule::in(Giros::claves())],
            // Misma validación que el asistente de alta: sin ella el editor guardaba cualquier texto como sitio.
            'sitio_web' => ['nullable', 'string', 'max:255', fn ($atributo, $valor, $fallo) => \App\Support\Clientes\Sitio::esValido($valor) ?: $fallo('El sitio web no parece una dirección válida (por ejemplo hotelejemplo.com).')],
            'ciudad' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('clientes', 'email')->ignore($cliente?->id)->where(fn ($q) => $q->whereNull('deleted_at'))],
            'telefono' => ['nullable', 'string', 'max:30'],
            'contacto_nombre' => ['nullable', 'string', 'max:255'],
            'estado' => ['required', Rule::enum(EstadoClienteServicio::class)],
            'fecha_inicio_contrato' => ['nullable', 'date'],
            'fecha_renovacion_contrato' => ['nullable', 'date'],
            'forma_pago' => ['nullable', Rule::enum(FormaPago::class)],
            'metodo_pago' => ['nullable', Rule::enum(MetodoPago::class)],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
