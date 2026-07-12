<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoClienteServicio;
use App\Http\Controllers\Controller;
use App\Models\AdsCampana;
use App\Models\Cliente;
use App\Models\Finanza;
use App\Models\SeoCampana;
use App\Models\Servicio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const MESES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    public function index(): View
    {
        $now = now();

        $clientesActivos = Cliente::where('estado', 'activo')->count();
        $clientesTotal = Cliente::count();

        $mrr = (float) Servicio::where('estado', 'activo')->sum('precio_mensual');

        $pendiente = (float) Finanza::where('tipo', 'ingreso')
            ->whereIn('estado', ['pendiente', 'vencido'])
            ->where('mes', $now->month)->where('anio', $now->year)
            ->sum('monto');
        $facturasPendientes = Finanza::where('tipo', 'ingreso')
            ->whereIn('estado', ['pendiente', 'vencido'])
            ->where('mes', $now->month)->where('anio', $now->year)
            ->count();

        $campanasActivas = AdsCampana::where('estado', 'activa')->count()
            + SeoCampana::where('estado', 'activa')->count();

        $kpis = [
            ['label' => 'MRR Activo', 'value' => '$' . $this->compact($mrr), 'sub' => 'MXN · ' . $clientesActivos . ' clientes activos', 'trend' => null, 'icon' => 'fa-arrow-trend-up', 'color' => 'emerald'],
            ['label' => 'Clientes Activos', 'value' => (string) $clientesActivos, 'sub' => 'de ' . $clientesTotal . ' clientes totales', 'trend' => null, 'icon' => 'fa-users', 'color' => 'primary'],
            ['label' => 'Pagos Pendientes', 'value' => '$' . $this->compact($pendiente), 'sub' => $facturasPendientes . ' facturas pendientes', 'trend' => null, 'icon' => 'fa-triangle-exclamation', 'color' => 'amber'],
            ['label' => 'Campañas Activas', 'value' => (string) $campanasActivas, 'sub' => 'SEO · Google · Meta · TikTok', 'trend' => null, 'icon' => 'fa-bullhorn', 'color' => 'teal'],
        ];

        $revenueData = $this->revenueData($now);

        return view('admin.dashboard.index', [
            'pageTitle' => 'Dashboard General',
            'kpis' => $kpis,
            'periodoActual' => $now->clone()->locale('es')->translatedFormat('F Y'),
            'chartRange' => $revenueData[0]['month'] . ' — ' . $revenueData[array_key_last($revenueData)]['month'] . ' ' . $now->year,
            'revenueData' => $revenueData,
            'alerts' => $this->alerts($now),
            'topRoas' => $this->topRoas(),
            'contractsExpiring' => $this->contractsExpiring($now),
        ]);
    }

    private function compact(float $amount): string
    {
        return number_format($amount / 1000, 0) . 'K';
    }

    /** Last 6 calendar months of ingreso/gasto totals, zero-filled where there's no data. */
    private function revenueData(Carbon $now): array
    {
        $periodos = collect(range(5, 0))->map(fn ($i) => $now->copy()->subMonths($i));

        $finanzas = Finanza::whereIn('anio', $periodos->pluck('year')->unique())->get()
            ->groupBy(fn (Finanza $f) => $f->anio . '-' . $f->mes);

        return $periodos->map(function (Carbon $periodo) use ($finanzas) {
            $items = $finanzas->get($periodo->year . '-' . $periodo->month, collect());

            return [
                'month' => self::MESES[$periodo->month - 1],
                'income' => (float) $items->where('tipo', 'ingreso')->sum('monto'),
                'expense' => (float) $items->where('tipo', 'gasto')->sum('monto'),
            ];
        })->values()->all();
    }

    /** Contract renewals due soon, overdue payments, and ROAS outliers — most urgent first. */
    private function alerts(Carbon $now): array
    {
        $alerts = collect();

        Cliente::where('estado', 'activo')
            ->whereNotNull('fecha_renovacion_contrato')
            ->whereBetween('fecha_renovacion_contrato', [$now, $now->copy()->addDays(60)])
            ->orderBy('fecha_renovacion_contrato')
            ->get()
            ->each(function (Cliente $c) use ($now, $alerts) {
                $dias = (int) $now->diffInDays($c->fecha_renovacion_contrato);
                $alerts->push(['icon' => 'fa-triangle-exclamation', 'color' => '#F59E0B', 'msg' => "Contrato de {$c->nombre} vence en {$dias} días"]);
            });

        Finanza::where('estado', 'vencido')->with('cliente')->get()
            ->each(function (Finanza $f) use ($alerts) {
                $monto = number_format((float) $f->monto);
                $alerts->push(['icon' => 'fa-triangle-exclamation', 'color' => '#EF4444', 'msg' => "Pago vencido: " . ($f->cliente?->nombre ?? '—') . " — \${$monto} MXN"]);
            });

        $campanasConRoas = AdsCampana::with('cliente', 'metricas')->where('estado', 'activa')->get()
            ->map(fn (AdsCampana $c) => [
                'label' => ($c->cliente?->nombre ?? '—') . ' ' . $c->nombre,
                'roas' => $c->metricas->count() ? round((float) $c->metricas->avg('roas'), 2) : null,
            ])
            ->filter(fn ($c) => $c['roas'] !== null);

        if ($peor = $campanasConRoas->where('roas', '<', 2)->sortBy('roas')->first()) {
            $alerts->push(['icon' => 'fa-chart-line', 'color' => '#0F9D6E', 'msg' => "ROAS de {$peor['label']} por debajo del objetivo"]);
        }
        if ($mejor = $campanasConRoas->sortByDesc('roas')->first()) {
            $alerts->push(['icon' => 'fa-circle-check', 'color' => '#10B981', 'msg' => "{$mejor['label']} alcanzó ROAS de {$mejor['roas']}x este mes"]);
        }

        return $alerts->take(5)->values()->all();
    }

    private function topRoas(): array
    {
        $platformLabels = ['google_ads' => 'Google Ads', 'meta_ads' => 'Meta Ads', 'tiktok_ads' => 'TikTok Ads'];

        return AdsCampana::with('cliente', 'metricas')->get()
            ->map(fn (AdsCampana $c) => [
                'name' => $c->nombre,
                'client' => $c->cliente?->nombre ?? '—',
                'platform' => $platformLabels[$c->plataforma] ?? $c->plataforma,
                'roas' => $c->metricas->count() ? round((float) $c->metricas->avg('roas'), 2) : 0,
            ])
            ->filter(fn ($c) => $c['roas'] > 0)
            ->sortByDesc('roas')
            ->take(5)
            ->values()
            ->all();
    }

    private function contractsExpiring(Carbon $now): array
    {
        return Cliente::where('estado', 'activo')
            ->whereNotNull('fecha_renovacion_contrato')
            ->where('fecha_renovacion_contrato', '>=', $now->copy()->startOfDay())
            ->with('servicios')
            ->orderBy('fecha_renovacion_contrato')
            ->take(5)
            ->get()
            ->map(function (Cliente $c) use ($now) {
                $fecha = $c->fecha_renovacion_contrato;
                $mrr = $c->servicios
                    ->filter(fn (Servicio $s) => $s->estado === EstadoClienteServicio::Activo)
                    ->sum('precio_mensual');

                return [
                    'client' => $c->nombre,
                    'end' => $fecha->day . ' ' . self::MESES[$fecha->month - 1] . ' ' . $fecha->year,
                    'days' => (int) $now->diffInDays($fecha),
                    'mrr' => (float) $mrr,
                ];
            })
            ->all();
    }
}
