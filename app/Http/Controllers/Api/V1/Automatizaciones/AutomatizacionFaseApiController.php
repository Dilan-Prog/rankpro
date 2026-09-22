<?php

namespace App\Http\Controllers\Api\V1\Automatizaciones;

use App\Enums\FaseAutomatizacion;
use App\Models\AutomatizacionProyecto;
use App\Services\Fases\MaquinaAutomatizacion;
use App\Support\Api\Respuesta;
use Illuminate\Http\Request;

/**
 * Replica el match por fase de
 * App\Http\Controllers\Admin\AutomatizacionFaseController::guardar() — esa
 * parte no tiene servicio compartido (ver api_contrato.md). El resto delega
 * en MaquinaAutomatizacion.
 */
class AutomatizacionFaseApiController
{
    public function __construct(private MaquinaAutomatizacion $maquina)
    {
    }

    public function fase(AutomatizacionProyecto $proyecto)
    {
        return Respuesta::recurso($this->maquina->estado($proyecto));
    }

    public function guardar(Request $request, AutomatizacionProyecto $proyecto)
    {
        $fase = $proyecto->fase_actual;

        if ($fase === FaseAutomatizacion::Cerrada) {
            return Respuesta::mensaje('Este proyecto está cerrado.', 422);
        }

        $data = match ($fase) {
            FaseAutomatizacion::Diagnostico => $request->validate([
                'objetivo_cliente' => ['nullable', 'string', 'max:5000'],
                'procesos_actuales' => ['nullable', 'string', 'max:5000'],
                'herramientas_actuales' => ['nullable', 'string', 'max:255'],
                'volumen_mensual_estimado' => ['nullable', 'integer', 'min:0'],
                'viable' => ['nullable', 'boolean'],
                'notas' => ['nullable', 'string', 'max:2000'],
                'checklist' => ['nullable', 'array'],
                'checklist.*' => ['boolean'],
            ]),
            FaseAutomatizacion::DisenoFlujo => $request->validate([
                'flujos_planeados' => ['nullable', 'string', 'max:5000'],
                'integraciones_planeadas' => ['nullable', 'string', 'max:255'],
                'diagrama_url' => ['nullable', 'string', 'max:255'],
                'cronograma' => ['nullable', 'string', 'max:5000'],
                'notas' => ['nullable', 'string', 'max:2000'],
                'checklist' => ['nullable', 'array'],
                'checklist.*' => ['boolean'],
            ]),
            FaseAutomatizacion::Implementacion => $request->validate([
                'porcentaje_avance' => ['nullable', 'integer', 'min:0', 'max:100'],
                'flujos_construidos' => ['nullable', 'integer', 'min:0'],
                'pruebas_realizadas' => ['nullable', 'boolean'],
                'cliente_capacitado' => ['nullable', 'boolean'],
                'notas' => ['nullable', 'string', 'max:2000'],
                'checklist' => ['nullable', 'array'],
                'checklist.*' => ['boolean'],
            ]),
            FaseAutomatizacion::Reporte => $request->validate([
                'flujos_activos_total' => ['nullable', 'integer', 'min:0'],
                'horas_ahorradas_mes' => ['nullable', 'numeric', 'min:0'],
                'mensajes_gestionados_mes' => ['nullable', 'integer', 'min:0'],
                'tareas_automatizadas_mes' => ['nullable', 'integer', 'min:0'],
                'incidencias' => ['nullable', 'string', 'max:5000'],
                'conclusiones' => ['nullable', 'string', 'max:5000'],
                'recomendaciones' => ['nullable', 'string', 'max:5000'],
                'satisfaccion_cliente' => ['nullable', 'integer', 'min:1', 'max:5'],
                'continua_proyecto' => ['nullable', 'boolean'],
                'notas_cierre' => ['nullable', 'string', 'max:2000'],
                'checklist' => ['nullable', 'array'],
                'checklist.*' => ['boolean'],
            ]),
        };

        $registro = $this->maquina->registro($proyecto, $fase);

        if (array_key_exists('checklist', $data)) {
            $data['checklist'] = $this->maquina->fusionarChecklist($registro, $fase, $data['checklist']);
        }

        foreach ($this->booleanFields($fase) as $field) {
            $data[$field] = $request->boolean($field);
        }

        $registro->update($data);

        return Respuesta::recurso([
            'checklist' => $registro->fresh()->checklist,
            'completo' => $this->maquina->checklistCompleto($registro->fresh(), $fase),
        ]);
    }

    public function aprobar(AutomatizacionProyecto $proyecto)
    {
        return Respuesta::recurso($this->maquina->aprobar($proyecto)->toArray());
    }

    public function retroceder(AutomatizacionProyecto $proyecto)
    {
        return Respuesta::recurso($this->maquina->retroceder($proyecto)->toArray());
    }

    public function nuevoCiclo(AutomatizacionProyecto $proyecto)
    {
        return Respuesta::recurso($this->maquina->nuevoCiclo($proyecto)->toArray());
    }

    public function cerrar(AutomatizacionProyecto $proyecto)
    {
        return Respuesta::recurso($this->maquina->cerrar($proyecto)->toArray());
    }

    public function pausar(AutomatizacionProyecto $proyecto)
    {
        return Respuesta::recurso($this->maquina->pausar($proyecto)->toArray());
    }

    private function booleanFields(FaseAutomatizacion $fase): array
    {
        return match ($fase) {
            FaseAutomatizacion::Diagnostico => ['viable'],
            FaseAutomatizacion::Implementacion => ['pruebas_realizadas', 'cliente_capacitado'],
            FaseAutomatizacion::Reporte => ['continua_proyecto'],
            default => [],
        };
    }
}
