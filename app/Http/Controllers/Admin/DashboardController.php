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
use App\Support\ResumenAgencia;
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
            'chartRange' => $this->rangoGrafica($revenueData),
            'revenueData' => $revenueData,
            'alerts' => $this->alerts($now),
            'topRoas' => $this->topRoas(),
            'contractsExpiring' => $this->contractsExpiring($now),
        ]);
    }

    /**
     * "Sep — Feb 2027" mentia cuando la ventana de 6 meses cruza de año (Sep era
     * 2026): si los años difieren se imprimen ambos.
     *
     * @param  list<array{periodo: string, month: string}>  $revenueData
     */
    private function rangoGrafica(array $revenueData): string
    {
        $primero = $revenueData[0];
        $ultimo = $revenueData[array_key_last($revenueData)];
        $anioPrimero = substr($primero['periodo'], 0, 4);
        $anioUltimo = substr($ultimo['periodo'], 0, 4);

        return $anioPrimero === $anioUltimo
            ? $primero['month'].' — '.$ultimo['month'].' '.$anioUltimo
            : $primero['month'].' '.$anioPrimero.' — '.$ultimo['month'].' '.$anioUltimo;
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

    /** @see ResumenAgencia::kpis() — compartido con Inicio para que las cifras no diverjan. */
    private function kpis(Carbon $now): array
    {
        return ResumenAgencia::kpis($now);
    }

    /** Last 6 calendar months of ingreso/gasto totals, zero-filled where there's no data. */
    private function revenueData(Carbon $now): array
    {
        return FinanzasMetrics::revenueData($now);
    }

    /** Contract renewals due soon, overdue payments, and ROAS outliers — most urgent first. */
    private function alerts(Carbon $now): array
    {
        return ResumenAgencia::alertas($now);
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
        // Todo se compara por fecha (sin hora): con $now a mitad del dia, diffInDays
        // truncaba y el contrato de mañana salia "en 0 dias".
        $hoy = $now->copy()->startOfDay();

        return Cliente::where('estado', 'activo')
            ->whereNotNull('fecha_renovacion_contrato')
            ->where('fecha_renovacion_contrato', '>=', $hoy)
            ->with('servicios')
            ->orderBy('fecha_renovacion_contrato')
            ->take(5)
            ->get()
            ->map(function (Cliente $c) use ($hoy) {
                $fecha = $c->fecha_renovacion_contrato;
                $mrr = $c->servicios
                    ->filter(fn (Servicio $s) => $s->estado === EstadoClienteServicio::Activo)
                    ->sum('precio_mensual');

                return [
                    'id' => $c->id,
                    'client' => $c->nombre,
                    'end' => $fecha->day . ' ' . self::MESES[$fecha->month - 1] . ' ' . $fecha->year,
                    'days' => (int) $hoy->diffInDays($fecha->copy()->startOfDay()),
                    'mrr' => (float) $mrr,
                ];
            })
            ->all();
    }
}
