<?php

namespace App\Enums;

/** Solo Pendiente/Exportada por ahora (export CSV manual a Google Ads); ampliable a Enviada/Fallida si algún día se integra la API de Google Ads. */
enum EstadoConversion: string
{
    case Pendiente = 'pendiente';
    case Exportada = 'exportada';
}
