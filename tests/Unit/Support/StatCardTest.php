<?php

namespace Tests\Unit\Support;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Compatibilidad de <x-stat-card>: los usos existentes no pasan las props nuevas
 * (fuente, fecha, tipo) y deben renderizar exactamente igual que antes.
 */
class StatCardTest extends TestCase
{
    public function test_el_marcado_sin_props_nuevas_es_el_de_siempre(): void
    {
        $html = Blade::render('<x-stat-card label="Clics" value="12" sub="CTR 2%" icon="fa-arrow-pointer" color="amber" />');
        $normalizado = preg_replace('/\s+/', ' ', trim($html));

        $this->assertSame(
            '<div class="kpi"> <div class="kpi__top"> <span class="kpi__label">Clics</span> <span class="kpi__icon kpi__icon--amber"><i class="fa-solid fa-arrow-pointer"></i></span> </div> <div class="kpi__value">12</div> <div class="kpi__sub">CTR 2%</div> </div>',
            $normalizado
        );
    }

    public function test_la_tendencia_sigue_funcionando_sin_props_nuevas(): void
    {
        $html = Blade::render('<x-stat-card label="MRR" value="$10K" :trend="8" icon="fa-arrow-trend-up" />');

        $this->assertStringContainsString('kpi__trend--up', $html);
        $this->assertStringContainsString('8% vs mes anterior', $html);
        $this->assertStringNotContainsString('dato-fecha', $html);
    }

    public function test_acepta_atributos_extra_y_href_como_antes(): void
    {
        $this->assertStringContainsString('<div class="kpi" id="kpiRoas">', Blade::render('<x-stat-card id="kpiRoas" label="ROAS" value="3x" icon="fa-chart-line" />'));
        $this->assertStringContainsString('<a href="/ir" class="kpi">', Blade::render('<x-stat-card label="X" value="1" icon="fa-users" href="/ir" />'));
    }

    public function test_solo_con_tipo_calculado_muestra_la_leyenda(): void
    {
        $html = Blade::render('<x-stat-card label="MRR" value="1" icon="fa-users" fuente="Servicios (MRR)" tipo="calculado" />');

        $this->assertStringContainsString('Servicios (MRR) · Calculado al momento', $html);
    }
}
