<?php

namespace App\Http\Controllers\Portal;

use App\Services\Propuestas\GeneradorPdf;
use App\Support\Portal\Marca;
use App\Support\Portal\Propuestas;
use App\Support\Portal\Respuesta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Propuestas enviadas o aprobadas del cliente y su PDF. */
class PropuestasController extends PortalController
{
    public function index(Request $request): View
    {
        $cliente = $this->cliente($request);

        return view('portal.propuestas', [
            'marca' => Marca::para($cliente),
            'expira' => $this->acceso($request)->expira_en,
            'propuestas' => Propuestas::listar($cliente),
        ]);
    }

    public function descargar(Request $request, string $token, string $propuesta): Response
    {
        $modelo = Propuestas::paraDescarga($this->cliente($request), (int) $propuesta);
        if ($modelo === null) {
            Respuesta::abortar(404);
        }

        // Se arma al momento con los mismos datos del PDF del panel (respeta los interruptores de visibilidad)
        // y NO se registra como Archivo: mirar una propuesta no debe dejar rastro en el repositorio del cliente.
        $pdf = Pdf::loadView('pdf.propuesta-continuidad', app(GeneradorPdf::class)->datos($modelo, (string) $modelo->folio))
            ->setPaper('letter')
            ->output();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.preg_replace('/[^A-Za-z0-9_-]/', '', (string) $modelo->folio).'.pdf"',
        ]);
    }
}
