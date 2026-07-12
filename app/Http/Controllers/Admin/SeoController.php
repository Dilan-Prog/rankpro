<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FaseSeo;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\SeoCampana;
use App\Models\SeoFaseAuditoria;
use App\Models\Servicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SeoController extends Controller
{
    public function index(): View
    {
        $campanas = SeoCampana::with('cliente', 'faseAuditoria', 'reporteActual')
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
                'seo_score' => $c->faseAuditoria?->seo_score,
                'trafico_actual' => $c->reporteActual?->trafico_actual,
            ]);

        return view('admin.seo.index', [
            'pageTitle' => 'Módulo SEO',
            'campanas' => $campanas,
            'enProceso' => $campanas->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.seo.create', [
            'pageTitle' => 'Nueva Campaña SEO',
            'clientes' => Cliente::with('servicios')->orderBy('nombre')->get(),
            'checklistAuditoria' => SeoFaseAuditoria::CHECKLIST,
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
            'velocidad_mobile' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'velocidad_desktop' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lcp_mobile' => ['nullable', 'numeric', 'min:0'],
            'fid_mobile' => ['nullable', 'numeric', 'min:0'],
            'cls_mobile' => ['nullable', 'numeric', 'min:0'],
            'lcp_desktop' => ['nullable', 'numeric', 'min:0'],
            'fid_desktop' => ['nullable', 'numeric', 'min:0'],
            'cls_desktop' => ['nullable', 'numeric', 'min:0'],
            'errores_tecnicos' => ['nullable', 'integer', 'min:0'],
            'indexacion_ok' => ['nullable', 'boolean'],
            'sitemap_ok' => ['nullable', 'boolean'],
            'robots_ok' => ['nullable', 'boolean'],
            'errores_404' => ['nullable', 'integer', 'min:0'],
            'redirecciones_incorrectas' => ['nullable', 'integer', 'min:0'],
            'duplicidad_contenido' => ['nullable', 'boolean'],
            'canonical_ok' => ['nullable', 'boolean'],
            'schema_ok' => ['nullable', 'boolean'],
            'herramienta' => ['nullable', 'in:semrush,ahrefs,screaming_frog,google_search_console,otro'],
            'notas' => ['nullable', 'string', 'max:2000'],

            'checklist' => ['nullable', 'array'],
            'checklist.*' => ['boolean'],
        ]);

        $checklistKeys = array_keys(SeoFaseAuditoria::CHECKLIST);
        $checklist = collect($checklistKeys)->mapWithKeys(fn ($key) => [$key => (bool) ($data['checklist'][$key] ?? false)])->all();

        $campana = DB::transaction(function () use ($request, $data, $checklist) {
            $campana = SeoCampana::create([
                'cliente_id' => $data['cliente_id'],
                'servicio_id' => $data['servicio_id'],
                'nombre' => $data['nombre'],
                'url_sitio' => $data['url_sitio'] ?? null,
                'estado' => 'activa',
                'fase_actual' => FaseSeo::Auditoria->value,
                'ciclo_actual' => 1,
                'fecha_inicio' => $data['fecha_inicio'] ?? null,
            ]);

            $campana->auditorias()->create([
                'ciclo' => 1,
                'seo_score' => $data['seo_score'] ?? null,
                'velocidad_mobile' => $data['velocidad_mobile'] ?? null,
                'velocidad_desktop' => $data['velocidad_desktop'] ?? null,
                'lcp_mobile' => $data['lcp_mobile'] ?? null,
                'fid_mobile' => $data['fid_mobile'] ?? null,
                'cls_mobile' => $data['cls_mobile'] ?? null,
                'lcp_desktop' => $data['lcp_desktop'] ?? null,
                'fid_desktop' => $data['fid_desktop'] ?? null,
                'cls_desktop' => $data['cls_desktop'] ?? null,
                'errores_tecnicos' => $data['errores_tecnicos'] ?? null,
                'indexacion_ok' => $request->boolean('indexacion_ok'),
                'sitemap_ok' => $request->boolean('sitemap_ok'),
                'robots_ok' => $request->boolean('robots_ok'),
                'errores_404' => $data['errores_404'] ?? null,
                'redirecciones_incorrectas' => $data['redirecciones_incorrectas'] ?? null,
                'duplicidad_contenido' => $request->boolean('duplicidad_contenido'),
                'canonical_ok' => $request->boolean('canonical_ok'),
                'schema_ok' => $request->boolean('schema_ok'),
                'herramienta' => $data['herramienta'] ?? null,
                'notas' => $data['notas'] ?? null,
                'checklist' => $checklist,
            ]);

            $campana->estrategias()->create(['ciclo' => 1, 'checklist' => []]);
            $campana->ejecuciones()->create(['ciclo' => 1, 'checklist' => []]);
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
                'contenido'
            ),
        ]);
    }

    public function edit(SeoCampana $campana): View
    {
        return view('admin.seo.edit', [
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
