<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AcotaPorClienteActivo;
use App\Http\Controllers\Controller;
use App\Models\Keyword;
use App\Models\KeywordCluster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Clústeres de keywords del cliente activo y asignación de keywords del banco a cada uno. */
class SeoPlanClusterController extends Controller
{
    use AcotaPorClienteActivo;

    public function store(Request $request): RedirectResponse
    {
        $clienteId = $this->clienteActivoId();
        KeywordCluster::create($this->validar($request) + ['cliente_id' => $clienteId]);

        return redirect()->route('admin.seo-plan.index')->with('status', 'Clúster creado.');
    }

    public function update(Request $request, KeywordCluster $cluster): RedirectResponse
    {
        $this->clusterDelCliente($cluster)->update($this->validar($request));

        return redirect()->route('admin.seo-plan.index')->with('status', 'Clúster actualizado.');
    }

    /** Las keywords y actividades del clúster quedan sin clúster (FK nullOnDelete), no se borran. */
    public function destroy(KeywordCluster $cluster): RedirectResponse
    {
        $this->clusterDelCliente($cluster)->delete();

        return redirect()->route('admin.seo-plan.index')->with('status', 'Clúster eliminado.');
    }

    /** `cluster_id` vacío suelta la keyword. Solo keywords y clústeres del cliente activo. */
    public function asignarKeyword(Request $request, Keyword $keyword): JsonResponse
    {
        $clienteId = $this->clienteActivoId();
        abort_unless($keyword->cliente_id === $clienteId, 404);

        $clusterId = $request->input('cluster_id');
        $clusterId = ($clusterId === null || $clusterId === '') ? null : (int) $clusterId;
        if ($clusterId !== null) {
            $this->clusterDelCliente(KeywordCluster::findOrFail($clusterId));
        }

        $keyword->update(['cluster_id' => $clusterId]);

        return response()->json(['id' => $keyword->id, 'cluster_id' => $keyword->cluster_id]);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'url' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
