<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AcotaPorClienteActivo;
use App\Http\Controllers\Controller;
use App\Models\SeoActividadNota;
use App\Models\SeoOnPageAccion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Bitácora de una actividad del SEO Plan: se lee al abrir el detalle y se agrega de una en una. */
class SeoPlanNotaController extends Controller
{
    use AcotaPorClienteActivo;

    public function index(SeoOnPageAccion $accion): JsonResponse
    {
        $this->actividadDelCliente($accion);

        return response()->json($accion->notas()->with('usuario:id,name')->get()->map(fn ($n) => $this->fila($n)));
    }

    public function store(Request $request, SeoOnPageAccion $accion): JsonResponse
    {
        $this->actividadDelCliente($accion);
        $datos = $request->validate(['nota' => ['required', 'string', 'max:2000']]);

        $nota = $accion->notas()->create($datos + ['user_id' => auth()->id()]);

        return response()->json($this->fila($nota->load('usuario:id,name')), 201);
    }

    public function destroy(SeoOnPageAccion $accion, SeoActividadNota $nota): JsonResponse
    {
        $this->actividadDelCliente($accion);
        abort_unless($nota->seo_onpage_accion_id === $accion->id, 404);

        $nota->delete();

        return response()->json(['deleted' => true]);
    }

    private function fila(SeoActividadNota $nota): array
    {
        return [
            'id' => $nota->id,
            'nota' => $nota->nota,
            'autor' => $nota->usuario?->name,
            'fecha' => $nota->created_at?->format('d/m/Y H:i'),
        ];
    }
}
