<?php

namespace App\Support\Conversiones;

use App\Models\AdsCampana;
use App\Models\AdsClic;
use App\Models\AdsConversion;
use App\Models\Cliente;

/**
 * Lógica de registro de clics/conversiones, compartida entre el endpoint
 * público de tracking (App\Http\Controllers\Api\TrackingController, con
 * token de cliente) y el endpoint de la API v1 (Api\V1\Conversiones\*, con
 * auth:sanctum + cliente_id explícito). Extraída de TrackingController tal
 * cual estaba, sin cambiar su comportamiento.
 */
class RegistradorConversion
{
    /** @param  array<string, mixed>  $data  ya validado; debe incluir 'visitor_id', 'utm_campaign' opcional */
    public static function registrarClic(Cliente $cliente, array $data): AdsClic
    {
        $data['ads_campana_id'] = self::matchCampana($cliente, $data['utm_campaign'] ?? null);

        return $cliente->adsClics()->create($data);
    }

    /** @param  array<string, mixed>  $data  ya validado; debe incluir 'visitor_id', 'tipo' y opcionalmente 'gclid'/'gbraid'/'wbraid' */
    public static function registrarConversion(Cliente $cliente, array $data): AdsConversion
    {
        $clic = $cliente->adsClics()->where('visitor_id', $data['visitor_id'])->latest('created_at')->first();

        // Respaldo server-side: si no traía el gclid (localStorage limpiado, o el llamador de la API no lo mandó), se copia del último clic conocido de ese visitor_id.
        foreach (['gclid', 'gbraid', 'wbraid'] as $campo) {
            if (empty($data[$campo]) && $clic) {
                $data[$campo] = $clic->{$campo};
            }
        }

        $data['ads_clic_id'] = $clic?->id;

        return $cliente->adsConversiones()->create($data);
    }

    /**
     * Match best-effort por nombre de campaña — solo si hay coincidencia
     * única, nunca adivina entre varias.
     */
    public static function matchCampana(Cliente $cliente, ?string $utmCampaign): ?int
    {
        if (blank($utmCampaign)) {
            return null;
        }

        $campanas = AdsCampana::where('cliente_id', $cliente->id)
            ->whereRaw('LOWER(nombre) = ?', [strtolower($utmCampaign)])
            ->pluck('id');

        return $campanas->count() === 1 ? $campanas->first() : null;
    }
}
