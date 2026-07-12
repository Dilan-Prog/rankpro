<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FaseSeo;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\SeoCampana;
use App\Models\Servicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SeoController extends Controller
{
    public function index(): View
    {
        $campanas = SeoCampana::with('cliente')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (SeoCampana $c) => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'cliente' => $c->cliente?->nombre ?? '—',
                'url_sitio' => $c->url_sitio,
                'estado' => $c->estado->value,
                'fase_actual' => $c->fase_actual->value,
                'ciclo_actual' => $c->ciclo_actual,
                'seo_score' => $c->seo_score,
                'trafico_organico_mensual' => $c->trafico_organico_mensual,
            ]);

        return view('admin.seo.index', [
            'pageTitle' => 'Módulo SEO',
            'campanas' => $campanas,
            'enProceso' => $campanas->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.seo.campana-create', [
            'pageTitle' => 'Nueva Campaña SEO',
            'clientes' => Cliente::with('servicios')->orderBy('nombre')->get(),
            'checklistAuditoria' => \App\Models\SeoFaseAuditoria::CHECKLIST,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'servicio_id' => ['required', 'integer', 'exists:servicios,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'url_sitio' => ['nullable', 'string', 'max:255'],
            'fecha_inicio' => ['nullable', 'date'],
            'seo_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'trafico_organico_mensual' => ['nullable', 'integer', 'min:0'],
            'backlinks_total' => ['nullable', 'integer', 'min:0'],
            'errores_tecnicos' => ['nullable', 'integer', 'min:0'],
            'velocidad_mobile' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'velocidad_desktop' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sitemap_ok' => ['nullable', 'boolean'],
            'robots_ok' => ['nullable', 'boolean'],
            'checklist' => ['nullable', 'array'],
            'checklist.*' => ['boolean'],
        ]);

        $checklistKeys = array_keys(\App\Models\SeoFaseAuditoria::CHECKLIST);
        $checklist = collect($checklistKeys)->mapWithKeys(fn ($key) => [$key => (bool) ($data['checklist'][$key] ?? false)])->all();

        $campana = DB::transaction(function () use ($data, $checklist, $request) {
            $campana = SeoCampana::create([
                'cliente_id' => $data['cliente_id'],
                'servicio_id' => $data['servicio_id'],
                'nombre' => $data['nombre'],
                'url_sitio' => $data['url_sitio'] ?? null,
                'estado' => 'activa',
                'fase_actual' => FaseSeo::Auditoria->value,
                'ciclo_actual' => 1,
                'fecha_inicio' => $data['fecha_inicio'] ?? null,
                'seo_score' => $data['seo_score'] ?? null,
                'trafico_organico_mensual' => $data['trafico_organico_mensual'] ?? null,
                'backlinks_total' => $data['backlinks_total'] ?? null,
                'errores_tecnicos' => $data['errores_tecnicos'] ?? null,
                'velocidad_mobile' => $data['velocidad_mobile'] ?? null,
                'velocidad_desktop' => $data['velocidad_desktop'] ?? null,
                'sitemap_ok' => $request->boolean('sitemap_ok'),
                'robots_ok' => $request->boolean('robots_ok'),
            ]);

            $campana->faseAuditoria()->create(['checklist' => $checklist]);
            $campana->faseEstrategia()->create(['checklist' => []]);
            $campana->faseEjecucion()->create(['checklist' => []]);
            $campana->reportes()->create(['ciclo' => 1, 'checklist' => []]);

            return $campana;
        });

        return redirect()->route('admin.seo.show', $campana)->with('status', "Campaña \"{$campana->nombre}\" creada. Comienza en fase de Auditoría.");
    }

    public function show(SeoCampana $campana): View
    {
        return view('admin.seo.show', [
            'pageTitle' => $campana->nombre,
            'campana' => $campana->load(
                'cliente',
                'servicio',
                'faseAuditoria',
                'faseEstrategia',
                'faseEjecucion',
                'reporteActual',
                'reportes',
                'posiciones',
                'backlinks',
                'keywords'
            ),
        ]);
    }

    public function edit(SeoCampana $campana): View
    {
        return view('admin.seo.campana-edit', [
            'pageTitle' => 'Editar Campaña SEO',
            'campana' => $campana,
            'clientes' => Cliente::orderBy('nombre')->get(['id', 'nombre']),
            'serviciosSeo' => Servicio::where('cliente_id', $campana->cliente_id)->where('tipo', 'seo')->get(),
        ]);
    }

    public function update(Request $request, SeoCampana $campana): RedirectResponse
    {
        $data = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'servicio_id' => ['required', 'integer', 'exists:servicios,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'url_sitio' => ['nullable', 'string', 'max:255'],
            'estado' => ['required', 'in:activa,pausada,finalizada'],
            'fecha_inicio' => ['nullable', 'date'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);

        $campana->update($data);

        return redirect()->route('admin.seo.show', $campana)->with('status', "Campaña \"{$campana->nombre}\" actualizada correctamente.");
    }

    public function destroy(SeoCampana $campana): RedirectResponse
    {
        $campana->delete();

        return redirect()->route('admin.seo.index')->with('status', 'Campaña SEO eliminada.');
    }
}
