<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\PortalAcceso;
use Illuminate\Http\Request;

/**
 * Base de las pantallas del portal. El cliente sale SIEMPRE del enlace que resolvió el middleware
 * PortalAcceso, nunca de un parámetro de la URL: así ninguna pantalla puede apuntar a otro cliente.
 */
abstract class PortalController extends Controller
{
    protected function acceso(Request $request): PortalAcceso
    {
        return $request->attributes->get('portal_acceso');
    }

    protected function cliente(Request $request): Cliente
    {
        return $this->acceso($request)->cliente;
    }
}
