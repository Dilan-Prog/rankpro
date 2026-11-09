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
use App\Support\ClienteActivo;
use App\Support\FinanzasMetrics;
use App\Support\Labels;
use App\Support\Reglas\Ads as ReglasAds;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdsController extends Controller
{
    public function index(): View
    {
        // Cliente activo del menú (grupo Marca): campañas, KPIs y gráficas salen solo de sus campañas.
        $clienteActivo = ClienteActivo::modelo();

        $modelos = AdsCampana::with(['cliente', 'metricas'])
            ->when($clienteActivo, fn ($q) => $q->where('cliente_id', $clienteActivo->id))
            ->orderBy('created_at', 'desc')
            ->get();
        $campanas = $modelos->map(fn (AdsCampana $campana) => $this->toRow($campana));

        // Fecha del dato más reciente: el último mes capturado (se cuenta desde que cerró), no la edición de la fila.
        $ultimoPeriodo = $modelos->flatMap->metricas->sortByDesc(fn ($m) => $m->anio * 12 + $m->mes)->first();

        // Con cliente activo su tarjeta se muestra aunque aún no tenga campañas (no depende de las filas).
        $idsCuentas = $clienteActivo ? [$clienteActivo->id] : $campanas->pluck('cliente_id')->unique()->all();

        return view('admin.ads.index', [
            'pageTitle' => 'Módulo Ads',
            'clienteActivo' => $clienteActivo,
            'campanas' => $campanas,
            'ultimoPeriodo' => $ultimoPeriodo ? ['mes' => (int) $ultimoPeriodo->mes, 'anio' => (int) $ultimoPeriodo->anio] : null,
            'clientes' => Cliente::with('servicios')->ordenadoPorNombreComercial()->get(),
            'cuentasInfo' => $this->cuentasInfo($idsCuentas),
        ]);
    }

    /**
     * Datos reales de Cliente para la tarjeta de la cuenta seleccionada (solo clientes con campañas, que son las
     * únicas cuentas que el selector muestra). El MRR sale de FinanzasMetrics (fuente única): solo cobro mensual activo, y en consulta
     * porque Servicio::estado es un enum y un `==` contra el string 'activo' nunca coincide en PHP.
     */
    private function cuentasInfo(array $clienteIds): array
    {
        $mrrPorCliente = FinanzasMetrics::mrrPorCliente($clienteIds);

        return Cliente::with('servicios')->whereIn('id', $clienteIds)->get()->mapWithKeys(fn (Cliente $c) => [
            $c->id => [
                'nombre' => $c->nombre_comercial,
                'contacto' => $c->contacto_nombre,
                'email' => $c->email,
                'telefono' => $c->telefono,
                'mrr' => (float) ($mrrPorCliente[$c->id] ?? 0),
                'inicio_contrato' => $c->fecha_inicio_contrato?->format('Y-m-d'),
                'renovacion_contrato' => $c->fecha_renovacion_contrato?->format('Y-m-d'),
                'servicios' => $c->servicios
                    ->filter(fn ($s) => $s->estado->value === 'activo')
                    ->map(fn ($s) => Labels::servicioTipo($s->tipo))
                    ->unique()
                    ->values()
                    ->all(),
            ],
        ])->all();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(ReglasAds::campana());

        $campana = DB::transaction(function () use ($data) {
            $campana = AdsCampana::create([
                'cliente_id' => $data['cliente_id'],
                'servicio_id' => $data['servicio_id'],
                'nombre' => $data['nombre'],
                'plataforma' => $data['plataforma'],
                'plataforma_cuenta_id' => $data['plataforma_cuenta_id'] ?? null,
                'plataforma_campana_id' => $data['plataforma_campana_id'] ?? null,
                'objetivo' => $data['objetivo'],
                'presupuesto_mensual' => $data['presupuesto_mensual'],
                // Sin moneda se usa el default de la columna (MXN), no un null.
                'moneda' => $data['moneda'] ?? 'MXN',
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
        $data = $request->validate(ReglasAds::campana($campana));
        // La columna es NOT NULL: una moneda vacía significa "no cambiar", no null (daba 500).
        if (($data['moneda'] ?? null) === null) {
            unset($data['moneda']);
        }

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
        // Ingreso real por mes: valor_conversion si se capturó; si no, inversión del mes × ROAS del mes. El promedio simple de roas
        // pesaba igual un mes de $100 que uno de $10,000 e ignoraba valor_conversion. Los meses sin ningún dato de ingreso no entran
        // al denominador (su inversión no tiene ingreso que comparar).
        $ingreso = 0.0;
        $inversionConIngreso = 0.0;
        foreach ($campana->metricas as $m) {
            if ($m->valor_conversion !== null) {
                $ingresoMes = (float) $m->valor_conversion;
            } elseif ($m->roas !== null) {
                $ingresoMes = (float) $m->inversion_real * (float) $m->roas;
            } else {
                continue;
            }
            $ingreso += $ingresoMes;
            $inversionConIngreso += (float) $m->inversion_real;
        }
        $roas = $inversionConIngreso > 0 ? round($ingreso / $inversionConIngreso, 2) : 0.0;
        $impresiones = (int) $campana->metricas->sum('impresiones');
        $clics = (int) $campana->metricas->sum('clics');
        $conversiones = (int) $campana->metricas->sum('conversiones');

        // Solo la métrica del mes en curso: el presupuesto es mensual, comparar contra la inversión histórica no tendría sentido.
        $metricaMes = $campana->metricas->first(fn ($m) => (int) $m->mes === (int) now()->month && (int) $m->anio === (int) now()->year);

        return [
            'id' => $campana->id,
            'cliente_id' => $campana->cliente_id,
            'cliente' => $campana->cliente?->nombre_comercial ?? '—',
            'cliente_contacto' => $campana->cliente?->contacto_nombre,
            'servicio_id' => $campana->servicio_id,
            'nombre' => $campana->nombre,
            'plataforma' => $campana->plataforma,
            'plataforma_cuenta_id' => $campana->plataforma_cuenta_id,
            'plataforma_campana_id' => $campana->plataforma_campana_id,
            'objetivo' => $campana->objetivo,
            'estado' => $campana->estado->value,
            'fase_actual' => $campana->fase_actual->value,
            'ciclo_actual' => $campana->ciclo_actual,
            'presupuesto_mensual' => (float) $campana->presupuesto_mensual,
            'moneda' => $campana->moneda ?? 'MXN',
            'inversion' => $inversion,
            'impresiones' => $impresiones,
            'clics' => $clics,
            'conversiones' => $conversiones,
            // Razones calculadas de las sumas de AdsMetrica (no el promedio de las columnas ctr/cpc/cpa guardadas): null = denominador 0, la vista muestra guion.
            'ctr' => $impresiones > 0 ? round(($clics / $impresiones) * 100, 2) : null,
            'cpc' => $clics > 0 ? round($inversion / $clics, 2) : null,
            'cpa' => $conversiones > 0 ? round($inversion / $conversiones, 2) : null,
            'metricas_count' => $campana->metricas->count(),
            'inversion_mes' => $metricaMes ? (float) $metricaMes->inversion_real : null,
            'roas' => $roas,
            'ingreso_atribuido' => round($ingreso, 2),
            // Inversión de los meses que sí tienen dato de ingreso: denominador correcto del ROAS global (los KPI de la lista).
            'inversion_con_ingreso' => round($inversionConIngreso, 2),
            'fecha_inicio' => $campana->fecha_inicio?->format('Y-m-d'),
            'fecha_fin' => $campana->fecha_fin?->format('Y-m-d'),
            'notas' => $campana->notas,
            'show_url' => route('admin.ads.show', $campana->id),
        ];
    }
}
