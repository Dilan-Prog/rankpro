<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FaseSeo;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\SeoCampana;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SeoController extends Controller
{
    public function index(): View
    {
        $clientes = Cliente::whereHas('servicios', fn ($q) => $q->where('tipo', 'seo'))
            ->with([
                'servicios' => fn ($q) => $q->where('tipo', 'seo'),
                'seoCampanas' => fn ($q) => $q->latest('created_at')->with('faseAuditoria', 'reporteActual'),
            ])
            ->orderBy('nombre')
            ->get()
            ->map(fn (Cliente $cliente) => $this->toClienteRow($cliente));

        $scores = $clientes->pluck('seo_score')->filter(fn ($s) => $s !== null);
        $trafico = $clientes->pluck('trafico_actual')->filter(fn ($t) => $t !== null);

        return view('admin.seo.index', [
            'pageTitle' => 'Módulo SEO',
            'clientes' => $clientes,
            'clientesConSeo' => $clientes->count(),
            'campanasActivas' => $clientes->where('estado', 'activa')->count(),
            'scorePromedio' => $scores->isNotEmpty() ? round($scores->avg(), 1) : null,
            'traficoTotal' => (int) $trafico->sum(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, forUpdate: false);

        $campana = DB::transaction(function () use ($data) {
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

            // Empty ciclo-1 rows for every phase — identical shape to what
            // SeoFaseController::nuevoCiclo() creates for a fresh cycle, so a
            // brand-new campaign lands on the same "empty phase panel, fill
            // it in from here" starting point as any later cycle does.
            $campana->auditorias()->create(['ciclo' => 1, 'checklist' => []]);
            $campana->estrategias()->create(['ciclo' => 1, 'checklist' => []]);
            $campana->ejecuciones()->create(['ciclo' => 1, 'checklist' => []]);
            $campana->reportes()->create(['ciclo' => 1, 'checklist' => []]);

            return $campana;
        });

        return response()->json(['show_url' => route('admin.seo.show', $campana->id)], 201);
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
                'contenido',
                'onPageAcciones.responsable',
                'metricasMensuales'
            ),
            'usuarios' => User::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function update(Request $request, SeoCampana $campana): JsonResponse
    {
        $data = $this->validated($request, forUpdate: true);

        $campana->update($data);

        $cliente = Cliente::with([
            'servicios' => fn ($q) => $q->where('tipo', 'seo'),
            'seoCampanas' => fn ($q) => $q->latest('created_at')->with('faseAuditoria', 'reporteActual'),
        ])->findOrFail($campana->cliente_id);

        return response()->json($this->toClienteRow($cliente));
    }

    public function destroy(SeoCampana $campana): JsonResponse
    {
        $clienteId = $campana->cliente_id;
        $campana->delete();

        $cliente = Cliente::with([
            'servicios' => fn ($q) => $q->where('tipo', 'seo'),
            'seoCampanas' => fn ($q) => $q->latest('created_at')->with('faseAuditoria', 'reporteActual'),
        ])->findOrFail($clienteId);

        return response()->json($this->toClienteRow($cliente));
    }

    /**
     * Shared shape for index()'s client-picker cards and store()/update()/
     * destroy()'s AJAX responses. One row per client-with-an-SEO-servicio;
     * 'campana_*' fields are null when that client has no SeoCampana yet
     * (renders as an empty "Crear campaña" card). When a client has more
     * than one campaign (e.g. a closed one plus a fresh restart), only the
     * most recently created is surfaced here — older campaigns stay in the
     * database but aren't reachable from this picker, mirroring the
     * reference's one-entity-per-client model.
     */
    private function toClienteRow(Cliente $cliente): array
    {
        $campana = $cliente->seoCampanas->first();

        return [
            'cliente_id' => $cliente->id,
            'cliente' => $cliente->nombre,
            'contacto' => $cliente->contacto_nombre,
            'mrr' => (float) $cliente->servicios->sum('precio_mensual'),
            'servicios_seo' => $cliente->servicios->map(fn ($s) => ['id' => $s->id, 'nombre' => $s->nombre])->values(),
            'campana_id' => $campana?->id,
            'campana_nombre' => $campana?->nombre,
            'url_sitio' => $campana?->url_sitio,
            'servicio_id' => $campana?->servicio_id,
            'estado' => $campana?->estado->value,
            'fase_actual' => $campana?->fase_actual->value,
            'ciclo_actual' => $campana?->ciclo_actual,
            'fecha_inicio' => $campana?->fecha_inicio?->format('Y-m-d'),
            'notas' => $campana?->notas,
            'seo_score' => $campana?->faseAuditoria?->seo_score,
            'trafico_actual' => $campana?->reporteActual?->trafico_actual,
            'show_url' => $campana ? route('admin.seo.show', $campana->id) : null,
        ];
    }

    private function validated(Request $request, bool $forUpdate): array
    {
        $rules = [
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'servicio_id' => ['required', 'integer', 'exists:servicios,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'url_sitio' => ['nullable', 'string', 'max:255'],
            'fecha_inicio' => ['nullable', 'date'],
        ];

        if ($forUpdate) {
            $rules['estado'] = ['required', 'in:activa,pausada,finalizada'];
            $rules['notas'] = ['nullable', 'string', 'max:2000'];
        }

        return $request->validate($rules);
    }
}
