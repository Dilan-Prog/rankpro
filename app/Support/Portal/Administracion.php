<?php

namespace App\Support\Portal;

use App\Models\Cliente;
use App\Models\PortalAcceso;
use App\Models\PortalVisita;

/** Datos de la tarjeta «Portal del cliente» del Resumen de cliente (lado panel, nunca expone tokens ni hashes). */
class Administracion
{
    /** @return array{accesos: list<array<string, mixed>>, visitas: list<array<string, mixed>>} */
    public static function datos(Cliente $cliente): array
    {
        $accesos = PortalAcceso::query()->where('cliente_id', $cliente->id)->orderByDesc('id')->limit(20)->get();

        $visitas = PortalVisita::query()
            ->whereIn('portal_acceso_id', PortalAcceso::query()->where('cliente_id', $cliente->id)->select('id'))
            ->with('acceso:id,etiqueta')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (PortalVisita $v) => [
                'fecha' => $v->created_at?->format('Y-m-d H:i'),
                'enlace' => $v->acceso?->etiqueta ?: 'Enlace #'.$v->portal_acceso_id,
                'ruta' => $v->ruta,
            ])
            ->all();

        return ['accesos' => $accesos->map->toRow()->all(), 'visitas' => $visitas];
    }
}
