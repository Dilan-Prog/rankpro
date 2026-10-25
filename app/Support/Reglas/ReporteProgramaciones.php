<?php

namespace App\Support\Reglas;

use App\Enums\AreaReporte;
use Illuminate\Http\Request;

/**
 * Validación de ReporteProgramacion, compartida entre el controlador web
 * (Admin\ReporteProgramacionesController) y la API (Api\V1\Reportes\
 * ReporteProgramacionesApiController).
 */
class ReporteProgramaciones
{
    /**
     * Los destinatarios llegan como texto libre (coma, punto y coma o salto de
     * línea) o como lista; se normalizan a lista antes de validar cada email.
     */
    public static function normalizarDestinatarios(Request $request): void
    {
        $destinatarios = $request->input('destinatarios');
        if (is_string($destinatarios)) {
            $destinatarios = array_values(array_unique(array_filter(array_map(
                fn ($e) => mb_strtolower(trim($e)),
                preg_split('/[\s,;]+/', $destinatarios) ?: []
            ))));
            $request->merge(['destinatarios' => $destinatarios]);
        }
    }

    /** @return array<string, mixed> */
    public static function guardar(): array
    {
        return [
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'area' => ['required', 'in:'.implode(',', array_column(AreaReporte::cases(), 'value'))],
            'plantilla_id' => ['required', 'integer', 'exists:correo_plantillas,id,deleted_at,NULL'],
            'asunto' => ['required', 'string', 'max:255'],
            'destinatarios' => ['required', 'array', 'min:1', 'max:10'],
            'destinatarios.*' => ['required', 'email:rfc', 'max:190'],
            'dia_mes' => ['required', 'integer', 'between:1,28'],
            'hora' => ['required', 'date_format:H:i'],
        ];
    }
}
