<?php

namespace App\Support\Portal;

use App\Enums\EstadoReporte;
use App\Models\Cliente;
use App\Models\Reporte;
use App\Models\ReporteEntrega;
use App\Models\ReporteSeccion;
use App\Support\Reportes\NombreEntregable;
use Illuminate\Support\Facades\Storage;

/**
 * Reportes que el portal enseña y deja descargar.
 *
 * LISTA BLANCA de campos por fila (nada más sale a la vista): id de la entrega, folio, título,
 * área, periodo, formato (etiqueta), versión, fecha y URL de descarga. Nunca `notas_alcance`,
 * `fuentes`, `creado_por`, secciones ni el contenido del reporte.
 *
 * Reglas: solo reportes `listo` o `entregado` (nunca borradores) y no eliminados, del cliente
 * del enlace; solo formatos `pdf_cliente` y `pdf` (el XLSX trae tablas de trabajo, no se expone).
 */
class Reportes
{
    public const FORMATOS = ['pdf_cliente', 'pdf'];

    private const ETIQUETAS = ['pdf_cliente' => 'Resumen para el cliente (PDF)', 'pdf' => 'Reporte completo (PDF)'];

    /**
     * @return list<array{id: int, folio: ?string, titulo: string, area: string, periodo: string, formato: string, formato_label: string, version: int, fecha: ?string, ruta: string}>
     */
    public static function listar(Cliente $cliente, ?int $limite = null): array
    {
        $reportes = Reporte::query()
            ->where('cliente_id', $cliente->id)
            ->whereIn('estado', [EstadoReporte::Listo->value, EstadoReporte::Entregado->value])
            ->with(['entregas' => fn ($q) => $q->whereIn('formato', self::FORMATOS)->with('archivo:id,cliente_id,ruta_archivo')])
            ->get();

        $internas = self::ultimaEdicionInterna($reportes->pluck('id')->all());

        $filas = [];
        foreach ($reportes as $reporte) {
            foreach ($reporte->entregas as $entrega) {
                if (! self::permitida($cliente, $reporte, $entrega, $internas[$reporte->id] ?? null)) {
                    continue;
                }
                $filas[] = [
                    'id' => $entrega->id,
                    'folio' => $reporte->numero,
                    'titulo' => (string) $reporte->titulo,
                    'area' => (string) $reporte->area?->label(),
                    'periodo' => self::periodo($reporte),
                    'formato' => $entrega->formato,
                    'formato_label' => self::ETIQUETAS[$entrega->formato],
                    'version' => (int) $entrega->version,
                    'fecha' => $entrega->created_at?->format('Y-m-d'),
                    'orden' => $entrega->created_at?->getTimestamp() ?? 0,
                ];
            }
        }

        usort($filas, fn ($a, $b) => [$b['orden'], $b['id']] <=> [$a['orden'], $a['id']]);
        $filas = array_map(function (array $fila) {
            unset($fila['orden']);

            return $fila;
        }, $filas);

        return array_slice($filas, 0, $limite);
    }

    /**
     * La entrega pedida, SOLO si pertenece al cliente del enlace y cumple todas las reglas.
     *
     * @return array{ruta: string, nombre: string}|null
     */
    public static function paraDescarga(Cliente $cliente, int $entregaId): ?array
    {
        $entrega = ReporteEntrega::query()
            ->whereKey($entregaId)
            ->whereIn('formato', self::FORMATOS)
            ->whereHas('reporte', fn ($q) => $q->where('cliente_id', $cliente->id)
                ->whereIn('estado', [EstadoReporte::Listo->value, EstadoReporte::Entregado->value]))
            ->with(['reporte.cliente', 'archivo'])
            ->first();

        if ($entrega === null) {
            return null;
        }

        $reporte = $entrega->reporte;
        $internas = self::ultimaEdicionInterna([$reporte->id]);
        if (! self::permitida($cliente, $reporte, $entrega, $internas[$reporte->id] ?? null)
            || ! Storage::disk('local')->exists($entrega->archivo->ruta_archivo)) {
            return null;
        }

        return [
            'ruta' => $entrega->archivo->ruta_archivo,
            'nombre' => NombreEntregable::para($reporte, (int) $entrega->version, $entrega->formato),
        ];
    }

    /**
     * @param  ?\Illuminate\Support\Carbon  $ultimaEdicionInterna  última edición de una sección interna del reporte
     */
    private static function permitida(Cliente $cliente, Reporte $reporte, ReporteEntrega $entrega, $ultimaEdicionInterna): bool
    {
        // Doble comprobación de pertenencia: el reporte Y el archivo deben ser del cliente del enlace.
        if ($reporte->cliente_id !== $cliente->id || $entrega->archivo === null || $entrega->archivo->cliente_id !== $cliente->id) {
            return false;
        }

        // El PDF completo se generó sin las secciones internas que había ENTONCES; si se marcó una como
        // interna después, ese PDF pudo contenerla y no se ofrece. El de cliente solo lleva el resumen.
        if ($entrega->formato === 'pdf' && $ultimaEdicionInterna !== null && $ultimaEdicionInterna->greaterThan($entrega->created_at)) {
            return false;
        }

        return true;
    }

    /** @return array<int, \Illuminate\Support\Carbon> reporte_id => última edición de una sección interna */
    private static function ultimaEdicionInterna(array $reporteIds): array
    {
        if ($reporteIds === []) {
            return [];
        }

        return ReporteSeccion::query()
            ->whereIn('reporte_id', $reporteIds)
            ->where('interno', true)
            ->selectRaw('reporte_id, MAX(updated_at) as ultima')
            ->groupBy('reporte_id')
            ->get()
            ->mapWithKeys(fn ($fila) => [$fila->reporte_id => \Illuminate\Support\Carbon::parse($fila->ultima)])
            ->all();
    }

    private static function periodo(Reporte $reporte): string
    {
        $inicio = $reporte->periodo_inicio;
        $fin = $reporte->periodo_fin;
        if ($inicio === null && $fin === null) {
            return '';
        }
        $inicio ??= $fin;
        $fin ??= $inicio;

        return $inicio->isSameMonth($fin) ? Meses::etiqueta($inicio) : Meses::etiqueta($inicio).' a '.Meses::etiqueta($fin);
    }
}
