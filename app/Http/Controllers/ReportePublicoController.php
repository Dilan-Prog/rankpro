<?php

namespace App\Http\Controllers;

use App\Models\Reporte;
use App\Models\ReporteEnlace;
use App\Services\Reportes\Armador;
use App\Services\Reportes\RenderizadorPdf;
use App\Support\Reportes\NombreEntregable;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Vista pública de solo lectura de la versión para cliente (`/r/{token}`), sin sesión.
 *
 * Seguridad: el token se compara por su SHA-256; un token desconocido da 404 y uno
 * revocado o vencido da 410. Todo lo que se muestra sale de Armador::armarCliente, una
 * lista blanca (título, folio, cliente, periodo y el resumen): no hay forma de que esta
 * página enseñe notas internas, fuentes, campañas ni otras secciones.
 */
class ReportePublicoController extends Controller
{
    public function show(string $token): Response
    {
        [$enlace, $reporte, $datos] = $this->resolver($token);

        $this->registrarAcceso($enlace);

        return response(view('reporte-publico.show', $datos + [
            'pdfUrl' => route('reporte-publico.pdf', ['token' => $token]),
            'expira' => $enlace->expira_en,
        ])->render());
    }

    public function pdf(string $token): Response
    {
        [$enlace, $reporte, $datos] = $this->resolver($token);

        $this->registrarAcceso($enlace);

        $nombre = NombreEntregable::para($reporte->loadMissing('cliente'), 1, 'pdf_cliente');
        // El PDF se arma al momento (no se archiva): refleja el contenido actual del resumen.
        $bytes = app(RenderizadorPdf::class)->generarCliente($reporte);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.Str::ascii(str_replace('-v1', '', $nombre)).'"',
        ]);
    }

    /** @return array{0: ReporteEnlace, 1: Reporte, 2: array<string, mixed>} */
    private function resolver(string $token): array
    {
        // Longitud fija: descarta basura antes de tocar la base.
        abort_unless(strlen($token) === 48 && ctype_alnum($token), 404);

        $enlace = ReporteEnlace::buscarPorToken($token);
        abort_if($enlace === null, 404);

        $reporte = $enlace->reporte;
        abort_if($reporte === null || $reporte->trashed(), 404);

        abort_if($enlace->estado() !== 'vigente', 410, 'Este enlace ya no está disponible. Pide uno nuevo a tu contacto en RankPro.');

        $datos = app(Armador::class)->armarCliente($reporte);
        abort_if($datos['resumen'] === null, 404);

        return [$enlace, $reporte, $datos];
    }

    /** UPDATE atómico: dos visitas simultáneas no pisan el contador. */
    private function registrarAcceso(ReporteEnlace $enlace): void
    {
        ReporteEnlace::whereKey($enlace->id)->update([
            'accesos' => DB::raw('accesos + 1'),
            'ultimo_acceso_en' => now(),
        ]);
    }
}
