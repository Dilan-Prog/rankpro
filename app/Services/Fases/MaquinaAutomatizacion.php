<?php

namespace App\Services\Fases;

use App\Enums\FaseAutomatizacion;
use App\Models\AutomatizacionFaseDiagnostico;
use App\Models\AutomatizacionFaseDiseno;
use App\Models\AutomatizacionFaseImplementacion;
use App\Models\AutomatizacionProyecto;
use App\Models\AutomatizacionReporte;
use Illuminate\Database\Eloquent\Model;

class MaquinaAutomatizacion extends MaquinaFasesCiclica
{
    protected function sujeto(): string
    {
        return 'El proyecto';
    }

    protected function generoNeutro(): string
    {
        return 'o';
    }

    protected function faseInicial(): \BackedEnum
    {
        return FaseAutomatizacion::Diagnostico;
    }

    protected function faseCerrada(): \BackedEnum
    {
        return FaseAutomatizacion::Cerrada;
    }

    protected function faseReporte(): \BackedEnum
    {
        return FaseAutomatizacion::Reporte;
    }

    /** @param  AutomatizacionProyecto  $modelo */
    public function registro(Model $modelo, \BackedEnum $fase): Model
    {
        return match ($fase) {
            FaseAutomatizacion::Diagnostico => $modelo->faseDiagnostico ?? $modelo->diagnosticos()->create(['ciclo' => $modelo->ciclo_actual, 'checklist' => []]),
            FaseAutomatizacion::DisenoFlujo => $modelo->faseDiseno ?? $modelo->disenos()->create(['ciclo' => $modelo->ciclo_actual, 'checklist' => []]),
            FaseAutomatizacion::Implementacion => $modelo->faseImplementacion ?? $modelo->implementaciones()->create(['ciclo' => $modelo->ciclo_actual, 'checklist' => []]),
            FaseAutomatizacion::Reporte => $modelo->reporteActual ?? $modelo->reportes()->create(['ciclo' => $modelo->ciclo_actual, 'checklist' => []]),
            FaseAutomatizacion::Cerrada => throw new \InvalidArgumentException('El proyecto está cerrado.'),
        };
    }

    /** @param  AutomatizacionProyecto  $modelo */
    protected function registroSiExiste(Model $modelo, \BackedEnum $fase): ?Model
    {
        return match ($fase) {
            FaseAutomatizacion::Diagnostico => $modelo->faseDiagnostico,
            FaseAutomatizacion::DisenoFlujo => $modelo->faseDiseno,
            FaseAutomatizacion::Implementacion => $modelo->faseImplementacion,
            FaseAutomatizacion::Reporte => $modelo->reporteActual,
            FaseAutomatizacion::Cerrada => null,
        };
    }

    protected function checklistKeys(\BackedEnum $fase): array
    {
        return match ($fase) {
            FaseAutomatizacion::Diagnostico => AutomatizacionFaseDiagnostico::CHECKLIST,
            FaseAutomatizacion::DisenoFlujo => AutomatizacionFaseDiseno::CHECKLIST,
            FaseAutomatizacion::Implementacion => AutomatizacionFaseImplementacion::CHECKLIST,
            FaseAutomatizacion::Reporte => AutomatizacionReporte::CHECKLIST,
            FaseAutomatizacion::Cerrada => [],
        };
    }

    /** @param  AutomatizacionProyecto  $modelo */
    protected function crearCiclo(Model $modelo, int $ciclo): void
    {
        $modelo->diagnosticos()->create(['ciclo' => $ciclo, 'checklist' => []]);
        $modelo->disenos()->create(['ciclo' => $ciclo, 'checklist' => []]);
        $modelo->implementaciones()->create(['ciclo' => $ciclo, 'checklist' => []]);
        $modelo->reportes()->create(['ciclo' => $ciclo, 'checklist' => []]);
    }
}
