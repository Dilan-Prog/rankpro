<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Api\Catalogo;
use App\Support\Api\Respuesta;

class CatalogoController extends ControladorApi
{
    public function __invoke()
    {
        return Respuesta::recurso(Catalogo::todo());
    }
}
