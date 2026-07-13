<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FaseAds;
use App\Http\Controllers\Controller;
use App\Models\AdsBriefing;
use App\Models\AdsCampana;
use App\Models\Cliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

                return [
                    'id' => $campana->id,
                    'nombre' => $campana->nombre,
                    'cliente' => $campana->cliente?->nombre ?? '—',
                    'objetivo' => $campana->objetivo,
                    'estado' => $campana->estado->value,
                    'fase_actual' => $campana->fase_actual->value,
                    'ciclo_actual' => $campana->ciclo_actual,
                    'presupuesto_mensual' => (float) $campana->presupuesto_mensual,
                    'inversion' => $totales['inversion'],
                    'impresiones' => $totales['impresiones'],
                    'clics' => $totales['clics'],
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
            'checklistBriefing' => AdsBriefing::CHECKLIST,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'servicio_id' => ['required', 'integer', 'exists:servicios,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'plataforma' => ['required', 'in:google_ads,meta_ads,tiktok_ads'],
            'objetivo' => ['required', 'in:leads,ventas,trafico,branding'],
            'presupuesto_mensual' => ['required', 'numeric', 'min:0'],
            'fecha_inicio' => ['nullable', 'date'],

            'publico_objetivo' => ['nullable', 'string', 'max:5000'],
            'rango_edad' => ['nullable', 'string', 'max:100'],
            'genero' => ['nullable', 'string', 'max:100'],
            'ubicacion_geografica' => ['nullable', 'string', 'max:255'],
            'intereses' => ['nullable', 'string', 'max:5000'],
            'propuesta_valor' => ['nullable', 'string', 'max:5000'],
            'analisis_competencia' => ['nullable', 'string', 'max:5000'],
            'producto_servicio' => ['nullable', 'string', 'max:255'],
            'url_destino' => ['nullable', 'string', 'max:255'],
            'fecha_inicio_estimada' => ['nullable', 'date'],
            'notas' => ['nullable', 'string', 'max:2000'],

            'checklist' => ['nullable', 'array'],
            'checklist.*' => ['boolean'],
        ]);

        $checklistKeys = array_keys(AdsBriefing::CHECKLIST);
        $checklist = collect($checklistKeys)->mapWithKeys(fn ($key) => [$key => (bool) ($data['checklist'][$key] ?? false)])->all();

        $campana = DB::transaction(function () use ($data, $checklist) {
            $campana = AdsCampana::create([
                'cliente_id' => $data['cliente_id'],
                'servicio_id' => $data['servicio_id'],
                'nombre' => $data['nombre'],
                'plataforma' => $data['plataforma'],
                'objetivo' => $data['objetivo'],
                'presupuesto_mensual' => $data['presupuesto_mensual'],
                'estado' => 'activa',
                'fase_actual' => FaseAds::Briefing->value,
                'ciclo_actual' => 1,
                'fecha_inicio' => $data['fecha_inicio'] ?? null,
            ]);

            $campana->briefings()->create([
                'ciclo' => 1,
                'publico_objetivo' => $data['publico_objetivo'] ?? null,
                'rango_edad' => $data['rango_edad'] ?? null,
                'genero' => $data['genero'] ?? null,
                'ubicacion_geografica' => $data['ubicacion_geografica'] ?? null,
                'intereses' => $data['intereses'] ?? null,
                'propuesta_valor' => $data['propuesta_valor'] ?? null,
                'analisis_competencia' => $data['analisis_competencia'] ?? null,
                'producto_servicio' => $data['producto_servicio'] ?? null,
                'url_destino' => $data['url_destino'] ?? null,
                'fecha_inicio_estimada' => $data['fecha_inicio_estimada'] ?? null,
                'notas' => $data['notas'] ?? null,
                'checklist' => $checklist,
            ]);

            $campana->configuraciones()->create(['ciclo' => 1, 'checklist' => []]);
            $campana->lanzamientos()->create(['ciclo' => 1, 'checklist' => []]);
            $campana->reportes()->create(['ciclo' => 1, 'checklist' => []]);

            return $campana;
        });

        return redirect()->route('admin.ads.show', $campana)->with('status', "Campaña \"{$campana->nombre}\" creada. Comienza en fase de Briefing.");
    }

    public function show(AdsCampana $campana): View
    {
        return view('admin.ads.show', [
            'pageTitle' => $campana->nombre,
            'campana' => $campana->load(
                'cliente',
                'servicio',
                'briefing',
                'configuracion',
                'lanzamiento',
                'reporteActual',
                'reportes',
                'grupos',
                'creativos',
                'metricas',
                'optimizaciones'
            ),
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
        $data = $request->validate([
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

        $campana->update($data);

        return redirect()->route('admin.ads.show', $campana)->with('status', "Campaña \"{$campana->nombre}\" actualizada correctamente.");
    }

    public function destroy(AdsCampana $campana): RedirectResponse
    {
        $plataforma = $campana->plataforma;
        $campana->delete();

        return redirect()->route('admin.ads.index', ['plataforma' => $plataforma])->with('status', 'Campaña eliminada.');
    }
}
