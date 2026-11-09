<?php

namespace App\Support;

use App\Models\AdsCampana;
use App\Models\Cliente;
use App\Models\Finanza;
use App\Models\SeoCampana;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Cifras y alertas de la agencia que comparten el Dashboard y Inicio. Viven aquí
 * (y no en un controlador) para que las dos pantallas nunca diverjan: Dashboard
 * las llama sin usuario (ve todo) e Inicio con el usuario, que solo recibe lo de
 * los módulos a los que tiene permiso.
 */
class ResumenAgencia
{
    /**
     * MRR, clientes activos y campañas activas llevan trend null a propósito: no se guarda
     * historial (se leen con el estado de hoy) y aproximarlo inventaría la cifra.
     *
     * @return list<array{label: string, value: string, sub: string, trend: float|null, icon: string, color: string, href: string, invertir?: bool, fuente: string, tipo: string}>
     */
    public static function kpis(Carbon $now): array
    {
        $clientesActivos = Cliente::where('estado', 'activo')->count();
        $clientesTotal = Cliente::count();

        $mrr = FinanzasMetrics::mrr();
        $periodo = FinanzasMetrics::periodoActual($now);
        $pendiente = $periodo['pendiente'];
        $facturasPendientes = $periodo['facturas_pendientes'];

        $campanasActivas = AdsCampana::where('estado', 'activa')->count()
            + SeoCampana::where('estado', 'activa')->count();

        return [
            ['label' => 'MRR Activo', 'value' => '$' . self::compacto($mrr), 'sub' => 'MXN · ' . $clientesActivos . ' clientes activos', 'trend' => null, 'icon' => 'fa-arrow-trend-up', 'color' => 'emerald', 'href' => route('admin.finanzas.index'), 'fuente' => 'Servicios (MRR)', 'tipo' => 'calculado'],
            ['label' => 'Clientes Activos', 'value' => (string) $clientesActivos, 'sub' => 'de ' . $clientesTotal . ' clientes totales', 'trend' => null, 'icon' => 'fa-users', 'color' => 'primary', 'href' => route('admin.clientes.index'), 'fuente' => 'Clientes', 'tipo' => 'calculado'],
            ['label' => 'Pagos Pendientes', 'value' => '$' . self::compacto($pendiente), 'sub' => $facturasPendientes . ' facturas pendientes', 'trend' => FinanzasMetrics::tendenciaPendiente($now), 'icon' => 'fa-triangle-exclamation', 'color' => 'amber', 'href' => route('admin.finanzas.index'), 'invertir' => true, 'fuente' => 'Finanzas (facturas)', 'tipo' => 'calculado'],
            ['label' => 'Campañas Activas', 'value' => (string) $campanasActivas, 'sub' => 'SEO · Google · Meta · TikTok', 'trend' => null, 'icon' => 'fa-bullhorn', 'color' => 'teal', 'href' => route('admin.ads.index'), 'fuente' => 'Campañas SEO y Ads', 'tipo' => 'calculado'],
        ];
    }

