<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FaseSeo;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\SeoCampana;
use App\Models\User;
use App\Support\Calidad\Antiguedad;
use App\Support\ClienteActivo;
use App\Support\FinanzasMetrics;
use App\Support\Reglas\SeoCampanas as ReglasSeoCampanas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SeoController extends Controller
{
    public function index(): View
    {
        // Cliente activo del menú (grupo Marca): cuentas, contadores y KPIs son solo los suyos.
        $clienteActivo = ClienteActivo::modelo();
        $clientes = $this->cuentas($clienteActivo?->id);

        $scores = $clientes->pluck('seo_score')->filter(fn ($s) => $s !== null);
        $trafico = $clientes->pluck('trafico_actual')->filter(fn ($t) => $t !== null);

        // Edad de las cifras: score = fecha de la auditoría (la más vieja manda; las sin fecha se cuentan aparte);
        // tráfico = última edición del reporte (no hay fecha de captura propia), también la más vieja.
        $conScore = $clientes->filter(fn ($c) => $c['seo_score'] !== null);
        $fechasScore = $conScore->pluck('seo_score_fecha')->filter()->sort()->values();
        $fechasTrafico = $clientes->filter(fn ($c) => $c['trafico_actual'] !== null)->pluck('trafico_fecha')->filter()->sort()->values();

        return view('admin.seo.index', [
            'pageTitle' => 'Módulo SEO',
            'clienteActivo' => $clienteActivo,
            'clientes' => $clientes,
            'clientesConSeo' => $clientes->count(),
            'campanasActivas' => $clientes->where('estado', 'activa')->count(),
            'scorePromedio' => $scores->isNotEmpty() ? round($scores->avg(), 1) : null,
            'traficoTotal' => (int) $trafico->sum(),
            'scoreFecha' => $fechasScore->first(),
            'scoreSinFecha' => $conScore->count() - $fechasScore->count(),
            'traficoFecha' => $fechasTrafico->first(),
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
            // Tarjetas selectoras de cuenta (mismas filas que el índice) para cambiar de cuenta sin volver a la lista.
            'cuentas' => $this->cuentas(),
            'resumenCuenta' => $this->resumenCuenta($campana),
            'serieTrafico' => $this->serieTrafico($campana),
            'serviciosCliente' => $campana->cliente->servicios()->where('estado', '!=', 'cancelado')->orderBy('tipo')->get(),
        ]);
    }

    public function update(Request $request, SeoCampana $campana): JsonResponse
    {
        $data = $this->validated($request, forUpdate: true);
        \App\Support\Reglas\CoherenciaEstadoFase::validar($campana, $data['estado']);

        $campana->update($data);

        $cliente = Cliente::with([
            'servicios' => fn ($q) => $q->where('tipo', 'seo')->with('responsable'),
            'seoCampanas' => fn ($q) => $q->latest('created_at')->with('faseAuditoria', 'reporteActual'),
        ])->findOrFail($campana->cliente_id);

        return response()->json($this->toClienteRow($cliente));
    }

    public function destroy(SeoCampana $campana): JsonResponse
    {
        $clienteId = $campana->cliente_id;
        $campana->delete();

        $cliente = Cliente::with([
            'servicios' => fn ($q) => $q->where('tipo', 'seo')->with('responsable'),
            'seoCampanas' => fn ($q) => $q->latest('created_at')->with('faseAuditoria', 'reporteActual'),
        ])->findOrFail($clienteId);

        return response()->json($this->toClienteRow($cliente));
    }

    /** Una fila por cliente con servicio SEO; la comparten el índice y el selector de cuentas del detalle. */
    private function cuentas(?int $clienteId = null): Collection
    {
        return Cliente::whereHas('servicios', fn ($q) => $q->where('tipo', 'seo'))
            ->when($clienteId, fn ($q) => $q->whereKey($clienteId))
            ->with([
                'servicios' => fn ($q) => $q->where('tipo', 'seo')->with('responsable'),
                'seoCampanas' => fn ($q) => $q->latest('created_at')->with('faseAuditoria', 'reporteActual'),
            ])
            ->ordenadoPorNombreComercial()
            ->get()
            ->map(fn (Cliente $cliente) => $this->toClienteRow($cliente));
    }

    /**
     * Filas etiqueta/valor de "Resumen de la cuenta". Todo sale de datos ya
     * capturados; lo que no existe se deja en null para que la vista diga
     * "Sin dato" en lugar de mostrar un 0 que parezca medición.
     */
    private function resumenCuenta(SeoCampana $campana): array
    {
        // Keywords de la campaña = las de la estrategia (keywords_ids) más las del banco ligadas por campana_id.
        $keywords = ($campana->faseEstrategia?->keywords() ?? collect())
            ->concat($campana->keywords()->get())
            ->unique('id');

        $ultimaMetrica = $campana->metricasMensuales->last();

        return [
            'keywords_total' => $keywords->count(),
            'keywords_con_posicion' => $keywords->whereNotNull('posicion_actual')->count(),
            'keywords_top10' => $keywords->filter(fn ($k) => $k->posicion_actual !== null && $k->posicion_actual > 0 && $k->posicion_actual <= 10)->count(),
            'posiciones_registradas' => $campana->posiciones->count(),
            'backlinks_activos' => $campana->backlinks->filter(fn ($i) => ($i->estado->value ?? $i->estado) === 'activo')->count(),
            'backlinks_total' => $campana->backlinks->count(),
            'contenidos_publicados' => $campana->contenido->filter(fn ($i) => ($i->estado->value ?? $i->estado) === 'publicado')->count(),
            'contenidos_total' => $campana->contenido->count(),
            'onpage_completadas' => $campana->onPageAcciones->filter(fn ($i) => ($i->estado->value ?? $i->estado) === 'completada')->count(),
            'onpage_total' => $campana->onPageAcciones->count(),
            'ultima_metrica' => $ultimaMetrica ? sprintf('%02d/%d', $ultimaMetrica->mes, $ultimaMetrica->anio) : null,
        ];
    }

    /**
     * Serie de tráfico orgánico por mes calendario (máx. 12, terminando en el
     * último mes capturado). Los meses sin dato quedan en null a propósito:
     * la gráfica corta la línea en vez de interpolar una tendencia inventada.
     */
    private function serieTrafico(SeoCampana $campana): ?array
    {
        $porMes = [];
        foreach ($campana->metricasMensuales as $m) {
            if ($m->trafico_organico !== null) {
                $porMes[$m->anio * 12 + ($m->mes - 1)] = $m->trafico_organico;
            }
        }
        if ($porMes === []) {
            return null;
        }

        $fin = max(array_keys($porMes));
        $inicio = max(min(array_keys($porMes)), $fin - 11);
        $nombres = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $completos = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

        $meses = [];
        for ($i = $inicio; $i <= $fin; $i++) {
            $meses[] = [
                'etiqueta' => $nombres[$i % 12],
                'anio' => intdiv($i, 12),
                'valor' => $porMes[$i] ?? null,
            ];
        }

        // Delta solo contra el mes calendario inmediato anterior; con hueco no se compara.
        $delta = null;
        $previo = $porMes[$fin - 1] ?? null;
        if ($previo !== null && $previo > 0) {
            $delta = ['pct' => round((($porMes[$fin] - $previo) / $previo) * 100), 'contra' => $completos[($fin - 1) % 12]];
        }

        // El último mes con dato fija la edad de la gráfica (se cuenta desde que ese mes cerró).
        return ['meses' => $meses, 'delta' => $delta, 'ultimo' => ['mes' => ($fin % 12) + 1, 'anio' => intdiv($fin, 12)]];
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
            'cliente' => $cliente->nombre_comercial,
            'contacto' => $cliente->contacto_nombre,
            'responsable' => $cliente->servicios->pluck('responsable.name')->filter()->first(),
            // Fuente única del MRR: solo activos de cobro mensual (un pausado, único o gratuito no es ingreso recurrente).
            'mrr' => FinanzasMetrics::mrrDeServicios($cliente->servicios),
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
            'seo_score_fecha' => $campana?->faseAuditoria?->fecha_auditoria?->format('Y-m-d'),
            'seo_score_nota' => $this->notaScore($campana?->faseAuditoria),
            'seo_score_antiguo' => $this->scoreAntiguo($campana?->faseAuditoria),
            'trafico_fecha' => $campana?->reporteActual?->updated_at?->format('Y-m-d'),
            'trafico_actual' => $campana?->reporteActual?->trafico_actual,
            'show_url' => $campana ? route('admin.seo.show', $campana->id) : null,
        ];
    }

    /** Texto de la tarjeta: de dónde sale el score y de cuándo es (o que no se sabe). */
    private function notaScore(?\App\Models\SeoFaseAuditoria $auditoria): ?string
    {
        if ($auditoria?->seo_score === null) {
            return null;
        }

        $evaluacion = Antiguedad::evaluar($auditoria->fecha_auditoria, null, Antiguedad::TIPO_AUDITORIA);
        $texto = 'Auditoría SEO · '.($auditoria->fecha_auditoria ? fecha_mx($auditoria->fecha_auditoria) : 'Sin fecha de auditoría');

        return $evaluacion['estado'] === Antiguedad::ANTIGUO ? $texto.' · hace '.$evaluacion['dias'].' días' : $texto;
    }

    private function scoreAntiguo(?\App\Models\SeoFaseAuditoria $auditoria): bool
    {
        return $auditoria?->seo_score !== null
            && Antiguedad::evaluar($auditoria->fecha_auditoria, null, Antiguedad::TIPO_AUDITORIA)['estado'] === Antiguedad::ANTIGUO;
    }

    private function validated(Request $request, bool $forUpdate): array
    {
        $rules = $forUpdate ? ReglasSeoCampanas::actualizar() : ReglasSeoCampanas::crear();

        return $request->validate($rules);
    }
}
