<?php

namespace App\Http\Controllers\Portal;

use App\Support\Portal\Respuesta;
use Illuminate\Http\Response;

/** Cualquier ruta o método desconocido bajo /portal/{token}: 404 propio, con las cabeceras del portal. */
class NoEncontradoController extends PortalController
{
    public function __invoke(): Response
    {
        return Respuesta::error(404);
    }
}
