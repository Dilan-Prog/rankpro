<?php

namespace App\Support\Tablero\Bloques;

use App\Enums\EstadoClienteServicio;
use App\Models\Finanza;
use App\Support\Captura\TableroCaptura;
use App\Support\FinanzasMetrics;
use App\Support\Tablero\Contexto;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Salud de la cuenta: contrato, dinero y constancia de captura. Solo reglas duras, sin umbrales inventados:
 * contrato vencido o pagos vencidos = rojo; captura del mes incompleta o tareas vencidas = amarillo.
 */
class Salud extends Bloque
{
    public function clave(): string
    {
        return 'salud';
    }

    public function titulo(): string
    {
        return 'Salud de la cuenta';
    }

    public function icono(): string
    {
        return 'fa-heart-pulse';
    }

    public function permisos(): array
    {
        return ['clientes'];
    }

    public function calcular(Contexto $ctx): array
    {
        $cliente = $ctx->cliente;
        $hoy = $ctx->hoy->copy()->startOfDay();
        $activo = $cliente->estado === EstadoClienteServicio::Activo;
        $fuenteFicha = 'Ficha del cliente';

        $cifras = [];
        $rojos = [];
        $amarillos = [];

        $cifras[] = self::cifra('Estado de la cuenta', $cliente->estado ? ucfirst($cliente->estado->value) : null, null, $fuenteFicha);

        // Renovación: solo un cliente activo con la fecha ya pasada tiene el contrato «vencido».
        $renueva = $cliente->fecha_renovacion_contrato?->copy()->startOfDay();
        $contratoVigente = false;
        if ($renueva === null) {
            $cifras[] = self::cifra('Renovación del contrato', null, null, $fuenteFicha, ['nota' => 'Sin fecha de renovación capturada.']);
        } else {
            $dias = (int) $hoy->diffInDays($renueva, false);
            if ($dias < 0) {
                $texto = 'Vencido hace '.abs($dias).' '.(abs($dias) === 1 ? 'día' : 'días');
                if ($activo) {
                    $rojos[] = 'Contrato vencido';
                }
            } else {
                $contratoVigente = true;
                $texto = $dias === 0 ? 'Vence hoy' : 'Vence en '.$dias.' '.($dias === 1 ? 'día' : 'días');
            }
            $cifras[] = self::cifra('Renovación del contrato', $texto, self::fecha($renueva), $fuenteFicha);
        }

        $enlace = null;
        if ($ctx->puede('finanzas')) {
            $mrr = FinanzasMetrics::mrrPorCliente([$cliente->id])->get($cliente->id, 0.0);
            $cifras[] = self::cifra('MRR', self::dinero($mrr).' / mes', self::fecha($hoy), 'Servicios activos de cobro mensual (Finanzas)');

            $vencidas = $this->pagosVencidos($ctx);
            if ($vencidas['cantidad'] > 0) {
                $rojos[] = $vencidas['cantidad'].' '.($vencidas['cantidad'] === 1 ? 'pago vencido' : 'pagos vencidos');
            }
            $cifras[] = self::cifra(
                'Pagos vencidos',
                $vencidas['cantidad'] === 0 ? 'Ninguno' : $vencidas['cantidad'].' · '.self::dinero($vencidas['monto']),
                self::fecha($vencidas['desde'] ?? $hoy),
                'Finanzas · ingresos vencidos o pendientes con fecha pasada',
                ['nota' => $vencidas['desde'] ? 'La fecha es el vencimiento más antiguo.' : null],
            );
            $enlace = ['url' => route('admin.finanzas.index'), 'etiqueta' => 'Ver Finanzas'];
        }

        if ($ctx->puede('desarrollo')) {
            $tareas = $ctx->tablero()['tareas'];
            if ($tareas['vencidas'] > 0) {
                $amarillos[] = $tareas['vencidas'].' '.($tareas['vencidas'] === 1 ? 'tarea vencida' : 'tareas vencidas');
            }
            $cifras[] = self::cifra(
                'Tareas vencidas',
                $tareas['vencidas'].' de '.$tareas['abiertas'].' abiertas',
                self::fecha($hoy),
                'Desarrollo · tareas de sus proyectos',
            );
        }

        if ($ctx->puede('captura')) {
            $celda = $this->captura($ctx);
            if ($celda['estado'] === 'pendiente') {
                $amarillos[] = 'Captura del mes incompleta';
            }
            $cifras[] = self::cifra('Captura del mes', $celda['texto'], $celda['mes'], 'Pendientes de captura', ['nota' => $celda['nota']]);
        }

        if ($rojos !== []) {
            $estado = self::ROJO;
            $motivo = implode(' · ', array_merge($rojos, $amarillos));
        } elseif ($amarillos !== []) {
            $estado = self::AMARILLO;
            $motivo = implode(' · ', $amarillos);
        } elseif ($activo && $contratoVigente) {
            $estado = self::VERDE;
            $motivo = 'Contrato vigente'.($ctx->puede('finanzas') ? ', sin pagos vencidos' : '').'.';
        } else {
            // Sin fecha de renovación o cuenta no activa no hay evidencia a favor: gris, nunca verde.
            $estado = self::NEUTRO;
            $motivo = $activo ? 'No hay fecha de renovación para evaluar el contrato.' : 'La cuenta no está activa.';
        }

        return $this->bloque($estado, $motivo, $cifras, $enlace);
    }

