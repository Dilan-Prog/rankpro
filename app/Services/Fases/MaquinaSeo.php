<?php

namespace App\Services\Fases;

use App\Enums\FaseSeo;
use App\Models\SeoCampana;
use App\Models\SeoFaseAuditoria;
use App\Models\SeoFaseEjecucion;
use App\Models\SeoFaseEstrategia;
use App\Models\SeoReporte;
use Illuminate\Database\Eloquent\Model;

class MaquinaSeo extends MaquinaFasesCiclica
{
    protected function sujeto(): string
    {
        return 'La campaña';
    }

    protected function generoNeutro(): string
    {
        return 'a';
    }

    protected function faseInicial(): \BackedEnum
    {
        return FaseSeo::Auditoria;
    }

    protected function faseCerrada(): \BackedEnum
    {
        return FaseSeo::Cerrada;
    }

    protected function faseReporte(): \BackedEnum
    {
        return FaseSeo::Reporte;
    }

    /** @param  SeoCampana  $modelo */
    public function registro(Model $modelo, \BackedEnum $fase): Model
    {
        return match ($fase) {
            FaseSeo::Auditoria => $modelo->faseAuditoria ?? $modelo->auditorias()->create(['ciclo' => $modelo->ciclo_actual, 'checklist' => []]),
            FaseSeo::Estrategia => $modelo->faseEstrategia ?? $modelo->estrategias()->create(['ciclo' => $modelo->ciclo_actual, 'checklist' => []]),
            FaseSeo::Ejecucion => $modelo->faseEjecucion ?? $modelo->ejecuciones()->create(['ciclo' => $modelo->ciclo_actual, 'checklist' => []]),
            FaseSeo::Reporte => $modelo->reporteActual ?? $modelo->reportes()->create(['ciclo' => $modelo->ciclo_actual, 'checklist' => []]),
            FaseSeo::Cerrada => throw new \InvalidArgumentException('La campaña está cerrada.'),
        };
    }

    /** @param  SeoCampana  $modelo */
    protected function registroSiExiste(Model $modelo, \BackedEnum $fase): ?Model
    {
        return match ($fase) {
            FaseSeo::Auditoria => $modelo->faseAuditoria,
            FaseSeo::Estrategia => $modelo->faseEstrategia,
            FaseSeo::Ejecucion => $modelo->faseEjecucion,
            FaseSeo::Reporte => $modelo->reporteActual,
            FaseSeo::Cerrada => null,
        };
    }

    protected function checklistKeys(\BackedEnum $fase): array
    {
        return match ($fase) {
            FaseSeo::Auditoria => SeoFaseAuditoria::CHECKLIST,
            FaseSeo::Estrategia => SeoFaseEstrategia::CHECKLIST,
            FaseSeo::Ejecucion => SeoFaseEjecucion::CHECKLIST,
            FaseSeo::Reporte => SeoReporte::CHECKLIST,
            FaseSeo::Cerrada => [],
        };
    }

    /** @param  SeoCampana  $modelo */
    protected function crearCiclo(Model $modelo, int $ciclo): void
    {
        $modelo->auditorias()->create(['ciclo' => $ciclo, 'checklist' => []]);
        $modelo->estrategias()->create(['ciclo' => $ciclo, 'checklist' => []]);
        $modelo->ejecuciones()->create(['ciclo' => $ciclo, 'checklist' => []]);
        $modelo->reportes()->create(['ciclo' => $ciclo, 'checklist' => []]);
    }
}
