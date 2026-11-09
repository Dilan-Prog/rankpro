<?php

namespace App\Support\Conversiones;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Cuántas conversiones de una lista se pueden subir a Google Ads y por qué las demás no.
 * Mismas reglas que AdsConversion::motivoNoExportable() (etapa + gclid/gbraid/wbraid + no
 * duplicada), pero en SQL para no cargar la lista entera solo para contar.
 */
class ResumenExportacion
{
    /** COALESCE: con NULL en las tres columnas un NOT(...) daría NULL y la fila se perdería del conteo. */
    private const SIN_IDENTIFICADOR = "COALESCE(gclid, '') = '' AND COALESCE(gbraid, '') = '' AND COALESCE(wbraid, '') = ''";

    /**
     * @param  Builder|Relation  $activas  conversiones de la lista SIN las duplicadas
     * @return array{total: int, duplicadas: int, sin_identificador: int, sin_etapa: int, exportables: int}
     */
    public static function calcular(Builder|Relation $activas, int $duplicadas): array
    {
        $conIdentificador = fn (Builder|Relation $q) => $q->whereRaw('NOT ('.self::SIN_IDENTIFICADOR.')');

        return [
            'total' => (clone $activas)->count(),
            'duplicadas' => $duplicadas,
            'sin_identificador' => (clone $activas)->whereRaw(self::SIN_IDENTIFICADOR)->count(),
            'sin_etapa' => $conIdentificador(clone $activas)->whereNull('ads_embudo_etapa_id')->count(),
            'exportables' => $conIdentificador(clone $activas)->whereNotNull('ads_embudo_etapa_id')->count(),
        ];
    }
}
