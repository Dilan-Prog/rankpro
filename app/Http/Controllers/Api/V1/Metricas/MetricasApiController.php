<?php

namespace App\Http\Controllers\Api\V1\Metricas;

use App\Enums\SentidoMetrica;
use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\Cliente;
use App\Models\MetricaCliente;
use App\Models\MetricaClienteValor;
use App\Support\Api\ConsultaOpciones;
use App\Support\Api\Respuesta;
use App\Support\Api\Serializador;
use App\Support\Metricas\GuardadorValores;
use App\Support\Metricas\Semaforo;
use App\Support\Reglas\Metricas as ReglasMetricas;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Definiciones de métrica y valores mensuales de /api/v1/metricas. Las reglas y
 * el guardado son los del módulo web (App\Support\Reglas\Metricas y
 * GuardadorValores). Las escrituras de valores son upserts por (métrica, mes):
 * n8n puede reintentar sin duplicar.
 */
class MetricasApiController extends ControladorApi
{
    private const MAX_MESES = 60;
    private const MAX_LOTE = 200;

    public function index(Request $request): JsonResponse
    {
        $query = MetricaCliente::query();
        if ($request->filled('activa')) {
            $query->where('activa', filter_var($request->input('activa'), FILTER_VALIDATE_BOOLEAN));
        }

        return $this->listar($query, $request, new ConsultaOpciones(
            buscarEn: ['nombre'],
            filtrosExactos: ['cliente_id'],
            ordenables: ['id', 'nombre', 'orden', 'created_at', 'updated_at'],
            ordenPorDefecto: 'orden',
        ));
    }

    public function show(MetricaCliente $metrica): JsonResponse
    {
        return Respuesta::recurso(Serializador::modelo($metrica));
    }

    public function store(Request $request): JsonResponse
    {
        $this->normalizarMeta($request);
        $data = $request->validate(
            ['cliente_id' => ['required', 'integer', 'exists:clientes,id'], 'activa' => ['sometimes', 'boolean']]
            + $this->reglasDefinicion(parcial: false)
        );
        $data['sentido'] ??= SentidoMetrica::Mayor->value;
        $data['umbral_amarillo_pct'] ??= 80;
        $data['orden'] ??= (int) MetricaCliente::where('cliente_id', $data['cliente_id'])->max('orden') + 1;

        return Respuesta::recurso(Serializador::modelo(MetricaCliente::create($data)->fresh()), 201);
    }

    public function update(Request $request, MetricaCliente $metrica): JsonResponse
    {
        // El cliente no se reasigna: los valores ya capturados pertenecen a su historia.
        $this->normalizarMeta($request);
        $data = $request->validate(['activa' => ['sometimes', 'boolean']] + $this->reglasDefinicion(parcial: true));
        // `orden: null` se ignora: la columna es NOT NULL y un null daba 500.
        if (array_key_exists('orden', $data) && $data['orden'] === null) {
            unset($data['orden']);
        }
        $metrica->update($data);

        return Respuesta::recurso(Serializador::modelo($metrica->fresh()));
    }

    public function destroy(MetricaCliente $metrica): JsonResponse
    {
        $metrica->delete();

        return Respuesta::eliminado();
    }

