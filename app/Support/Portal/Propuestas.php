<?php

namespace App\Support\Portal;

use App\Enums\EstadoPropuesta;
use App\Models\Cliente;
use App\Models\Propuesta;

/**
 * Propuestas visibles en el portal.
 *
 * LISTA BLANCA de campos por fila: id, folio, título, estado (etiqueta), fecha de emisión y si ya
 * tiene PDF. Nunca precio, horas, tarifa, `visibilidad`, el contenido JSON ni `creado_por`
 * (lo que el cliente debe ver está en el PDF, que respeta los interruptores de visibilidad).
 *
 * Estados expuestos: enviada y aprobada. Un borrador es trabajo interno y una rechazada ya no es
 * relevante para el cliente; ninguna sale.
 */
class Propuestas
{
    public const ESTADOS = [EstadoPropuesta::Enviada, EstadoPropuesta::Aprobada];

    /** @return list<array{id: int, folio: ?string, titulo: string, estado: string, estado_label: string, fecha: ?string, con_pdf: bool}> */
    public static function listar(Cliente $cliente): array
    {
        return self::consulta($cliente)
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->get(['id', 'cliente_id', 'folio', 'titulo', 'estado', 'fecha_emision'])
            ->map(fn (Propuesta $p) => [
                'id' => $p->id,
                'folio' => $p->folio,
                'titulo' => (string) $p->titulo,
                'estado' => $p->estado->value,
                'estado_label' => $p->estado->label(),
                'fecha' => $p->fecha_emision?->format('Y-m-d'),
                // Sin folio no hay PDF emitido todavía: no se genera uno "en borrador" para el cliente.
                'con_pdf' => $p->folio !== null,
            ])
            ->all();
    }

    /** La propuesta pedida SOLO si es del cliente del enlace, está en un estado expuesto y ya tiene folio. */
    public static function paraDescarga(Cliente $cliente, int $id): ?Propuesta
    {
        return self::consulta($cliente)->whereKey($id)->whereNotNull('folio')->first();
    }

    private static function consulta(Cliente $cliente)
    {
        return Propuesta::query()
            ->where('cliente_id', $cliente->id)
            ->whereIn('estado', array_map(fn (EstadoPropuesta $e) => $e->value, self::ESTADOS));
    }
}
