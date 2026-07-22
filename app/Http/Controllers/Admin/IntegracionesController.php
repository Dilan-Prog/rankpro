<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TipoConversion;
use App\Http\Controllers\Controller;
use App\Models\AdsCampana;
use App\Models\AdsClic;
use App\Models\AdsConversion;
use App\Models\Cliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class IntegracionesController extends Controller
{
    public function index(): View
    {
        $integraciones = [
            ['name' => 'Google Ads API', 'desc' => 'Sincronización automática de campañas', 'icon' => 'fa-google', 'brand' => true],
            ['name' => 'Google Analytics 4', 'desc' => 'Tráfico, conversiones y audiencias', 'icon' => 'fa-chart-line', 'brand' => false],
            ['name' => 'Meta Ads API', 'desc' => 'Facebook e Instagram Ads', 'icon' => 'fa-facebook', 'brand' => true],
            ['name' => 'Google Search Console', 'desc' => 'Posiciones, clics e indexación', 'icon' => 'fa-magnifying-glass', 'brand' => false],
            ['name' => 'Ahrefs', 'desc' => 'Backlinks y análisis de competidores', 'icon' => 'fa-link', 'brand' => false],
            ['name' => 'Semrush', 'desc' => 'Auditoría técnica y keyword research', 'icon' => 'fa-satellite-dish', 'brand' => false],
            ['name' => 'HubSpot CRM', 'desc' => 'Sincronización de contactos y pipeline', 'icon' => 'fa-handshake', 'brand' => false],
            ['name' => 'Slack', 'desc' => 'Notificaciones y alertas en tiempo real', 'icon' => 'fa-slack', 'brand' => true],
        ];

        return view('admin.integraciones.index', [
            'pageTitle' => 'Integraciones',
            'integraciones' => $integraciones,
        ]);
    }

    public function clienteIndex(Cliente $cliente): View
    {
        $ultimoClic = $cliente->adsClics()->latest('created_at')->value('created_at');
        $ultimaConversion = $cliente->adsConversiones()->latest('created_at')->value('created_at');
        $ultimaSenal = collect([$ultimoClic, $ultimaConversion])->filter()->max();

        return view('admin.integraciones.cliente', [
            'pageTitle' => 'Integraciones — '.$cliente->nombre,
            'cliente' => $cliente,
            'ultimaSenal' => $ultimaSenal,
            'scriptUrl' => $cliente->api_token
                ? route('tracking.snippet', ['token' => $cliente->api_token])
                : null,
            'pendientesCount' => $cliente->adsConversiones()->where('estado', 'pendiente')->count(),
        ]);
    }

    public function regenerarToken(Cliente $cliente): RedirectResponse
    {
        $cliente->forceFill([
            'api_token' => 'rp_live_'.Str::random(56),
            'api_token_regenerated_at' => now(),
        ])->save();

        return redirect()->route('admin.clientes.integraciones', $cliente)
            ->with('status', 'Token regenerado. Actualiza el script en el sitio del cliente con el nuevo enlace.');
    }

    public function clics(Request $request, Cliente $cliente): View
    {
        $query = $cliente->adsClics()->with('adsCampana');

        $this->aplicarFiltrosFecha($query, $request);

        if ($request->filled('ads_campana_id')) {
            $request->get('ads_campana_id') === 'sin_asignar'
                ? $query->whereNull('ads_campana_id')
                : $query->where('ads_campana_id', $request->get('ads_campana_id'));
        }

        if ($request->filled('tipo_id')) {
            $query->whereNotNull($request->get('tipo_id'));
        }

        if ($request->filled('utm_campaign')) {
            $query->where('utm_campaign', 'like', '%'.$request->get('utm_campaign').'%');
        }

        return view('admin.integraciones.clics', [
            'pageTitle' => 'Clics — '.$cliente->nombre,
            'cliente' => $cliente,
            'clics' => $query->latest('created_at')->paginate(50)->withQueryString(),
            'campanas' => AdsCampana::where('cliente_id', $cliente->id)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function conversiones(Request $request, Cliente $cliente): View
    {
        $query = $this->conversionesFiltradas($request, $cliente);

        return view('admin.integraciones.conversiones', [
            'pageTitle' => 'Conversiones — '.$cliente->nombre,
            'cliente' => $cliente,
            'conversiones' => $query->with('adsClic.adsCampana', 'etapa')->latest('created_at')->paginate(50)->withQueryString(),
            'campanas' => AdsCampana::where('cliente_id', $cliente->id)->orderBy('nombre')->get(['id', 'nombre']),
            'tiposConversion' => TipoConversion::cases(),
            'etapas' => $cliente->embudoEtapas,
        ]);
    }

    /**
     * Descarga un CSV en el formato que Google Ads espera para carga manual
     * de conversiones (Herramientas > Medición > Conversiones > Cargas).
     * Verificar los nombres exactos de columna contra la plantilla vigente
     * del cliente antes de usarlo en producción — Google la actualiza
     * periódicamente (incluye la migración hacia "Data Manager" iniciada en
     * junio 2026).
     */
    public function exportarCsv(Request $request, Cliente $cliente)
    {
        $conversiones = $this->conversionesFiltradas($request, $cliente)
            ->where('estado', 'pendiente')
            ->orderBy('created_at')
            ->get();

        $filename = 'conversiones-'.Str::slug($cliente->nombre).'-'.now()->format('Y-m-d_His').'.csv';

        $response = response()->streamDownload(function () use ($conversiones) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Google Click ID', 'GBRAID', 'WBRAID', 'Conversion Name', 'Conversion Time', 'Conversion Value', 'Conversion Currency']);

            foreach ($conversiones as $conversion) {
                fputcsv($out, [
                    $conversion->gclid,
                    $conversion->gbraid,
                    $conversion->wbraid,
                    \App\Support\Labels::tipoConversion($conversion->tipo->value),
                    $conversion->created_at->format('Y-m-d H:i:sP'),
                    $conversion->valor,
                    $conversion->moneda,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);

        AdsConversion::whereIn('id', $conversiones->pluck('id'))->update([
            'estado' => 'exportada',
            'exportada_at' => now(),
        ]);

        return $response;
    }

    public function asignarCampana(Request $request, Cliente $cliente, AdsClic $clic): RedirectResponse
    {
        abort_unless($clic->cliente_id === $cliente->id, 404);

        $data = $request->validate([
            'ads_campana_id' => ['nullable', 'exists:ads_campanas,id'],
        ]);

        $clic->update(['ads_campana_id' => $data['ads_campana_id'] ?? null]);

        return back()->with('status', 'Clic asignado correctamente.');
    }

    public function asignarEtapa(Request $request, Cliente $cliente, AdsConversion $conversion): RedirectResponse
    {
        abort_unless($conversion->cliente_id === $cliente->id, 404);

        $data = $request->validate([
            'ads_embudo_etapa_id' => ['nullable', 'exists:ads_embudo_etapas,id'],
        ]);

        $conversion->update(['ads_embudo_etapa_id' => $data['ads_embudo_etapa_id'] ?? null]);

        return back()->with('status', 'Conversión clasificada correctamente.');
    }

    /**
     * Exportación general a Excel (CSV con BOM UTF-8, se abre directo en
     * Excel sin problemas de acentos) — distinta del exportarCsv() de arriba,
     * que es específico para la carga manual de conversiones en Google Ads.
     * Respeta los filtros activos (incluida la etapa del embudo).
     */
    public function exportarExcel(Request $request, Cliente $cliente)
    {
        $conversiones = $this->conversionesFiltradas($request, $cliente)
            ->with('adsClic.adsCampana', 'etapa')
            ->orderBy('created_at')
            ->get();

        $filename = 'embudo-'.Str::slug($cliente->nombre).'-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($conversiones) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8
            fputcsv($out, ['Fecha', 'Tipo', 'Etapa del embudo', 'Campaña', 'Valor', 'Moneda', 'Estado', 'Identificador (gclid/gbraid/wbraid)']);

            foreach ($conversiones as $conversion) {
                fputcsv($out, [
                    $conversion->created_at->format('Y-m-d H:i'),
                    \App\Support\Labels::tipoConversion($conversion->tipo->value),
                    $conversion->etapa->nombre ?? 'Sin clasificar',
                    $conversion->adsClic?->adsCampana?->nombre ?? '—',
                    $conversion->valor,
                    $conversion->moneda,
                    $conversion->estado->value,
                    $conversion->gclid ?? $conversion->gbraid ?? $conversion->wbraid,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function conversionesFiltradas(Request $request, Cliente $cliente)
    {
        $query = $cliente->adsConversiones();

        $this->aplicarFiltrosFecha($query, $request);

        if ($request->filled('tipo')) {
            $query->whereIn('tipo', (array) $request->get('tipo'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->get('estado'));
        }

        if ($request->filled('ads_campana_id')) {
            $campanaId = $request->get('ads_campana_id');
            $query->whereHas('adsClic', fn ($q) => $campanaId === 'sin_asignar'
                ? $q->whereNull('ads_campana_id')
                : $q->where('ads_campana_id', $campanaId));
        }

        if ($request->filled('valor_min')) {
            $query->where('valor', '>=', $request->get('valor_min'));
        }

        if ($request->filled('ads_embudo_etapa_id')) {
            $etapaId = $request->get('ads_embudo_etapa_id');
            $etapaId === 'sin_clasificar'
                ? $query->whereNull('ads_embudo_etapa_id')
                : $query->where('ads_embudo_etapa_id', $etapaId);
        }

        return $query;
    }

    private function aplicarFiltrosFecha($query, Request $request): void
    {
        if ($request->filled('fecha_desde')) {
            $query->whereDate('created_at', '>=', $request->get('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $request->get('fecha_hasta'));
        }
    }
}
