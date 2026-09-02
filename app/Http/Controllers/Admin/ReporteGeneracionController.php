<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reporte;
use App\Services\Reportes\Armador;
use App\Services\Reportes\PublicadorReporte;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Vista previa y descarga de los entregables de un reporte. El armado y el
 * render viven en App\Services\Reportes; aquí solo se orquesta.
 */
class ReporteGeneracionController extends Controller
{
    public function __construct(private PublicadorReporte $publicador)
    {
    }

    /**
     * Muestra el documento tal cual saldrá impreso, dentro del mismo shell que
     * usan contrato y propuesta (DocumentosController::previewContrato): la
     * misma vista que consume DomPDF, renderizada a HTML y aislada en un iframe
     * para que su CSS de impresión no choque con el panel.
     */
    public function preview(Reporte $reporte, Armador $armador): View
    {
        $html = view('pdf.reporte', $armador->armar($reporte))->render();

        return view('admin.archivos.documento-preview', [
            'pageTitle' => 'Vista previa — '.$reporte->titulo,
            'documentoHtml' => $html,
            'formAction' => route('admin.reportes.pdf', $reporte),
            'cancelRoute' => route('admin.reportes.show', $reporte),
            // El shell es compartido con los documentos de venta y reenvía
            // `hidden` al endpoint de descarga. Un reporte no necesita mandar
            // nada (ya está todo persistido en sus secciones), pero la vista
            // lee estas tres claves sin comprobarlas, así que van vacías.
            'hidden' => ['cliente_id' => $reporte->cliente_id, 'servicios' => [], 'condiciones' => ''],
        ]);
    }

    public function pdf(Reporte $reporte): StreamedResponse
    {
        return $this->descargar($reporte, 'pdf');
    }

    public function xlsx(Reporte $reporte): StreamedResponse
    {
        return $this->descargar($reporte, 'xlsx');
    }

    private function descargar(Reporte $reporte, string $formato): StreamedResponse
    {
        $archivo = $this->publicador->publicar($reporte, $formato);

        return Storage::disk('local')->download(
            $archivo->ruta_archivo,
            basename($archivo->ruta_archivo)
        );
    }
}
