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
     * @return list<array{label: string, value: string, sub: string, trend: float|null, icon: string, color: string, href: string, invertir?: bool}>
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
            ['label' => 'MRR Activo', 'value' => '$' . self::compacto($mrr), 'sub' => 'MXN · ' . $clientesActivos . ' clientes activos', 'trend' => null, 'icon' => 'fa-arrow-trend-up', 'color' => 'emerald', 'href' => route('admin.finanzas.index')],
            ['label' => 'Clientes Activos', 'value' => (string) $clientesActivos, 'sub' => 'de ' . $clientesTotal . ' clientes totales', 'trend' => null, 'icon' => 'fa-users', 'color' => 'primary', 'href' => route('admin.clientes.index')],
            ['label' => 'Pagos Pendientes', 'value' => '$' . self::compacto($pendiente), 'sub' => $facturasPendientes . ' facturas pendientes', 'trend' => FinanzasMetrics::tendenciaPendiente($now), 'icon' => 'fa-triangle-exclamation', 'color' => 'amber', 'href' => route('admin.finanzas.index'), 'invertir' => true],
            ['label' => 'Campañas Activas', 'value' => (string) $campanasActivas, 'sub' => 'SEO · Google · Meta · TikTok', 'trend' => null, 'icon' => 'fa-bullhorn', 'color' => 'teal', 'href' => route('admin.ads.index')],
        ];
    }

    /**
     * Renovaciones de contrato cercanas, pagos vencidos y atípicos de ROAS, lo más urgente primero.
     * Cada alerta lleva `href` y `permiso` del módulo donde se resuelve; con `$usuario` se omiten
     * (antes del tope de 5, para que no desplacen a las visibles) las de módulos sin permiso.
     *
     * @return list<array{icon: string, color: string, msg: string, href: string, permiso: string}>
     */
    public static function alertas(Carbon $now, ?User $usuario = null): array
    {
        $alerts = collect();
        $permitido = fn (string $modulo): bool => $usuario === null || $usuario->tienePermiso($modulo);

        if ($permitido('clientes')) {
            Cliente::where('estado', 'activo')
                ->whereNotNull('fecha_renovacion_contrato')
                ->whereBetween('fecha_renovacion_contrato', [$now, $now->copy()->addDays(60)])
                ->orderBy('fecha_renovacion_contrato')
                ->get()
                ->each(function (Cliente $c) use ($now, $alerts) {
                    $dias = (int) $now->diffInDays($c->fecha_renovacion_contrato);
                    $alerts->push(['icon' => 'fa-triangle-exclamation', 'color' => '#F59E0B', 'msg' => "Contrato de {$c->nombre} vence en {$dias} días", 'href' => route('admin.clientes.show', $c->id), 'permiso' => 'clientes']);
                });
        }

        if ($permitido('finanzas')) {
            Finanza::where('estado', 'vencido')->with('cliente')->get()
                ->each(function (Finanza $f) use ($alerts) {
                    $monto = number_format((float) $f->monto);
                    $alerts->push(['icon' => 'fa-triangle-exclamation', 'color' => '#EF4444', 'msg' => "Pago vencido: " . ($f->cliente?->nombre ?? '—') . " — \${$monto} MXN", 'href' => route('admin.finanzas.index'), 'permiso' => 'finanzas']);
                });
        }

        if ($permitido('ads')) {
            $campanasConRoas = AdsCampana::with('cliente', 'metricas')->where('estado', 'activa')->get()
                ->map(fn (AdsCampana $c) => [
                    'label' => ($c->cliente?->nombre ?? '—') . ' ' . $c->nombre,
                    'roas' => $c->metricas->count() ? round((float) $c->metricas->avg('roas'), 2) : null,
                ])
                ->filter(fn ($c) => $c['roas'] !== null);

            if ($peor = $campanasConRoas->where('roas', '<', 2)->sortBy('roas')->first()) {
                $alerts->push(['icon' => 'fa-chart-line', 'color' => '#0F9D6E', 'msg' => "ROAS de {$peor['label']} por debajo del objetivo", 'href' => route('admin.ads.index'), 'permiso' => 'ads']);
            }
            if ($mejor = $campanasConRoas->sortByDesc('roas')->first()) {
                $alerts->push(['icon' => 'fa-circle-check', 'color' => '#10B981', 'msg' => "{$mejor['label']} alcanzó ROAS de {$mejor['roas']}x este mes", 'href' => route('admin.ads.index'), 'permiso' => 'ads']);
            }
        }

        return $alerts->take(5)->values()->all();
    }

    private static function compacto(float $amount): string
    {
        return number_format($amount / 1000, 0) . 'K';
    }
}
