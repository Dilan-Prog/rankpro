<?php

namespace App\Support\Reglas;

use App\Enums\TipoServicio;
use App\Models\AdsCampana;
use Illuminate\Validation\Rule;

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
            'cliente_id' => ['required', 'integer', Rule::exists('clientes', 'id')->whereNull('deleted_at')],
            'servicio_id' => ['required', 'integer', new ServicioDelCliente([TipoServicio::GoogleAds->value, TipoServicio::MetaAds->value, TipoServicio::TiktokAds->value], 'Ads')],
            'nombre' => ['required', 'string', 'max:255'],
            'plataforma' => ['required', 'in:google_ads,meta_ads,tiktok_ads'],
            'objetivo' => ['required', 'in:leads,ventas,trafico,branding'],
            // La columna es NOT NULL: se exige un monto. Al crear no se acepta 0 (era el valor prellenado y se guardaba sin querer); al editar sí, por las campañas que ya lo tenían.
            'presupuesto_mensual' => ['required', 'numeric', $campana ? 'min:0' : 'gt:0'],
            'moneda' => ['nullable', 'in:MXN,USD'],
            // Formato libre (123-456-7890, 1234567890): solo letras, números y guiones, sin espacios ni símbolos.
            'plataforma_cuenta_id' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/'],
            'plataforma_campana_id' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/'],
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
