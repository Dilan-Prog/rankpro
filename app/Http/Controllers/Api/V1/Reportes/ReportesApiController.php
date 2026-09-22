<?php

namespace App\Http\Controllers\Api\V1\Reportes;

use App\Enums\AreaReporte;
use App\Enums\EstadoReporte;
use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\Reporte;
use App\Support\Api\ConsultaOpciones;
use App\Support\Api\Respuesta;
use App\Support\Api\Serializador;
use App\Support\Reportes\Plantillas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * CRUD de /api/v1/reportes. Misma forma de datos y mismas reglas que
 * App\Http\Controllers\Admin\ReportesController (no se extrajeron a
 * App\Support\Reglas para no tocar el controlador web en este agente; si
 * cambia una regla ahí hay que replicarla aquí).
 */
class ReportesApiController extends ControladorApi
{
    public function index(Request $request): JsonResponse
    {
        return $this->listar(Reporte::query(), $request, new ConsultaOpciones(
            buscarEn: ['titulo', 'numero'],
            filtrosExactos: ['cliente_id', 'area', 'estado', 'seo_campana_id', 'ads_campana_id'],
            ordenables: ['id', 'titulo', 'numero', 'periodo_inicio', 'periodo_fin', 'created_at', 'updated_at'],
            incluibles: ['cliente', 'secciones', 'entregas'],
        ));
    }

    /** `?incluir=secciones,entregas` (o `cliente`, `creador`) — separado por comas. */
    public function show(Request $request, Reporte $reporte): JsonResponse
    {
        $incluir = array_values(array_intersect(
            array_filter(explode(',', (string) $request->string('incluir'))),
            ['cliente', 'secciones', 'entregas', 'creador']
        ));

        if ($incluir !== []) {
            $reporte->load($incluir);
        }

        return Respuesta::recurso(Serializador::modelo($reporte, $incluir));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, forUpdate: false);

        $reporte = DB::transaction(function () use ($data) {
            $reporte = Reporte::create($data + [
                'estado' => EstadoReporte::Borrador->value,
                'creado_por' => Auth::id(),
            ]);

            // La estructura de secciones sale de la plantilla del área, igual
            // que en el alta desde el panel (ver Admin\ReportesController::store).
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

        return Respuesta::recurso(Serializador::modelo($reporte->fresh('cliente')), 201);
    }

    public function update(Request $request, Reporte $reporte): JsonResponse
    {
        $data = $this->validated($request, forUpdate: true);

        $reporte->update($data);

        return Respuesta::recurso(Serializador::modelo($reporte->fresh('cliente')->loadCount('entregas')));
    }

    public function destroy(Reporte $reporte): JsonResponse
    {
        $reporte->delete();

        return Respuesta::eliminado();
    }

    /** @return array<string, mixed> */
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
