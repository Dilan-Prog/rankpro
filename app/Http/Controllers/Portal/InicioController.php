<?php

namespace App\Http\Controllers\Portal;

use App\Support\Portal\Marca;
use App\Support\Portal\Metricas;
use App\Support\Portal\Reportes;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Inicio del portal: reportes recientes, semáforo general y vigencia del enlace. */
class InicioController extends PortalController
{
    public function __invoke(Request $request): View
    {
        $cliente = $this->cliente($request);

        return view('portal.inicio', [
            'marca' => Marca::para($cliente),
            'expira' => $this->acceso($request)->expira_en,
            'reportes' => Reportes::listar($cliente, 3),
            'general' => Metricas::general(Metricas::listar($cliente)),
        ]);
    }
}
