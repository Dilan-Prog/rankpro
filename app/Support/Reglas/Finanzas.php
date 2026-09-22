<?php

namespace App\Support\Reglas;

/**
 * Reglas de validación de Finanza, compartidas entre el controlador web
 * (Admin\FinanzasController) y la API (Api\V1\Crm\FinanzasApiController).
 */
class Finanzas
{
    public static function guardar(): array
    {
        return [
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'servicio_id' => ['nullable', 'integer', 'exists:servicios,id'],
            'concepto' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'in:ingreso,gasto'],
            'monto' => ['required', 'numeric', 'min:0'],
            'estado' => ['required', 'in:pagado,pendiente,vencido'],
            'fecha_emision' => ['nullable', 'date'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'fecha_pago' => ['nullable', 'date'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
