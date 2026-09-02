<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AreaReporte;
use App\Enums\EstadoReporte;
use App\Enums\TipoSeccion;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Reporte;
use App\Support\Reportes\Plantillas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportesController extends Controller
{
    public function index(): View
    {
        $reportes = Reporte::with('cliente')
            ->withCount('entregas')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Reporte $reporte) => $reporte->toRow());

        return view('admin.reportes.index', [
            'pageTitle' => 'Reportes',
            'reportes' => $reportes,
            'clientes' => Cliente::orderBy('nombre')->get(['id', 'nombre', 'empresa']),
            'totalReportes' => $reportes->count(),
            'borradores' => $reportes->where('estado', EstadoReporte::Borrador->value)->count(),
            // `fecha_emision` se sella al pasar el reporte a Listo y ya no se
            // vuelve a mover: cualquier edición posterior (una nota, un cambio
            // de sección) tocaba `updated_at` y sacaba o metía el reporte del
            // recuento del mes según el día en que se le hubiera dado un
            // retoque.
            'entregadosMes' => Reporte::where('estado', EstadoReporte::Entregado->value)
                ->whereBetween('fecha_emision', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, forUpdate: false);

        $reporte = DB::transaction(function () use ($data) {
            $reporte = Reporte::create($data + [
                'estado' => EstadoReporte::Borrador->value,
                'creado_por' => Auth::id(),
            ]);

            // Un reporte nace con la estructura de su área ya puesta (ver
            // Plantillas): el equipo abre el detalle y solo pega y redacta,
            // en vez de tener que montar secciones y columnas a mano.
            foreach (Plantillas::para($reporte->area) as $i => $seccion) {
                $reporte->secciones()->create([
                    'tipo' => $seccion['tipo']->value,
                    'titulo' => $seccion['titulo'],
                    'orden' => $i + 1,
                    'contenido' => $seccion['contenido'],
                    'visible' => true,
                ]);
            }

            return $reporte;
        });

        return response()->json([
            'show_url' => route('admin.reportes.show', $reporte),
            'reporte' => $reporte->fresh('cliente')->toRow(),
        ], 201);
    }

    public function show(Reporte $reporte): View
    {
        return view('admin.reportes.show', [
            'pageTitle' => $reporte->titulo,
            // `entregas.usuario` va en el load porque la tabla del histórico
            // pinta quién generó cada entrega: sin precargarlo son N consultas.
            'reporte' => $reporte->load('cliente', 'secciones', 'entregas.archivo', 'entregas.usuario', 'creador'),
            // Menú "añadir sección": los tipos que admiten pegado se marcan
            // aquí para que la vista no tenga que conocer el enum.
            'tiposSeccion' => collect(TipoSeccion::cases())->map(fn (TipoSeccion $tipo) => [
                'value' => $tipo->value,
                'label' => $tipo->label(),
                'admite_pegado' => $tipo->admitePegado(),
            ])->values(),
        ]);
    }

    public function update(Request $request, Reporte $reporte): JsonResponse
    {
        $data = $this->validated($request, forUpdate: true);

        $reporte->update($data);

        return response()->json($reporte->fresh('cliente')->loadCount('entregas')->toRow());
    }

    public function destroy(Reporte $reporte): JsonResponse
    {
        $reporte->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * `cliente_id` y `area` son inmutables: el área decide la plantilla de
     * secciones que ya se sembró, y cambiarla dejaría el reporte con secciones
     * que no le corresponden.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $forUpdate): array
    {
        $reglas = [
            'titulo' => ['required', 'string', 'max:255'],
            'periodo_inicio' => ['required', 'date'],
            'periodo_fin' => ['required', 'date', 'after_or_equal:periodo_inicio'],
            'notas_alcance' => ['nullable', 'string', 'max:5000'],
            'seo_campana_id' => ['nullable', 'integer', 'exists:seo_campanas,id'],
            'ads_campana_id' => ['nullable', 'integer', 'exists:ads_campanas,id'],
        ];

        $reglas += $forUpdate
            ? ['estado' => ['required', 'in:'.implode(',', array_column(EstadoReporte::cases(), 'value'))]]
            : [
                'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
                'area' => ['required', 'in:'.implode(',', array_column(AreaReporte::cases(), 'value'))],
            ];

        return $request->validate($reglas);
    }
}
