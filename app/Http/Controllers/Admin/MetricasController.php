<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\MetricaCliente;
use App\Models\MetricaClienteValor;
use App\Support\Metricas\GuardadorValores;
use App\Support\Metricas\Semaforo;
use App\Support\Reglas\Metricas as ReglasMetricas;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Captura mensual a mano de métricas por cliente: pantalla del mes con
 * guardado en bloque, e histórico de 12 meses por métrica.
 */
class MetricasController extends Controller
{
    public function index(Request $request): View
    {
        $clientes = Cliente::query()->orderBy('nombre')->get(['id', 'nombre']);
        $clienteId = $request->query('cliente');
        $cliente = is_string($clienteId) && ctype_digit($clienteId) ? $clientes->firstWhere('id', (int) $clienteId) : null;
        $mes = $this->resolverMes($request->query('mes'));
        $mesAnterior = $mes->copy()->subMonthNoOverflow();

        $filas = [];
        $definiciones = collect();
        $copiado = false;

        if ($cliente) {
            $definiciones = MetricaCliente::query()->where('cliente_id', $cliente->id)->orderBy('orden')->orderBy('id')->get();
            $ids = $definiciones->pluck('id');
            $actuales = MetricaClienteValor::query()->whereIn('metrica_id', $ids)->whereDate('periodo', $mes)->get()->keyBy('metrica_id');
            $previos = MetricaClienteValor::query()->whereIn('metrica_id', $ids)->whereDate('periodo', $mesAnterior)->get()->keyBy('metrica_id');
            $copiado = $request->boolean('copiar');

            foreach ($definiciones->where('activa', true) as $metrica) {
                $actual = $actuales->get($metrica->id);
                $previo = $previos->get($metrica->id);
                $valor = $actual?->valor;
                $valorPrevio = $previo?->valor;

                // "Copiar mes anterior" solo rellena la caja de captura de lo que aún no tiene valor; no guarda.
                $precargado = $copiado && $actual === null && $previo !== null;

                $filas[] = [
                    'metrica' => $metrica,
                    'valor' => $valor,
                    'valor_input' => $valor ?? ($precargado ? $valorPrevio : null),
                    'precargado' => $precargado,
                    'valor_previo' => $valorPrevio,
                    'nota' => $actual?->nota,
                    'semaforo' => Semaforo::evaluar($valor, $metrica->meta, $metrica->sentido, $metrica->umbral_amarillo_pct),
                    'variacion' => self::variacion($valor, $valorPrevio),
                ];
            }
        }

        return view('admin.metricas.index', [
            'pageTitle' => 'Métricas',
            'clientes' => $clientes,
            'cliente' => $cliente,
            'mes' => $mes,
            'mesAnterior' => $mesAnterior,
            'filas' => $filas,
            'definiciones' => $definiciones,
            'copiado' => $copiado,
        ]);
    }

