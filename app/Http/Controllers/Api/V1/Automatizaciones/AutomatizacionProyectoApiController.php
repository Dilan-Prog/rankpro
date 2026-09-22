<?php

namespace App\Http\Controllers\Api\V1\Automatizaciones;

use App\Enums\FaseAutomatizacion;
use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\AutomatizacionProyecto;
use App\Support\Api\{ConsultaOpciones, Respuesta, Serializador};
use App\Support\Reglas\Automatizaciones as ReglasAutomatizaciones;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AutomatizacionController (web) devuelve redirects, no JSON — este
 * controlador reimplementa store/update/destroy en JSON usando las mismas
 * reglas de validación (App\Support\Reglas\Automatizaciones), ver
 * api_contrato.md.
 */
class AutomatizacionProyectoApiController extends ControladorApi
{
    public function index(Request $request)
    {
        return $this->listar(AutomatizacionProyecto::query(), $request, new ConsultaOpciones(
            buscarEn: ['nombre'],
            filtrosExactos: ['cliente_id', 'servicio_id', 'estado', 'fase_actual'],
            ordenables: ['id', 'nombre', 'created_at', 'updated_at'],
            incluibles: ['cliente', 'servicio', 'flujos', 'faseDiagnostico', 'faseDiseno', 'faseImplementacion', 'reporteActual'],
        ));
    }

    public function show(Request $request, AutomatizacionProyecto $proyecto)
    {
        $incluibles = ['cliente', 'servicio', 'flujos', 'faseDiagnostico', 'faseDiseno', 'faseImplementacion', 'reporteActual'];
        $incluir = array_values(array_intersect(
            array_filter(explode(',', (string) $request->string('incluir'))),
            $incluibles
        ));

        if ($incluir) {
            $proyecto->load($incluir);
        }

        return Respuesta::recurso(Serializador::modelo($proyecto, $incluir));
    }

    public function store(Request $request)
    {
        $data = $request->validate(ReglasAutomatizaciones::crear());
        $data['viable'] = $request->boolean('viable');

        $proyecto = DB::transaction(function () use ($data) {
            $proyecto = AutomatizacionProyecto::create([
                'cliente_id' => $data['cliente_id'],
                'servicio_id' => $data['servicio_id'],
                'nombre' => $data['nombre'],
                'estado' => 'activa',
                'fase_actual' => FaseAutomatizacion::Diagnostico->value,
                'ciclo_actual' => 1,
                'fecha_inicio' => $data['fecha_inicio'] ?? null,
            ]);

            ReglasAutomatizaciones::guardar($proyecto, $data);

            return $proyecto;
        });

        return Respuesta::recurso(Serializador::modelo($proyecto->fresh()), 201);
    }

    public function update(Request $request, AutomatizacionProyecto $proyecto)
    {
        $data = $request->validate(ReglasAutomatizaciones::actualizar());

        $proyecto->update($data);

        return Respuesta::recurso(Serializador::modelo($proyecto->fresh()));
    }

    public function destroy(AutomatizacionProyecto $proyecto)
    {
        $proyecto->delete();

        return Respuesta::eliminado();
    }
}
