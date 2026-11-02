<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoCampana;
use App\Enums\EstadoKeyword;
use App\Enums\EstadoOnPageAccion;
use App\Enums\TipoActividadSeo;
use App\Http\Controllers\Admin\Concerns\AcotaPorClienteActivo;
use App\Http\Controllers\Controller;
use App\Models\Keyword;
use App\Models\KeywordCluster;
use App\Models\MetricaCliente;
use App\Models\MetricaClienteValor;
use App\Models\SeoCampana;
use App\Models\SeoOnPageAccion;
use App\Models\SeoPlan;
use App\Models\User;
use App\Support\ClienteActivo;
use App\Support\Metricas\Semaforo;
use App\Support\SeoPlan\PosicionesKeyword;
use App\Support\SeoPlan\Resultados;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * SEO Plan: pantalla única por cliente activo con tres pestañas (Plan,
 * Keywords, Resultados). Solo lee de los módulos existentes; lo único propio
 * es la meta general, los clústeres y las notas de cada actividad.
 */
class SeoPlanController extends Controller
{
    use AcotaPorClienteActivo;

    public function index(): View
    {
        $cliente = ClienteActivo::modelo();

        if (! $cliente) {
            return view('admin.seo-plan.index', ['pageTitle' => 'SEO Plan', 'cliente' => null]);
        }

        $plan = SeoPlan::query()->where('cliente_id', $cliente->id)->first();

        // Metas: métricas del cliente con su último valor y semáforo (no se duplican, se leen).
        $metricas = MetricaCliente::query()
            ->where('cliente_id', $cliente->id)->where('activa', true)
            ->whereNotNull('meta')
            ->orderBy('orden')->orderBy('id')->get();
        $ultimos = MetricaClienteValor::query()
            ->whereIn('metrica_id', $metricas->pluck('id'))
            ->orderByDesc('periodo')->get()->groupBy('metrica_id');
        $metas = $metricas->map(function (MetricaCliente $m) use ($ultimos) {
            $ultimo = $ultimos->get($m->id)?->first();

            return [
                'metrica' => $m,
                'valor' => $ultimo?->valor,
                'periodo' => $ultimo?->periodo,
                'semaforo' => Semaforo::evaluar($ultimo?->valor, $m->meta, $m->sentido, $m->umbral_amarillo_pct),
            ];
        });
        $sinMeta = MetricaCliente::query()->where('cliente_id', $cliente->id)->where('activa', true)->whereNull('meta')->count();

        // Actividades: acciones de las campañas del cliente.
        $campanas = SeoCampana::query()->where('cliente_id', $cliente->id)
            ->where('estado', '!=', EstadoCampana::Finalizada->value)
            ->orderByDesc('id')->get(['id', 'nombre']);
        $actividades = SeoOnPageAccion::query()
            ->whereHas('seoCampana', fn ($q) => $q->withTrashed()->where('cliente_id', $cliente->id))
            ->with(['responsable:id,name', 'cluster:id,nombre'])
            ->withCount('notas')
            ->orderBy('orden')->orderBy('id')->get();

        // Clústeres y keywords.
        $clusters = KeywordCluster::query()->where('cliente_id', $cliente->id)->orderBy('nombre')->get();
        $keywords = Keyword::query()->where('cliente_id', $cliente->id)
            ->where('estado', '!=', EstadoKeyword::Descartada->value)
            ->orderBy('keyword')->get();
        $posiciones = PosicionesKeyword::para($keywords);

        return view('admin.seo-plan.index', [
            'pageTitle' => 'SEO Plan',
            'cliente' => $cliente,
            'plan' => $plan,
            'metas' => $metas,
            'metricasSinMeta' => $sinMeta,
            'campanas' => $campanas,
            'actividades' => $actividades,
            'columnas' => EstadoOnPageAccion::cases(),
            'tipos' => TipoActividadSeo::cases(),
            'usuarios' => User::query()->orderBy('name')->pluck('name', 'id'),
            'clusters' => $clusters,
            'keywords' => $keywords,
            'posiciones' => $posiciones,
            'rangos' => Resultados::rangos($posiciones),
            'cierres' => Resultados::cierres($actividades),
            'conMedicion' => Resultados::conMedicion($actividades),
        ]);
    }

    public function guardarPlan(Request $request): RedirectResponse
    {
        $clienteId = $this->clienteActivoId();
        $datos = $request->validate(['meta_general' => ['nullable', 'string', 'max:2000']]);

        SeoPlan::query()->updateOrCreate(['cliente_id' => $clienteId], ['meta_general' => $datos['meta_general'] ?? null]);

        return redirect()->route('admin.seo-plan.index')->with('status', 'Meta general guardada.');
    }
}