    public function guardarMes(Request $request): RedirectResponse
    {
        $request->merge(['valores' => $this->normalizarValores($request->input('valores'))]);

        $datos = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'mes' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'valores' => ['nullable', 'array'],
            'valores.*' => ReglasMetricas::valor(),
            'notas' => ['nullable', 'array'],
            'notas.*' => ['nullable', 'string', 'max:2000'],
        ], [], $this->nombresDeCampos((int) $request->input('cliente_id')));

        $periodo = Carbon::createFromFormat('!Y-m', $datos['mes']);
        // Solo métricas de este cliente: un id ajeno en el payload se ignora.
        $metricas = MetricaCliente::query()->where('cliente_id', $datos['cliente_id'])->pluck('id')->all();
        $valores = $datos['valores'] ?? [];
        $notas = $datos['notas'] ?? [];
        $guardados = 0;

        DB::transaction(function () use ($valores, $notas, $metricas, $periodo, &$guardados) {
            foreach ($valores as $metricaId => $valor) {
                if (! in_array((int) $metricaId, $metricas, true)) {
                    continue;
                }

                $nota = $notas[$metricaId] ?? null;

                if (GuardadorValores::guardar((int) $metricaId, $periodo, $valor, $nota, auth()->id()) === null) {
                    continue;
                }
                $guardados++;
            }
        });

        return redirect()
            ->route('admin.metricas.index', ['cliente' => $datos['cliente_id'], 'mes' => $datos['mes']])
            ->with('status', "Mes guardado: {$guardados} valor(es).");
    }

    public function show(Request $request, MetricaCliente $metrica): View
    {
        $fin = $this->resolverMes($request->query('mes'));
        $inicio = $fin->copy()->subMonthsNoOverflow(11);

        $valores = $metrica->valores()
            ->whereDate('periodo', '>=', $inicio)
            ->whereDate('periodo', '<=', $fin)
            ->get()
            ->keyBy(fn ($v) => $v->periodo->format('Y-m'));

        $meses = [];
        $anterior = null;
        for ($i = 0; $i < 12; $i++) {
            $m = $inicio->copy()->addMonthsNoOverflow($i);
            $registro = $valores->get($m->format('Y-m'));
            $valor = $registro?->valor;
            $meses[] = [
                'mes' => $m,
                'valor' => $valor,
                'nota' => $registro?->nota,
                'semaforo' => Semaforo::evaluar($valor, $metrica->meta, $metrica->sentido, $metrica->umbral_amarillo_pct),
                'variacion' => self::variacion($valor, $anterior),
            ];
            $anterior = $valor;
        }

        return view('admin.metricas.show', [
            'pageTitle' => $metrica->nombre,
            'metrica' => $metrica->load('cliente'),
            'meses' => $meses,
            'grafica' => self::grafica($meses, $metrica->meta),
            'fin' => $fin,
        ]);
    }

    /** Acepta "2026-10"; cualquier otra cosa cae al mes actual. */
    private function resolverMes(mixed $mes): Carbon
    {
        // mixed: ?mes[]=x llega como array y un tipo ?string lo convertía en 500.
        if (is_string($mes) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
            return Carbon::createFromFormat('!Y-m', $mes);
        }

        return now()->startOfMonth();
    }

    /**
     * Para que el error diga "valor de QA-Llamadas" y no "valores.2".
     *
     * @return array<string, string>
     */
    private function nombresDeCampos(int $clienteId): array
    {
        $nombres = [];
        foreach (MetricaCliente::query()->where('cliente_id', $clienteId)->get(['id', 'nombre']) as $m) {
            $nombres["valores.{$m->id}"] = "valor de {$m->nombre}";
            $nombres["notas.{$m->id}"] = "nota de {$m->nombre}";
        }

        return $nombres;
    }

    private function normalizarValores(mixed $valores): mixed
    {
        if (! is_array($valores)) {
            return $valores;
        }

        return array_map([ReglasMetricas::class, 'normalizarDecimal'], $valores);
    }

    /** @return array{abs: float, pct: ?float}|null */
    private static function variacion(mixed $valor, mixed $previo): ?array
    {
        if ($valor === null || $previo === null) {
            return null;
        }
        $abs = (float) $valor - (float) $previo;

        return ['abs' => round($abs, 2), 'pct' => (float) $previo != 0.0 ? round($abs / abs((float) $previo) * 100, 1) : null];
    }

    /**
     * Coordenadas para la mini-gráfica SVG (viewBox 600x180), calculadas aquí
     * para que la vista solo pinte.
     *
     * @return array{ancho: int, alto: int, puntos: array, lineas: array<int, string>, meta_y: ?float}
     */
    private static function grafica(array $meses, mixed $meta): array
    {
        $ancho = 600;
        $alto = 180;
        $margen = 20;
        $datos = collect($meses)->pluck('valor')->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v);
        if ($meta !== null) {
            $datos->push((float) $meta);
        }
        $min = $datos->isEmpty() ? 0 : min($datos->min(), 0);
        $max = $datos->isEmpty() ? 1 : $datos->max();
        $rango = ($max - $min) ?: 1;
        $y = fn (float $v) => round($alto - $margen - (($v - $min) / $rango) * ($alto - 2 * $margen), 1);

        $puntos = [];
        $segmentos = [];
        $actual = [];
        foreach ($meses as $i => $fila) {
            if ($fila['valor'] === null) {
                // Un mes sin dato corta la línea: unirlo inventaría una tendencia.
                $segmentos[] = $actual;
                $actual = [];
                continue;
            }
            $actual[] = $punto = [
                'x' => round($margen + $i * (($ancho - 2 * $margen) / 11), 1),
                'y' => $y((float) $fila['valor']),
                'etiqueta' => $fila['mes']->format('Y-m').': '.$fila['valor'],
            ];
            $puntos[] = $punto;
        }
        $segmentos[] = $actual;
        // Un punto aislado ya se pinta como círculo; solo los tramos de 2+ puntos llevan polilínea.
        $lineas = collect($segmentos)->filter(fn ($s) => count($s) > 1)
            ->map(fn ($s) => collect($s)->map(fn ($p) => $p['x'].','.$p['y'])->implode(' '))->values()->all();

        return [
            'ancho' => $ancho,
            'alto' => $alto,
            'puntos' => $puntos,
            'lineas' => $lineas,
            'meta_y' => $meta !== null ? $y((float) $meta) : null,
        ];
    }
}
