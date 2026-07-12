<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdsCampana;
use App\Models\AdsCreativo;
use App\Models\Cliente;
use App\Models\Servicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdsController extends Controller
{
    public function index(Request $request): View
    {
        $plataforma = in_array($request->get('plataforma'), ['google_ads', 'meta_ads', 'tiktok_ads'])
            ? $request->get('plataforma')
            : 'google_ads';

        $campanas = AdsCampana::with(['cliente', 'metricas'])
            ->where('plataforma', $plataforma)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (AdsCampana $campana) {
                $totales = [
                    'inversion' => (float) $campana->metricas->sum('inversion_real'),
                    'impresiones' => (int) $campana->metricas->sum('impresiones'),
                    'clics' => (int) $campana->metricas->sum('clics'),
                    'conversiones' => (int) $campana->metricas->sum('conversiones'),
                ];
                $roasPromedio = $campana->metricas->count() ? round((float) $campana->metricas->avg('roas'), 2) : 0;
                $ctrPromedio = $totales['impresiones'] > 0 ? round(($totales['clics'] / $totales['impresiones']) * 100, 2) : 0;
                $cpcPromedio = $totales['clics'] > 0 ? round($totales['inversion'] / $totales['clics'], 2) : 0;

                return [
                    'id' => $campana->id,
                    'nombre' => $campana->nombre,
                    'cliente' => $campana->cliente?->nombre ?? '—',
                    'objetivo' => $campana->objetivo,
                    'estado' => $campana->estado->value,
                    'presupuesto_mensual' => (float) $campana->presupuesto_mensual,
                    'inversion' => $totales['inversion'],
                    'impresiones' => $totales['impresiones'],
                    'clics' => $totales['clics'],
                    'ctr' => $ctrPromedio,
                    'cpc' => $cpcPromedio,
                    'conversiones' => $totales['conversiones'],
                    'roas' => $roasPromedio,
                ];
            });

        $kpis = [
            'inversion' => $campanas->sum('inversion'),
            'impresiones' => $campanas->sum('impresiones'),
            'clics' => $campanas->sum('clics'),
            'conversiones' => $campanas->sum('conversiones'),
        ];

        return view('admin.ads.index', [
            'pageTitle' => 'Módulo Ads',
            'plataforma' => $plataforma,
            'campanas' => $campanas,
            'kpis' => $kpis,
        ]);
    }

    public function create(): View
    {
        return view('admin.ads.create', [
            'pageTitle' => 'Nueva Campaña',
            'clientes' => Cliente::with('servicios')->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $campana = AdsCampana::create($data);

        return redirect()->route('admin.ads.index', ['plataforma' => $campana->plataforma])
            ->with('status', "Campaña \"{$campana->nombre}\" creada correctamente.");
    }

    public function show(AdsCampana $campana): View
    {
        return view('admin.ads.show', [
            'pageTitle' => $campana->nombre,
            'campana' => $campana->load('cliente', 'creativos', 'metricas'),
        ]);
    }

    public function edit(AdsCampana $campana): View
    {
        return view('admin.ads.edit', [
            'pageTitle' => 'Editar Campaña',
            'campana' => $campana,
            'clientes' => Cliente::with('servicios')->orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, AdsCampana $campana): RedirectResponse
    {
        $data = $this->validated($request);

        $campana->update($data);

        return redirect()->route('admin.ads.index', ['plataforma' => $campana->plataforma])
            ->with('status', "Campaña \"{$campana->nombre}\" actualizada correctamente.");
    }

    public function destroy(AdsCampana $campana): RedirectResponse
    {
        $plataforma = $campana->plataforma;
        $campana->delete();

        return redirect()->route('admin.ads.index', ['plataforma' => $plataforma])->with('status', 'Campaña eliminada.');
    }

    public function storeCreativo(Request $request, AdsCampana $campana): RedirectResponse
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'copy' => ['nullable', 'string', 'max:2000'],
            'tipo' => ['required', 'in:imagen,video,carrusel'],
            'url_imagen' => ['nullable', 'string', 'max:255'],
            'ctr' => ['nullable', 'numeric', 'min:0'],
            'estado' => ['required', 'in:activo,pausado'],
            'ab_testing' => ['nullable', 'boolean'],
        ]);

        $data['ab_testing'] = $request->boolean('ab_testing');
        $campana->creativos()->create($data);

        return redirect()->route('admin.ads.show', $campana)->with('status', 'Creativo agregado.');
    }

    public function destroyCreativo(AdsCreativo $creativo): RedirectResponse
    {
        $campanaId = $creativo->ads_campana_id;
        $creativo->delete();

        return redirect()->route('admin.ads.show', $campanaId)->with('status', 'Creativo eliminado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'servicio_id' => ['required', 'integer', 'exists:servicios,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'plataforma' => ['required', 'in:google_ads,meta_ads,tiktok_ads'],
            'objetivo' => ['required', 'in:leads,ventas,trafico,branding'],
            'presupuesto_mensual' => ['required', 'numeric', 'min:0'],
            'estado' => ['required', 'in:activa,pausada,finalizada'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
