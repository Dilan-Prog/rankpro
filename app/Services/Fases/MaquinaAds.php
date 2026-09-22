<?php

namespace App\Services\Fases;

use App\Enums\FaseAds;
use App\Models\AdsBriefing;
use App\Models\AdsCampana;
use App\Models\AdsConfiguracion;
use App\Models\AdsLanzamiento;
use App\Models\AdsReporte;
use Illuminate\Database\Eloquent\Model;

class MaquinaAds extends MaquinaFasesCiclica
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
        return FaseAds::Briefing;
    }

    protected function faseCerrada(): \BackedEnum
    {
        return FaseAds::Cerrada;
    }

    protected function faseReporte(): \BackedEnum
    {
        return FaseAds::Reporte;
    }

    /** @param  AdsCampana  $modelo */
    public function registro(Model $modelo, \BackedEnum $fase): Model
    {
        return match ($fase) {
            FaseAds::Briefing => $modelo->briefing ?? $modelo->briefings()->create(['ciclo' => $modelo->ciclo_actual, 'checklist' => []]),
            FaseAds::Configuracion => $modelo->configuracion ?? $modelo->configuraciones()->create(['ciclo' => $modelo->ciclo_actual, 'checklist' => []]),
            FaseAds::Lanzamiento => $modelo->lanzamiento ?? $modelo->lanzamientos()->create(['ciclo' => $modelo->ciclo_actual, 'checklist' => []]),
            FaseAds::Reporte => $modelo->reporteActual ?? $modelo->reportes()->create(['ciclo' => $modelo->ciclo_actual, 'checklist' => []]),
            FaseAds::Cerrada => throw new \InvalidArgumentException('La campaña está cerrada.'),
        };
    }

    /** @param  AdsCampana  $modelo */
    protected function registroSiExiste(Model $modelo, \BackedEnum $fase): ?Model
    {
        return match ($fase) {
            FaseAds::Briefing => $modelo->briefing,
            FaseAds::Configuracion => $modelo->configuracion,
            FaseAds::Lanzamiento => $modelo->lanzamiento,
            FaseAds::Reporte => $modelo->reporteActual,
            FaseAds::Cerrada => null,
        };
    }

    protected function checklistKeys(\BackedEnum $fase): array
    {
        return match ($fase) {
            FaseAds::Briefing => AdsBriefing::CHECKLIST,
            FaseAds::Configuracion => AdsConfiguracion::CHECKLIST,
            FaseAds::Lanzamiento => AdsLanzamiento::CHECKLIST,
            FaseAds::Reporte => AdsReporte::CHECKLIST,
            FaseAds::Cerrada => [],
        };
    }

    /** @param  AdsCampana  $modelo */
    protected function crearCiclo(Model $modelo, int $ciclo): void
    {
        $modelo->briefings()->create(['ciclo' => $ciclo, 'checklist' => []]);
        $modelo->configuraciones()->create(['ciclo' => $ciclo, 'checklist' => []]);
        $modelo->lanzamientos()->create(['ciclo' => $ciclo, 'checklist' => []]);
        $modelo->reportes()->create(['ciclo' => $ciclo, 'checklist' => []]);
    }
}
