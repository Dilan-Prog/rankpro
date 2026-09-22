<?php

namespace App\Http\Controllers\Api\V1\Propuestas;

use App\Enums\TipoArchivo;
use App\Http\Controllers\Admin\PropuestaController;
use App\Models\Archivo;
use App\Models\Propuesta;
use App\Support\Api\Respuesta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Genera el PDF de la Propuesta de Continuidad y devuelve una URL firmada
 * temporal, mismo patrón que Api\V1\Reportes\ReporteGeneracionApiController
 * (y la misma ruta pública de descarga, routes/api_publico_archivos.php).
 *
 * A diferencia de Reportes, Propuestas no tiene un PublicadorReporte
 * equivalente: el armado de folio + render + Archivo vive inline en
 * Admin\PropuestaController::generarPdf(). Aquí se replica esa orquestación
 * (es corta) pero se reusa `datosDocumento()` de ese controlador para los
 * cálculos derivados (variación %, subtotales, textos compuestos), que es lo
 * que de verdad no conviene duplicar.
 */
class PropuestaGeneracionApiController
{
    public function __construct(private PropuestaController $documentador)
    {
    }

    public function pdf(Propuesta $propuesta): JsonResponse
    {
        $propuesta->loadMissing('cliente');

        // Idempotente: folio/fecha_emision se asignan una sola vez (ver
        // Admin\PropuestaController::generarPdf, mismo precedente que Reporte::numero).
        if ($propuesta->folio === null) {
            $numero = 'PROP-CONT-'.now()->format('Y').'-'.str_pad((string) (Propuesta::whereNotNull('folio')->count() + 1), 4, '0', STR_PAD_LEFT);
            $propuesta->update(['folio' => $numero, 'fecha_emision' => now()]);
        }

        $pdf = Pdf::loadView('pdf.propuesta-continuidad', $this->documentador->datosDocumento($propuesta, $propuesta->folio))
            ->setPaper('letter');

        $filename = "{$propuesta->folio}.pdf";
        $path = "clientes/{$propuesta->cliente_id}/propuestas-continuidad/{$filename}";
        Storage::disk('local')->put($path, $pdf->output());

        $archivo = Archivo::create([
            'cliente_id' => $propuesta->cliente_id,
            'nombre' => "Propuesta de Continuidad {$propuesta->folio} — {$propuesta->cliente->nombre}.pdf",
            'tipo' => TipoArchivo::Propuesta->value,
            'ruta_archivo' => $path,
            'tamano' => Storage::disk('local')->size($path),
            'extension' => 'pdf',
            'subido_por' => Auth::id(),
        ]);

        $url = URL::temporarySignedRoute(
            'api.publico.archivos.descargar',
            now()->addMinutes((int) config('api.url_firmada_minutos', 15)),
            ['archivo' => $archivo->id]
        );

        return Respuesta::recurso(['archivo_id' => $archivo->id, 'url' => $url], 201);
    }
}
