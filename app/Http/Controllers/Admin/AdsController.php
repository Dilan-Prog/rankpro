<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FaseAds;
use App\Http\Controllers\Controller;
use App\Models\AdsBriefing;
use App\Models\AdsCampana;
use App\Models\AdsClic;
use App\Models\AdsConversion;
use App\Models\AdsKeywordColumna;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdsController extends Controller
{
    public function index(): View
    {
        $campanas = AdsCampana::with(['cliente', 'metricas'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (AdsCampana $campana) => $this->toRow($campana));

        return view('admin.ads.index', [
            'pageTitle' => 'Módulo Ads',
            'campanas' => $campanas,
            'clientes' => Cliente::with('servicios')->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'servicio_id' => ['required', 'integer', 'exists:servicios,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'plataforma' => ['required', 'in:google_ads,meta_ads,tiktok_ads'],
            'objetivo' => ['required', 'in:leads,ventas,trafico,branding'],
            'presupuesto_mensual' => ['required', 'numeric', 'min:0'],
            'fecha_inicio' => ['nullable', 'date'],
        ]);

        $campana = DB::transaction(function () use ($data) {
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
                'checklist' => collect(array_keys(AdsBriefing::CHECKLIST))->mapWithKeys(fn ($key) => [$key => false])->all(),
            ]);
            $campana->configuraciones()->create(['ciclo' => 1, 'checklist' => []]);
            $campana->lanzamientos()->create(['ciclo' => 1, 'checklist' => []]);
            $campana->reportes()->create(['ciclo' => 1, 'checklist' => []]);

            return $campana;
        });

        return response()->json($this->toRow($campana->fresh(['cliente', 'metricas'])), 201);
    }

    public function show(AdsCampana $campana): View
    {
        $campana->load(
            'cliente',
            'servicio',
            'briefing',
            'configuracion',
            'lanzamiento',
            'reporteActual',
            'reportes',
            'grupos.keywords',
            'grupos.columnasPersonalizadas',
            'creativos',
            'metricas',
            'optimizaciones'
        );

        // Vista rápida (últimas 50): clics ya vinculados a esta campaña + clics sin asignar del mismo cliente, para que quede a la vista lo que falta emparejar manualmente.
        $clics = AdsClic::where('cliente_id', $campana->cliente_id)
            ->where(fn ($q) => $q->whereNull('ads_campana_id')->orWhere('ads_campana_id', $campana->id))
            ->latest('created_at')
            ->limit(50)
            ->get();

        $conversiones = AdsConversion::where('cliente_id', $campana->cliente_id)
            ->with('adsClic')
            ->latest('created_at')
            ->limit(50)
            ->get();

        return view('admin.ads.show', [
            'pageTitle' => $campana->nombre,
            'campana' => $campana,
            'clics' => $clics,
            'conversiones' => $conversiones,
            // Sugerencias de nombres de columnas ya creadas en cualquier otro grupo, para reutilizar nomenclatura al agregar una nueva.
            'columnasSugeridas' => AdsKeywordColumna::query()->distinct()->orderBy('nombre')->pluck('nombre'),
        ]);
    }

    public function update(Request $request, AdsCampana $campana): JsonResponse
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

        return response()->json($this->toRow($campana->fresh(['cliente', 'metricas'])));
    }

    public function destroy(AdsCampana $campana): JsonResponse
    {
        $campana->delete();

        return response()->json(['deleted' => true]);
    }

    /** Shared shape for index()'s server-rendered rows and store()/update()'s AJAX responses. */
    private function toRow(AdsCampana $campana): array
    {
        $inversion = (float) $campana->metricas->sum('inversion_real');
        $roas = $campana->metricas->count() ? round((float) $campana->metricas->avg('roas'), 2) : 0.0;

        return [
            'id' => $campana->id,
            'cliente_id' => $campana->cliente_id,
            'cliente' => $campana->cliente?->nombre ?? '—',
            'cliente_contacto' => $campana->cliente?->contacto_nombre,
            'servicio_id' => $campana->servicio_id,
            'nombre' => $campana->nombre,
            'plataforma' => $campana->plataforma,
            'objetivo' => $campana->objetivo,
            'estado' => $campana->estado->value,
            'fase_actual' => $campana->fase_actual->value,
            'ciclo_actual' => $campana->ciclo_actual,
            'presupuesto_mensual' => (float) $campana->presupuesto_mensual,
            'inversion' => $inversion,
            'impresiones' => (int) $campana->metricas->sum('impresiones'),
            'clics' => (int) $campana->metricas->sum('clics'),
            'conversiones' => (int) $campana->metricas->sum('conversiones'),
            'roas' => $roas,
            'ingreso_atribuido' => round($inversion * $roas, 2),
            'fecha_inicio' => $campana->fecha_inicio?->format('Y-m-d'),
            'fecha_fin' => $campana->fecha_fin?->format('Y-m-d'),
            'notas' => $campana->notas,
            'show_url' => route('admin.ads.show', $campana->id),
        ];
    }
}
