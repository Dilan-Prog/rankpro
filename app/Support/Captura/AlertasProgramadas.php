<?php

namespace App\Support\Captura;

use App\Enums\EstadoReporte;
use App\Models\Reporte;
use App\Models\ReporteProgramacion;
use App\Services\Reportes\EnviadorReporteProgramado;
use Carbon\Carbon;

/**
 * "Reportes por salir": avisa, con 3 días de anticipación, de lo que le falta a cada envío
 * programado para salir bien. Solo AVISA: el envío no se bloquea (se manda lo que haya).
 *
 * Por cada programación activa cuyo próximo envío cae a 3 días de calendario o menos de hoy
 * (incluidas las que ya vencieron y siguen reintentando porque no había reporte listo):
 *  (a) si falta el reporte del periodo anterior en estado listo o entregado (el mismo criterio
 *      que usa EnviadorReporteProgramado::reporteDelPeriodo para decidir qué enviar), y
 *  (b) qué celdas de captura de ese mes anterior del cliente están en falta o parciales,
 *      reutilizando TableroCaptura para no tener dos definiciones de "qué falta capturar".
 */
class AlertasProgramadas
{
    /** Días de calendario de anticipación del aviso. */
    public const DIAS = 3;

    public function __construct(private ?TableroCaptura $tablero = null)
    {
        $this->tablero ??= new TableroCaptura;
    }

    /**
     * @return array{hoy: string, dias_aviso: int, total: int, con_pendientes: int, alertas: array<int, array<string, mixed>>}
     */
    public function calcular(Carbon $hoy, ?int $clienteId = null): array
    {
        $hoyInicio = $hoy->copy()->startOfDay();

        $programaciones = ReporteProgramacion::query()
            ->with('cliente')
            ->where('activo', true)
            ->whereNotNull('proximo_envio_en')
            ->where('proximo_envio_en', '<=', $hoyInicio->copy()->addDays(self::DIAS)->endOfDay())
            ->when($clienteId, fn ($q) => $q->where('cliente_id', $clienteId))
            ->orderBy('proximo_envio_en')
            ->get();

        $celdasPorClienteYMes = [];
        $alertas = [];

        foreach ($programaciones as $prog) {
            // Un cliente pausado, cancelado o eliminado no recibe reportes: avisar de él sería ruido.
            if (! $prog->clienteActivo()) {
                continue;
            }

            [$inicio, $fin] = EnviadorReporteProgramado::periodoAnterior(Carbon::instance($prog->proximo_envio_en));

            $existente = Reporte::query()
                ->where('cliente_id', $prog->cliente_id)
                ->where('area', $prog->area->value)
                ->whereBetween('periodo_fin', [$inicio->toDateString(), $fin->toDateString()])
                ->orderByRaw("FIELD(estado, 'entregado', 'listo', 'borrador')")
                ->orderByDesc('id')
                ->first();

            $faltaReporte = ! $existente || ! in_array($existente->estado, [EstadoReporte::Listo, EstadoReporte::Entregado], true);

            $clave = $prog->cliente_id.'|'.$inicio->format('Y-m');
            $celdasPorClienteYMes[$clave] ??= $this->celdasFaltantes($inicio, $prog->cliente_id);

            $alertas[] = [
                'programacion_id' => $prog->id,
                'cliente_id' => $prog->cliente_id,
                'cliente' => $prog->cliente->nombre,
                'cliente_nombre_comercial' => $prog->cliente->nombre_comercial,
                'area' => $prog->area->value,
                'area_label' => $prog->area->label(),
                'proximo_envio_en' => $prog->proximo_envio_en->format('Y-m-d H:i'),
                // Negativo = ya venció y sigue pendiente.
                'dias_restantes' => (int) $hoyInicio->diffInDays($prog->proximo_envio_en->copy()->startOfDay(), false),
                'periodo' => $inicio->format('Y-m'),
                'falta_reporte' => $faltaReporte,
                // Si existe pero sigue en borrador, el aviso lo dice y enlaza a él.
                'reporte_id' => $existente?->id,
                'reporte_estado' => $existente?->estado->value,
                'celdas_faltantes' => $celdasPorClienteYMes[$clave],
                'requiere_atencion' => $faltaReporte || $celdasPorClienteYMes[$clave] !== [],
            ];
        }

        return [
            'hoy' => $hoyInicio->toDateString(),
            'dias_aviso' => self::DIAS,
            'total' => count($alertas),
            'con_pendientes' => count(array_filter($alertas, fn ($a) => $a['requiere_atencion'])),
            'alertas' => $alertas,
        ];
    }

    /**
     * Celdas del mes anterior del cliente en falta o parciales.
     *
     * @return array<int, array{fuente: string, etiqueta: string, estado: string, hechas: int, total: int, url: ?string}>
     */
    private function celdasFaltantes(Carbon $mes, int $clienteId): array
    {
        $fila = $this->tablero->calcular($mes, false, $clienteId)['filas'][0] ?? null;
        $salida = [];

        foreach ($fila['celdas'] ?? [] as $fuente => $celda) {
            if (in_array($celda['estado'], [TableroCaptura::FALTA, TableroCaptura::PARCIAL], true)) {
                $salida[] = [
                    'fuente' => $fuente,
                    'etiqueta' => TableroCaptura::FUENTES[$fuente] ?? $fuente,
                    'estado' => $celda['estado'],
                    'hechas' => $celda['hechas'],
                    'total' => $celda['total'],
                    'url' => $celda['url'],
                ];
            }
        }

        return $salida;
    }
}
