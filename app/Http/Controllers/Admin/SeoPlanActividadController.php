<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoOnPageAccion;
use App\Enums\TipoActividadSeo;
use App\Http\Controllers\Admin\Concerns\AcotaPorClienteActivo;
use App\Http\Controllers\Controller;
use App\Models\KeywordCluster;
use App\Models\SeoCampana;
use App\Models\SeoOnPageAccion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Actividades del SEO Plan (= SeoOnPageAccion). La campaña SEO es el vínculo
 * con el cliente: no se creó una columna `cliente_id` paralela para no tener
 * dos verdades sobre de quién es una acción.
 */
class SeoPlanActividadController extends Controller
{
    use AcotaPorClienteActivo;

    public function store(Request $request): RedirectResponse
    {
        $clienteId = $this->clienteActivoId();
        $datos = $this->validar($request, $clienteId, true);

        $datos['url_pagina'] = $datos['url_pagina'] ?? '';
        $datos['estado'] = $datos['estado'] ?? EstadoOnPageAccion::Pendiente->value;
        $datos['fecha'] = now()->toDateString();
        // Al final de su columna.
        $datos['orden'] = (int) SeoOnPageAccion::query()
            ->whereHas('seoCampana', fn ($q) => $q->withTrashed()->where('cliente_id', $clienteId))
            ->where('estado', $datos['estado'])->max('orden') + 1;

        SeoOnPageAccion::create($datos);

        return redirect()->route('admin.seo-plan.index')->with('status', 'Actividad creada.');
    }

    public function update(Request $request, SeoOnPageAccion $accion): RedirectResponse
    {
        $this->actividadDelCliente($accion);
        $datos = $this->validar($request, $this->clienteActivoId(), false);
        $datos['url_pagina'] = $datos['url_pagina'] ?? '';

        $accion->update($datos);

        return redirect()->route('admin.seo-plan.index')->with('status', 'Actividad actualizada.');
    }

    public function destroy(SeoOnPageAccion $accion): RedirectResponse
    {
        $this->actividadDelCliente($accion)->delete();

        return redirect()->route('admin.seo-plan.index')->with('status', 'Actividad eliminada.');
    }

    /**
     * Kanban: cambia de columna y fija la posición dentro de ella. Se reescribe
     * el orden de la columna destino completa para que sea estable y sin huecos.
     */
    public function mover(Request $request, SeoOnPageAccion $accion): JsonResponse
    {
        $this->actividadDelCliente($accion);
        $clienteId = $this->clienteActivoId();
        $datos = $request->validate([
            'estado' => ['required', Rule::in(array_column(EstadoOnPageAccion::cases(), 'value'))],
            'posicion' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($accion, $datos, $clienteId) {
            $accion->estado = $datos['estado'];
            $accion->save();

            $hermanas = SeoOnPageAccion::query()
                ->whereHas('seoCampana', fn ($q) => $q->withTrashed()->where('cliente_id', $clienteId))
                ->where('estado', $datos['estado'])->where('id', '!=', $accion->id)
                ->orderBy('orden')->orderBy('id')->pluck('id')->all();

            array_splice($hermanas, min($datos['posicion'] ?? count($hermanas), count($hermanas)), 0, [$accion->id]);

            foreach ($hermanas as $i => $id) {
                SeoOnPageAccion::query()->whereKey($id)->update(['orden' => $i + 1]);
            }
        });

        $accion->refresh();

        return response()->json([
            'id' => $accion->id,
            'estado' => $accion->estado->value,
            'cerrada_en' => $accion->cerrada_en?->toDateString(),
        ]);
    }

    private function validar(Request $request, int $clienteId, bool $creando): array
    {
        $campanasValidas = SeoCampana::query()->where('cliente_id', $clienteId)->pluck('id')->all();
        $clustersValidos = KeywordCluster::query()->where('cliente_id', $clienteId)->pluck('id')->all();

        return $request->validate([
            'seo_campana_id' => [$creando ? 'required' : 'prohibited', 'integer', Rule::in($campanasValidas)],
            'accion' => ['required', 'string', 'max:2000'],
            'tipo' => ['required', Rule::in(array_column(TipoActividadSeo::cases(), 'value'))],
            'cluster_id' => ['nullable', 'integer', Rule::in($clustersValidos)],
            'url_pagina' => ['nullable', 'string', 'max:255'],
            'fecha_limite' => ['nullable', 'date'],
            'responsable_id' => ['nullable', 'integer', 'exists:users,id'],
            'estado' => [$creando ? 'nullable' : 'required', Rule::in(array_column(EstadoOnPageAccion::cases(), 'value'))],
            'medicion_antes' => ['nullable', 'string', 'max:120'],
            'medicion_despues' => ['nullable', 'string', 'max:120'],
        ]);
    }
}
