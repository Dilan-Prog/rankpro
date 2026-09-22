<?php

namespace App\Support\Reglas;

use App\Models\AutomatizacionProyecto;

/**
 * Reglas de validación de AutomatizacionProyecto. El controlador web
 * (App\Http\Controllers\Admin\AutomatizacionController) sigue devolviendo
 * redirects y valida inline; el de la API (Api\V1\Automatizaciones\
 * AutomatizacionProyectoApiController) reimplementa store/update en JSON
 * usando estas mismas reglas para no duplicar los arrays de validación.
 */
class Automatizaciones
{
    /** Reglas de store() del proyecto (incluye el diagnóstico inicial, igual que el formulario web de creación). */
    public static function crear(): array
    {
        return [
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'servicio_id' => ['required', 'integer', 'exists:servicios,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'fecha_inicio' => ['nullable', 'date'],

            'objetivo_cliente' => ['nullable', 'string', 'max:5000'],
            'procesos_actuales' => ['nullable', 'string', 'max:5000'],
            'herramientas_actuales' => ['nullable', 'string', 'max:255'],
            'volumen_mensual_estimado' => ['nullable', 'integer', 'min:0'],
            'viable' => ['nullable', 'boolean'],
            'notas' => ['nullable', 'string', 'max:2000'],

            'checklist' => ['nullable', 'array'],
            'checklist.*' => ['boolean'],
        ];
    }

    public static function actualizar(): array
    {
        return [
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'servicio_id' => ['required', 'integer', 'exists:servicios,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'estado' => ['required', 'in:activa,pausada,finalizada'],
            'fecha_inicio' => ['nullable', 'date'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @param  array<string, mixed>  $data  ya validado con crear() */
    public static function guardar(AutomatizacionProyecto $proyecto, array $data): void
    {
        $checklistKeys = array_keys(\App\Models\AutomatizacionFaseDiagnostico::CHECKLIST);
        $checklist = collect($checklistKeys)->mapWithKeys(fn ($key) => [$key => (bool) ($data['checklist'][$key] ?? false)])->all();

        $proyecto->diagnosticos()->create([
            'ciclo' => 1,
            'objetivo_cliente' => $data['objetivo_cliente'] ?? null,
            'procesos_actuales' => $data['procesos_actuales'] ?? null,
            'herramientas_actuales' => $data['herramientas_actuales'] ?? null,
            'volumen_mensual_estimado' => $data['volumen_mensual_estimado'] ?? null,
            'viable' => (bool) ($data['viable'] ?? false),
            'notas' => $data['notas'] ?? null,
            'checklist' => $checklist,
        ]);

        $proyecto->disenos()->create(['ciclo' => 1, 'checklist' => []]);
        $proyecto->implementaciones()->create(['ciclo' => 1, 'checklist' => []]);
        $proyecto->reportes()->create(['ciclo' => 1, 'checklist' => []]);
    }
}
