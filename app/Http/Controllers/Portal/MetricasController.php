<?php

namespace App\Http\Controllers\Portal;

use App\Support\Portal\Marca;
use App\Support\Portal\Metricas;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Métricas activas del cliente con meta y semáforo. */
class MetricasController extends PortalController
{
    public function __invoke(Request $request): View
    {
        $cliente = $this->cliente($request);
        $metricas = Metricas::listar($cliente);

        return view('portal.metricas', [
            'marca' => Marca::para($cliente),
            'expira' => $this->acceso($request)->expira_en,
            'metricas' => $metricas,
            'general' => Metricas::general($metricas),
        ]);
    }
}
