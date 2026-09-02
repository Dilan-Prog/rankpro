<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoClienteServicio;
use App\Http\Controllers\Controller;
use App\Models\AdsCampana;
use App\Models\Cliente;
use App\Models\Finanza;
use App\Models\SeoCampana;
use App\Models\Servicio;
use App\Support\FinanzasMetrics;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const MESES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    public function index(): View
    {
        $now = now();
        $revenueData = $this->revenueData($now);

        return view('admin.dashboard.index', [
            'pageTitle' => 'Dashboard General',
            'kpis' => $this->kpis($now),
            'periodoActual' => $now->clone()->locale('es')->translatedFormat('F Y'),
            'chartRange' => $revenueData[0]['month'] . ' — ' . $revenueData[array_key_last($revenueData)]['month'] . ' ' . $now->year,
            'revenueData' => $revenueData,
            'alerts' => $this->alerts($now),
            'topRoas' => $this->topRoas(),
            'contractsExpiring' => $this->contractsExpiring($now),
        ]);
    }

    /** Streams a PDF summary of the same data index() renders — see resources/views/pdf/dashboard-reporte.blade.php. */
    public function exportarReporte(): Response
    {
        $now = now();

        $pdf = Pdf::loadView('pdf.dashboard-reporte', [
            'periodo' => $now->clone()->locale('es')->translatedFormat('F Y'),
            'fechaEmision' => $now->format('d/m/Y'),
            'kpis' => $this->kpis($now),
            'revenueData' => $this->revenueData($now),
            'topRoas' => $this->topRoas(),
            'contractsExpiring' => $this->contractsExpiring($now),
        ])->setPaper('letter');

        return $pdf->download('reporte-ejecutivo-' . $now->format('Y-m') . '.pdf');
    }

    /** @return list<array{label: string, value: string, sub: string, trend: null, icon: string, color: string, href: string}> */
    private function kpis(Carbon $now): array
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
            ['label' => 'MRR Activo', 'value' => '$' . $this->compact($mrr), 'sub' => 'MXN · ' . $clientesActivos . ' clientes activos', 'trend' => null, 'icon' => 'fa-arrow-trend-up', 'color' => 'emerald', 'href' => route('admin.finanzas.index')],
            ['label' => 'Clientes Activos', 'value' => (string) $clientesActivos, 'sub' => 'de ' . $clientesTotal . ' clientes totales', 'trend' => null, 'icon' => 'fa-users', 'color' => 'primary', 'href' => route('admin.clientes.index')],
            ['label' => 'Pagos Pendientes', 'value' => '$' . $this->compact($pendiente), 'sub' => $facturasPendientes . ' facturas pendientes', 'trend' => null, 'icon' => 'fa-triangle-exclamation', 'color' => 'amber', 'href' => route('admin.finanzas.index')],
            ['label' => 'Campañas Activas', 'value' => (string) $campanasActivas, 'sub' => 'SEO · Google · Meta · TikTok', 'trend' => null, 'icon' => 'fa-bullhorn', 'color' => 'teal', 'href' => route('admin.ads.index')],
        ];
    }

    private function compact(float $amount): string
    {
        return number_format($amount / 1000, 0) . 'K';
    }

    /** Last 6 calendar months of ingreso/gasto totals, zero-filled where there's no data. */
    private function revenueData(Carbon $now): array
    {
        return FinanzasMetrics::revenueData($now);
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
            ->map(function (AdsCampana $c) use ($platformLabels) {
                $ultimaMetrica = $c->metricas->sortByDesc(fn ($m) => $m->anio * 100 + $m->mes)->first();

                return [
                    'id' => $c->id,
                    'name' => $c->nombre,
                    'client' => $c->cliente?->nombre ?? '—',
                    'platform' => $platformLabels[$c->plataforma] ?? $c->plataforma,
                    'roas' => $c->metricas->count() ? round((float) $c->metricas->avg('roas'), 2) : 0,
                    'estado' => $c->estado->value,
                    'fase' => $c->fase_actual->value,
                    'presupuesto_mensual' => (float) $c->presupuesto_mensual,
                    'gasto_total' => (float) $c->metricas->sum('inversion_real'),
                    'ultima_metrica' => $ultimaMetrica ? [
                        'impresiones' => (int) $ultimaMetrica->impresiones,
                        'clics' => (int) $ultimaMetrica->clics,
                        'ctr' => (float) $ultimaMetrica->ctr,
                        'cpc' => (float) $ultimaMetrica->cpc,
                        'conversiones' => (int) $ultimaMetrica->conversiones,
                    ] : null,
                ];
            })
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
                    'id' => $c->id,
                    'client' => $c->nombre,
                    'end' => $fecha->day . ' ' . self::MESES[$fecha->month - 1] . ' ' . $fecha->year,
                    'days' => (int) $now->diffInDays($fecha),
                    'mrr' => (float) $mrr,
                ];
            })
            ->all();
    }
}
