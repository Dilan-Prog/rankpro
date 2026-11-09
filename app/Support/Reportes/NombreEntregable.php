<?php

namespace App\Support\Reportes;

use App\Models\Archivo;
use App\Models\Reporte;
use App\Models\ReporteEntrega;
use Illuminate\Support\Str;

/**
 * Nombre con el que se DESCARGA un entregable de reporte:
 * `<cliente>_<periodo>_<FOLIO>-v<N>.<ext>`, p. ej. `hotel-fratelli_2026-10_REP-SEO-2026-0001-v2.pdf`.
 *
 * Es solo el nombre de descarga; la ruta de almacenamiento no cambia para no romper
 * entregas ya enviadas. Un solo helper para que el panel, la API y el Archivo visible
 * nunca difieran, y el nombre ya diga de quién y de cuándo es sin abrir el PDF.
 */
class NombreEntregable
{
    public static function para(Reporte $reporte, int $version, string $formato): string
    {
        $cliente = Str::slug((string) $reporte->cliente?->nombre_comercial) ?: 'cliente';
        $partes = [$cliente, self::periodo($reporte), $reporte->numero ?: 'REP-SIN-FOLIO'];

        // La versión para cliente es otro entregable del mismo folio: su nombre lo dice y no choca con el PDF completo.
        $esCliente = $formato === 'pdf_cliente';

        return implode('_', array_filter($partes, fn ($p) => $p !== '')).($esCliente ? '-cliente' : '').'-v'.$version.'.'.($esCliente ? 'pdf' : $formato);
    }

    /** Un solo mes -> `2026-10`; si cruza meses -> `2026-09_a_2026-10`; sin fechas -> vacío. */
    public static function periodo(Reporte $reporte): string
    {
        $inicio = $reporte->periodo_inicio?->format('Y-m');
        $fin = $reporte->periodo_fin?->format('Y-m');

        if ($inicio === null && $fin === null) {
            return '';
        }
        $inicio ??= $fin;
        $fin ??= $inicio;

        return $inicio === $fin ? $inicio : "{$inicio}_a_{$fin}";
    }

    /**
     * Nombre de descarga de un Archivo: el nuevo esquema si es la entrega de un reporte
     * (también las anteriores al cambio, que guardaban otro `nombre`), y el nombre del fichero si no.
     */
    public static function deArchivo(Archivo $archivo): string
    {
        $entrega = ReporteEntrega::where('archivo_id', $archivo->id)->first();
        $reporte = $entrega ? Reporte::withTrashed()->with('cliente')->find($entrega->reporte_id) : null;

        return $entrega && $reporte
            ? self::para($reporte, (int) $entrega->version, $entrega->formato)
            : basename($archivo->ruta_archivo);
    }
}
