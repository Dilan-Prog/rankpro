<?php

namespace App\Support\Reglas;

use App\Models\AdsCampana;

/**
 * Reglas de validación de AdsCampana compartidas entre el controlador web
 * (App\Http\Controllers\Admin\AdsController) y el de la API
 * (App\Http\Controllers\Api\V1\Ads\AdsCampanaApiController), para que no
 * diverjan con el tiempo. store() y update() del web difieren solo en que
 * update() además permite estado/fecha_fin/notas — de ahí el parámetro
 * opcional $campana (null = reglas de creación).
 */
class Ads
{
    public static function campana(?AdsCampana $campana = null): array
    {
        $reglas = [
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'servicio_id' => ['required', 'integer', 'exists:servicios,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'plataforma' => ['required', 'in:google_ads,meta_ads,tiktok_ads'],
            'objetivo' => ['required', 'in:leads,ventas,trafico,branding'],
            'presupuesto_mensual' => ['required', 'numeric', 'min:0'],
            'fecha_inicio' => ['nullable', 'date'],
        ];

        if ($campana) {
            $reglas['estado'] = ['required', 'in:activa,pausada,finalizada'];
            $reglas['fecha_fin'] = ['nullable', 'date', 'after_or_equal:fecha_inicio'];
            $reglas['notas'] = ['nullable', 'string', 'max:2000'];
        }

        return $reglas;
    }
}
