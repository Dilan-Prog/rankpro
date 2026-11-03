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
            // Validacion cruzada: un cobro "pagado" sin fecha de pago no entra en los
            // cierres, una fecha de pago en algo "pendiente" es contradictoria y un
            // vencimiento anterior a la emision no tiene sentido.
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha_emision'],
            'fecha_pago' => ['nullable', 'date', 'required_if:estado,pagado', 'prohibited_if:estado,pendiente'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Valida y devuelve los datos con mes/anio DERIVADOS de fecha_emision cuando
     * esta viene: asi los reportes por periodo (que agrupan por mes/anio) no se
     * descuadran de la fecha real. Sin fecha_emision se respetan los enviados.
     *
     * @return array<string, mixed>
     */
    public static function validar(\Illuminate\Http\Request $request): array
    {
        $emision = $request->input('fecha_emision');
        if (is_string($emision) && ($fecha = date_create($emision)) !== false) {
            $request->merge(['mes' => (int) $fecha->format('n'), 'anio' => (int) $fecha->format('Y')]);
        }

        return $request->validate(self::guardar());
    }
}
