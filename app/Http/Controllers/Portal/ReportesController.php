<?php

namespace App\Http\Controllers\Portal;

use App\Support\Portal\Marca;
use App\Support\Portal\Reportes;
use App\Support\Portal\Respuesta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Reportes entregados al cliente y su descarga (solo PDF, solo de este cliente). */
class ReportesController extends PortalController
{
    public function index(Request $request): View
    {
        $cliente = $this->cliente($request);

        return view('portal.reportes', [
            'marca' => Marca::para($cliente),
            'expira' => $this->acceso($request)->expira_en,
            'reportes' => Reportes::listar($cliente),
        ]);
    }

    public function descargar(Request $request, string $token, string $entrega): StreamedResponse
    {
        $datos = Reportes::paraDescarga($this->cliente($request), (int) $entrega);
        if ($datos === null) {
            Respuesta::abortar(404);
        }

        return Storage::disk('local')->download($datos['ruta'], $datos['nombre']);
    }
}