    /** Valores con semáforo; por defecto los últimos 12 meses. Solo los meses con dato. */
    public function valores(Request $request, MetricaCliente $metrica): JsonResponse
    {
        $request->validate([
            'desde' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'hasta' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        $hasta = ReglasMetricas::periodo($request->input('hasta')) ?? now()->startOfMonth();
        $desde = ReglasMetricas::periodo($request->input('desde')) ?? $hasta->copy()->subMonthsNoOverflow(11);

        if ($desde->gt($hasta)) {
            throw ValidationException::withMessages(['desde' => ['"desde" no puede ser posterior a "hasta".']]);
        }
        if ($desde->diffInMonths($hasta) >= self::MAX_MESES) {
            throw ValidationException::withMessages(['desde' => ['El rango máximo es de '.self::MAX_MESES.' meses.']]);
        }

        $valores = $metrica->valores()
            ->whereDate('periodo', '>=', $desde)
            ->whereDate('periodo', '<=', $hasta)
            ->orderBy('periodo')
            ->get()
            ->map(fn (MetricaClienteValor $v) => $this->serializarValor($metrica, $v->periodo, $v))
            ->values()
            ->all();

        return Respuesta::recurso($valores, 200, [
            'metrica' => $this->resumenMetrica($metrica),
            'desde' => $desde->format('Y-m'),
            'hasta' => $hasta->format('Y-m'),
        ]);
    }

    /** Upsert de un mes. `valor` null/"" borra el valor existente; 0 se guarda. */
    public function guardarValor(Request $request, MetricaCliente $metrica, string $periodo): JsonResponse
    {
        $mes = ReglasMetricas::periodo($periodo);
        if (! $mes) {
            throw ValidationException::withMessages(['periodo' => ['El periodo debe ser YYYY-MM.']]);
        }

        if ($request->has('valor')) {
            $request->merge(['valor' => ReglasMetricas::normalizarDecimal($request->input('valor'))]);
        }
        $data = $request->validate([
            'valor' => array_merge(['present'], ReglasMetricas::valor()),
            'nota' => ['nullable', 'string', 'max:2000'],
        ]);

        $registro = GuardadorValores::guardar(
            $metrica->id, $mes, $data['valor'], $data['nota'] ?? null, $request->user()?->id,
            conservarNota: ! $request->has('nota'),
        );

        return Respuesta::recurso($this->serializarValor($metrica, $mes, $registro));
    }

    /**
     * Lote para n8n: todas las cifras de un cliente en un mes, atómico. Cada
     * entrada identifica la métrica por `metrica_id` o por `nombre`; si alguna no
     * resuelve se rechaza TODO el lote con el índice y el motivo.
     */
    public function lote(Request $request, Cliente $cliente): JsonResponse
    {
        $entradas = $request->input('valores');
        if (is_array($entradas)) {
            $request->merge(['valores' => array_map(function ($e) {
                if (is_array($e) && array_key_exists('valor', $e)) {
                    $e['valor'] = ReglasMetricas::normalizarDecimal($e['valor']);
                }

                return $e;
            }, $entradas)]);
        }

        $data = $request->validate([
            'periodo' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'valores' => ['required', 'array', 'min:1', 'max:'.self::MAX_LOTE],
            'valores.*' => ['array'],
            'valores.*.metrica_id' => ['nullable', 'integer', 'required_without:valores.*.nombre'],
            'valores.*.nombre' => ['nullable', 'string', 'max:120', 'required_without:valores.*.metrica_id'],
            'valores.*.valor' => array_merge(['present'], ReglasMetricas::valor()),
            'valores.*.nota' => ['nullable', 'string', 'max:2000'],
        ]);

        // validate() arma el arreglo en el orden de las reglas, no el del cuerpo: se restaura para que el índice del error y la respuesta coincidan.
        ksort($data['valores']);

        $mes = ReglasMetricas::periodo($data['periodo']);
        $metricas = MetricaCliente::where('cliente_id', $cliente->id)->get();
        $resueltas = $this->resolverEntradas($data['valores'], $metricas);

        $guardados = DB::transaction(function () use ($data, $resueltas, $mes, $request) {
            $salida = [];
            foreach ($data['valores'] as $i => $entrada) {
                $metrica = $resueltas[$i];
                $registro = GuardadorValores::guardar(
                    $metrica->id, $mes, $entrada['valor'], $entrada['nota'] ?? null, $request->user()?->id,
                    conservarNota: ! array_key_exists('nota', $entrada),
                );
                $salida[] = $this->serializarValor($metrica, $mes, $registro);
            }

            return $salida;
        });

        return Respuesta::recurso($guardados, 200, ['cliente_id' => $cliente->id, 'periodo' => $mes->format('Y-m'), 'total' => count($guardados)]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $entradas
     * @param  Collection<int, MetricaCliente>  $metricas
     * @return array<int, MetricaCliente>
     */
    private function resolverEntradas(array $entradas, Collection $metricas): array
    {
        $porId = $metricas->keyBy('id');
        $porNombre = $metricas->groupBy(fn ($m) => mb_strtolower(trim($m->nombre)));
        $resueltas = [];
        $errores = [];
        $vistas = [];

        foreach ($entradas as $i => $entrada) {
            $clave = "valores.{$i}";

            $id = $entrada['metrica_id'] ?? null;
            if ($id !== null && $id !== '') {
                $metrica = $porId->get((int) $id);
                if (! $metrica) {
                    $errores[$clave] = ["La métrica {$id} no existe en este cliente."];
                    continue;
                }
            } else {
                $nombre = is_string($entrada['nombre'] ?? null) ? trim($entrada['nombre']) : '';
                if ($nombre === '') {
                    $errores[$clave] = ['Indica metrica_id o nombre.'];
                    continue;
                }
                $candidatas = $porNombre->get(mb_strtolower($nombre), collect());
                if ($candidatas->isEmpty()) {
                    $errores[$clave] = ["No hay una métrica llamada \"{$nombre}\" en este cliente."];
                    continue;
                }
                if ($candidatas->count() > 1) {
                    $errores[$clave] = ["El nombre \"{$nombre}\" coincide con varias métricas; usa metrica_id."];
                    continue;
                }
                $metrica = $candidatas->first();
            }

            if (isset($vistas[$metrica->id])) {
                $errores[$clave] = ["La métrica {$metrica->id} ya viene en la entrada {$vistas[$metrica->id]} del lote."];
                continue;
            }
            $vistas[$metrica->id] = $i;
            $resueltas[$i] = $metrica;
        }

        if ($errores) {
            throw ValidationException::withMessages($errores);
        }

        return $resueltas;
    }

    /** Misma tolerancia a la coma decimal que el formulario web. */
    private function normalizarMeta(Request $request): void
    {
        if ($request->has('meta')) {
            $request->merge(['meta' => ReglasMetricas::normalizarDecimal($request->input('meta'))]);
        }
    }

    /** sentido/umbral tienen default en store y todo es opcional en update (cambio parcial). */
    private function reglasDefinicion(bool $parcial): array
    {
        $reglas = ReglasMetricas::definicion();
        foreach ($reglas as $campo => $r) {
            if (in_array($campo, ['sentido', 'umbral_amarillo_pct'], true) || ($parcial && $campo === 'nombre')) {
                $reglas[$campo] = array_map(fn ($x) => $x === 'required' ? 'sometimes' : $x, $r);
            }
        }

        return $reglas;
    }

    /** @return array<string, mixed> */
    private function resumenMetrica(MetricaCliente $metrica): array
    {
        return [
            'id' => $metrica->id,
            'cliente_id' => $metrica->cliente_id,
            'nombre' => $metrica->nombre,
            'unidad' => $metrica->unidad,
            'meta' => $metrica->meta,
            'sentido' => $metrica->sentido->value,
            'umbral_amarillo_pct' => $metrica->umbral_amarillo_pct,
        ];
    }

    /** @return array<string, mixed> */
    private function serializarValor(MetricaCliente $metrica, Carbon $periodo, ?MetricaClienteValor $v): array
    {
        $valor = $v?->valor;

        return [
            'metrica_id' => $metrica->id,
            'nombre' => $metrica->nombre,
            'periodo' => $periodo->format('Y-m'),
            'valor' => $valor,
            'nota' => $v?->nota,
            'meta' => $metrica->meta,
            'semaforo' => Semaforo::evaluar($valor, $metrica->meta, $metrica->sentido, $metrica->umbral_amarillo_pct),
            // 'borrado' = llegó vacío y se eliminó el valor existente (si lo había).
            'accion' => $v ? 'guardado' : 'borrado',
        ];
    }
}