    /**
     * Ingresos vencidos: marcados `vencido` o `pendiente` con la fecha de vencimiento ya pasada (nadie corre
     * un cron que los marque, así que el estado solo no basta).
     *
     * @return array{cantidad: int, monto: float, desde: ?CarbonInterface}
     */
    private function pagosVencidos(Contexto $ctx): array
    {
        $hoy = $ctx->hoy->toDateString();

        $fila = Finanza::query()
            ->where('cliente_id', $ctx->cliente->id)
            ->where('tipo', 'ingreso')
            ->where(function ($q) use ($hoy) {
                $q->where('estado', 'vencido')
                    ->orWhere(fn ($q2) => $q2->where('estado', 'pendiente')->whereNotNull('fecha_vencimiento')->whereDate('fecha_vencimiento', '<', $hoy));
            })
            ->selectRaw('count(*) as cantidad, coalesce(sum(monto), 0) as monto, min(fecha_vencimiento) as desde')
            ->first();

        return [
            'cantidad' => (int) ($fila->cantidad ?? 0),
            'monto' => (float) ($fila->monto ?? 0),
            'desde' => ! empty($fila->desde) ? Carbon::parse($fila->desde) : null,
        ];
    }

    /**
     * Fila del cliente en «Pendientes de captura» del mes actual (el cálculo es de TableroCaptura, no se duplica).
     *
     * @return array{estado: string, texto: string, mes: ?string, nota: ?string}
     */
    private function captura(Contexto $ctx): array
    {
        $mes = $ctx->hoy->copy()->startOfMonth();
        $etiquetaMes = self::periodo($mes->month, $mes->year);
        $datos = $ctx->recordar('captura', fn () => (new TableroCaptura())->calcular($mes));

        $fila = collect($datos['filas'])->first(fn ($f) => $f['cliente']->id === $ctx->cliente->id);
        if ($fila === null) {
            return ['estado' => 'no_aplica', 'texto' => 'No aplica', 'mes' => $etiquetaMes, 'nota' => 'Solo se pide captura a clientes activos.'];
        }
        if ($fila['sin_fuentes']) {
            return ['estado' => 'no_aplica', 'texto' => 'Sin fuentes por medir', 'mes' => $etiquetaMes, 'nota' => 'Aún no tiene listas, campañas ni métricas que capturar.'];
        }

        // «Captura del mes» son las mediciones mensuales: el perfil del cliente no es mensual y se persigue aparte.
        $mensuales = collect($fila['celdas'])->except('perfil');
        $aplicables = $mensuales->filter(fn ($c) => $c['estado'] !== TableroCaptura::NO_APLICA);
        $pendientes = $mensuales->filter(fn ($c) => in_array($c['estado'], [TableroCaptura::FALTA, TableroCaptura::PARCIAL], true))->count();

        return [
            'estado' => $pendientes > 0 ? 'pendiente' : 'completa',
            'texto' => $pendientes > 0 ? $pendientes.' de '.$aplicables->count().' fuentes pendientes' : 'Completa ('.$aplicables->count().' fuentes)',
            'mes' => $etiquetaMes,
            'nota' => 'Mes en curso.',
        ];
    }
}