    /**
     * Renovaciones de contrato cercanas, pagos vencidos y atípicos de ROAS.
     * Cada alerta lleva `href` y `permiso` del módulo donde se resuelve; con `$usuario` se omiten
     * (antes del tope de 5, para que no desplacen a las visibles) las de módulos sin permiso.
     * Las categorías se reparten por turnos (pago vencido, renovación, ROAS) hasta llegar a 5:
     * con un take(5) sobre la lista plana, 5 renovaciones desplazaban a todos los pagos vencidos.
     * Con `$cliente` solo salen las de ese cliente (lo usa el Resumen de cliente).
     *
     * @return list<array{icon: string, color: string, msg: string, href: string, permiso: string}>
     */
    public static function alertas(Carbon $now, ?User $usuario = null, ?Cliente $cliente = null): array
    {
        $hoy = $now->copy()->startOfDay();
        $permitido = fn (string $modulo): bool => $usuario === null || $usuario->tienePermiso($modulo);
        $renovaciones = collect();
        $vencidos = collect();
        $roas = collect();

        if ($permitido('clientes')) {
            // Por fecha (hoy a las 00:00): con la hora actual el contrato que vence
            // HOY quedaba fuera del rango, y diffInDays truncaba "mañana" a 0 días.
            Cliente::where('estado', 'activo')
                ->when($cliente, fn ($q) => $q->whereKey($cliente->id))
                ->whereNotNull('fecha_renovacion_contrato')
                ->whereBetween('fecha_renovacion_contrato', [$hoy, $hoy->copy()->addDays(60)])
                ->orderBy('fecha_renovacion_contrato')
                ->get()
                ->each(function (Cliente $c) use ($hoy, $renovaciones) {
                    $dias = (int) $hoy->diffInDays($c->fecha_renovacion_contrato->copy()->startOfDay());
                    $cuando = match ($dias) {
                        0 => 'vence hoy',
                        1 => 'vence mañana',
                        default => "vence en {$dias} días",
                    };
                    $renovaciones->push(['icon' => 'fa-triangle-exclamation', 'color' => '#F59E0B', 'msg' => "Contrato de {$c->nombre_comercial} {$cuando}", 'href' => route('admin.clientes.show', $c->id), 'permiso' => 'clientes']);
                });
        }

        if ($permitido('finanzas')) {
            // Solo ingresos: un gasto vencido es lo que la agencia debe, no un cliente moroso.
            Finanza::where('tipo', 'ingreso')->where('estado', 'vencido')
                ->when($cliente, fn ($q) => $q->where('cliente_id', $cliente->id))
                ->with('cliente')->get()
                ->each(function (Finanza $f) use ($vencidos) {
                    $monto = number_format((float) $f->monto);
                    $vencidos->push(['icon' => 'fa-triangle-exclamation', 'color' => '#EF4444', 'msg' => "Pago vencido: " . ($f->cliente?->nombre_comercial ?? '—') . " — \${$monto} MXN", 'href' => route('admin.finanzas.index'), 'permiso' => 'finanzas']);
                });
        }

        if ($permitido('ads')) {
            $campanas = AdsCampana::with('cliente', 'metricas')->where('estado', 'activa')
                ->when($cliente, fn ($q) => $q->where('cliente_id', $cliente->id))
                ->get();
            $etiqueta = fn (AdsCampana $c) => ($c->cliente?->nombre_comercial ?? '—') . ' ' . $c->nombre;

            $campanasConRoas = $campanas
                ->map(fn (AdsCampana $c) => [
                    'label' => $etiqueta($c),
                    'roas' => $c->metricas->count() ? round((float) $c->metricas->avg('roas'), 2) : null,
                ])
                ->filter(fn ($c) => $c['roas'] !== null);

            // "este mes" se calcula con las métricas del mes en curso, no con el promedio histórico.
            $campanasMes = $campanas
                ->map(function (AdsCampana $c) use ($etiqueta, $now) {
                    $delMes = $c->metricas->where('mes', $now->month)->where('anio', $now->year);

                    return ['label' => $etiqueta($c), 'roas' => $delMes->count() ? round((float) $delMes->avg('roas'), 2) : null];
                })
                ->filter(fn ($c) => $c['roas'] !== null);

            if ($peor = $campanasConRoas->where('roas', '<', 2)->sortBy('roas')->first()) {
                $roas->push(['icon' => 'fa-chart-line', 'color' => '#0F9D6E', 'msg' => "ROAS de {$peor['label']} por debajo del objetivo", 'href' => route('admin.ads.index'), 'permiso' => 'ads']);
            }
            if ($mejor = $campanasMes->sortByDesc('roas')->first()) {
                $roas->push(['icon' => 'fa-circle-check', 'color' => '#10B981', 'msg' => "{$mejor['label']} alcanzó ROAS de {$mejor['roas']}x este mes", 'href' => route('admin.ads.index'), 'permiso' => 'ads']);
            }
        }

        $grupos = [$vencidos->values(), $renovaciones->values(), $roas->values()];
        $alerts = collect();
        for ($i = 0; $alerts->count() < 5; $i++) {
            $hubo = false;
            foreach ($grupos as $grupo) {
                if ($alerts->count() < 5 && $grupo->has($i)) {
                    $alerts->push($grupo[$i]);
                    $hubo = true;
                }
            }
            if (! $hubo) {
                break;
            }
        }

        return $alerts->values()->all();
    }

    /**
     * Monto abreviado: menos de 10,000 completo (un MRR de 6,265 no debe verse como "$6K" y
     * parecer distinto al de Finanzas); desde 10,000 en miles con un decimal ("$22.3K").
     */
    private static function compacto(float $amount): string
    {
        if (abs($amount) < 10000) {
            return number_format($amount, 0);
        }

        return rtrim(rtrim(number_format($amount / 1000, 1), '0'), '.') . 'K';
    }
}
