<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MetricaCliente;
use App\Support\Metricas\Catalogo;
use App\Support\Reglas\Metricas as ReglasMetricas;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD de las definiciones de métrica de un cliente. Desactivar conserva el
 * histórico; solo eliminar borra también sus valores (cascade).
 */
class MetricaClienteController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request) + $request->validate(['cliente_id' => ['required', 'integer', 'exists:clientes,id']]);
        $datos['orden'] = $datos['orden'] ?? ((int) MetricaCliente::where('cliente_id', $datos['cliente_id'])->max('orden') + 1);
        $datos['fuente'] ??= 'manual';

        MetricaCliente::create($datos);

        return $this->volver($request, $datos['cliente_id'], 'Métrica creada.');
    }

    /**
     * Crea las métricas elegidas del catálogo estándar con su unidad, sentido y fuente típica. Nunca lleva meta
     * (no hay benchmarks verificables: la fija el usuario) y salta las que el cliente ya tiene para no duplicarlas.
     */
    public function agregarDelCatalogo(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'claves' => ['required', 'array', 'min:1'],
            'claves.*' => ['string', Rule::in(Catalogo::claves())],
        ], ['claves.required' => 'Elige al menos una métrica del catálogo.']);

        $clienteId = (int) $datos['cliente_id'];
        $existentes = MetricaCliente::where('cliente_id', $clienteId)->whereNotNull('metrica_estandar')->pluck('metrica_estandar')->all();
        $orden = (int) MetricaCliente::where('cliente_id', $clienteId)->max('orden');
        $creadas = 0;
        $omitidas = 0;

        // Se recorre el catálogo (no la petición) para que el orden sea el del catálogo y se ignoren claves repetidas.
        foreach (Catalogo::todos() as $clave => $entrada) {
            if (! in_array($clave, $datos['claves'], true)) {
                continue;
            }
            if (in_array($clave, $existentes, true)) {
                $omitidas++;
                continue;
            }

            try {
                MetricaCliente::create([
                    'cliente_id' => $clienteId,
                    'nombre' => $entrada['nombre'],
                    'unidad' => $entrada['unidad'],
                    'meta' => null,
                    'sentido' => $entrada['sentido'],
                    'fuente' => $entrada['fuente_tipica'],
                    'metrica_estandar' => $clave,
                    'umbral_amarillo_pct' => 80,
                    'activa' => true,
                    'orden' => ++$orden,
                ]);
                $creadas++;
            } catch (QueryException $e) {
                // Doble clic o carrera: el índice único (cliente, metrica_estandar) manda y cuenta como ya existente.
                if ((string) $e->getCode() !== '23000') {
                    throw $e;
                }
                $omitidas++;
            }
        }

        $mensaje = "Se agregaron {$creadas} métrica(s) del catálogo; edita cada una para ponerle su meta.";
        if ($omitidas > 0) {
            $mensaje .= " {$omitidas} ya existía(n) y no se duplicó.";
        }

        return $this->volver($request, $clienteId, $mensaje);
    }

    public function update(Request $request, MetricaCliente $metrica): RedirectResponse
    {
        // El cliente no se reasigna: los valores ya capturados pertenecen a su historia.
        $datos = $this->validar($request);
        // "Orden" vacío significa "no cambiar": la columna es NOT NULL y un null daba 500.
        if (($datos['orden'] ?? null) === null) {
            unset($datos['orden']);
        }
        // Una fuente vacía vuelve a 'manual' en vez de dejar la columna en null.
        if (array_key_exists('fuente', $datos)) {
            $datos['fuente'] ??= 'manual';
        }
        $metrica->update($datos);

        return $this->volver($request, $metrica->cliente_id, 'Métrica actualizada.');
    }

    public function alternar(Request $request, MetricaCliente $metrica): RedirectResponse
    {
        $metrica->update(['activa' => ! $metrica->activa]);

        return $this->volver($request, $metrica->cliente_id, $metrica->activa ? 'Métrica activada.' : 'Métrica desactivada; su histórico se conserva.');
    }

    public function destroy(Request $request, MetricaCliente $metrica): RedirectResponse
    {
        $clienteId = $metrica->cliente_id;
        $metrica->delete();

        return $this->volver($request, $clienteId, 'Métrica eliminada junto con su histórico.');
    }

    private function validar(Request $request): array
    {
        $request->merge([
            'meta' => $request->filled('meta') ? str_replace(',', '.', str_replace(' ', '', (string) $request->input('meta'))) : null,
        ]);

        return $request->validate(ReglasMetricas::definicion());
    }

    private function volver(Request $request, int $clienteId, string $mensaje): RedirectResponse
    {
        return redirect()
            ->route('admin.metricas.index', array_filter(['cliente' => $clienteId, 'mes' => $request->input('mes')]))
            ->with('status', $mensaje);
    }
}
