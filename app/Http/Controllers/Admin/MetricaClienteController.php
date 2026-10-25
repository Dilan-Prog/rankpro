<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MetricaCliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\Reglas\Metricas as ReglasMetricas;

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

        MetricaCliente::create($datos);

        return $this->volver($request, $datos['cliente_id'], 'Métrica creada.');
    }

    public function update(Request $request, MetricaCliente $metrica): RedirectResponse
    {
        // El cliente no se reasigna: los valores ya capturados pertenecen a su historia.
        $datos = $this->validar($request);
        // "Orden" vacío significa "no cambiar": la columna es NOT NULL y un null daba 500.
        if (($datos['orden'] ?? null) === null) {
            unset($datos['orden']);
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
