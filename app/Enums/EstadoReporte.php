<?php

namespace App\Enums;

/**
 * Borrador mientras se captura; pasa a Listo al generar el primer entregable
 * y a Entregado cuando el equipo confirma el envío al cliente.
 */
enum EstadoReporte: string
{
    case Borrador = 'borrador';
    case Listo = 'listo';
    case Entregado = 'entregado';
}
