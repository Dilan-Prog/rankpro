<?php

namespace Tests\Unit\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Componente <x-dato-fecha>: fuente y fecha dd/mm/aaaa, e insignia ámbar solo cuando el dato
 * es antiguo. La compatibilidad de <x-stat-card> está en StatCardTest.
 */
class DatoFechaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function dato(string $atributos, array $datos = []): string
    {
        return Blade::render('<x-dato-fecha '.$atributos.' />', $datos);
    }

    public function test_muestra_fuente_y_fecha_en_dd_mm_aaaa_sin_insignia_si_es_reciente(): void
    {
        $html = $this->dato('fuente="Auditoría SEO" :fecha="$f"', ['f' => Carbon::parse('2026-11-05')]);

        $this->assertStringContainsString('Auditoría SEO · 05/11/2026', $html);
        $this->assertStringNotContainsString('dato-fecha__alerta', $html);
        $this->assertStringNotContainsString('2026-11-05', $html);
    }

    public function test_dato_antiguo_agrega_insignia_ambar_con_texto_e_icono(): void
    {
        $html = $this->dato('fuente="Métricas" :fecha="$f"', ['f' => Carbon::parse('2026-10-01')]);

        $this->assertStringContainsString('dato-fecha__alerta', $html);
        $this->assertStringContainsString('Hace 40 días', $html);
        $this->assertStringContainsString('fa-clock', $html);
        $this->assertStringContainsString('title="Este dato tiene 40 días; se considera desactualizado después de 30."', $html);
    }

    public function test_en_la_frontera_de_30_dias_no_hay_insignia_y_en_31_si(): void
    {
        $this->assertStringNotContainsString('dato-fecha__alerta', $this->dato(':fecha="$f"', ['f' => Carbon::now()->subDays(30)]));
        $this->assertStringContainsString('dato-fecha__alerta', $this->dato(':fecha="$f"', ['f' => Carbon::now()->subDays(31)]));
    }

    public function test_la_auditoria_usa_el_umbral_de_90_dias(): void
    {
        $this->assertStringNotContainsString('dato-fecha__alerta', $this->dato('tipo="auditoria" :fecha="$f"', ['f' => Carbon::now()->subDays(90)]));
        $this->assertStringContainsString('Hace 91 días', $this->dato('tipo="auditoria" :fecha="$f"', ['f' => Carbon::now()->subDays(91)]));
    }

    public function test_sin_fecha_dice_sin_fecha_de_captura_y_no_marca_antiguo(): void
    {
        $html = $this->dato('fuente="Ads"');

        $this->assertStringContainsString('Ads · Sin fecha de captura', $html);
        $this->assertStringNotContainsString('dato-fecha__alerta', $html);
    }

    public function test_sin_fecha_admite_un_texto_propio(): void
    {
        $this->assertStringContainsString('Sin fecha de auditoría', $this->dato('fuente="Auditoría SEO" sin-fecha="Sin fecha de auditoría"'));
    }

    public function test_calculado_al_momento_no_muestra_fecha_ni_insignia(): void
    {
        $html = $this->dato('fuente="Servicios (MRR)" tipo="calculado"');

        $this->assertStringContainsString('Servicios (MRR) · Calculado al momento', $html);
        $this->assertStringNotContainsString('dato-fecha__alerta', $html);
    }

    public function test_el_texto_sustituye_la_fecha_pero_la_edad_sigue_saliendo_de_la_fecha(): void
    {
        $html = $this->dato('fuente="Ads" :fecha="$f" texto="periodo 08/2026"', ['f' => Carbon::parse('2026-08-31')]);

        $this->assertStringContainsString('Ads · periodo 08/2026', $html);
        $this->assertStringContainsString('Hace 71 días', $html);
    }

    public function test_una_fecha_ilegible_no_rompe_la_vista(): void
    {
        $this->assertStringContainsString('Sin fecha de captura', $this->dato('fecha="basura"'));
    }

    public function test_stat_card_con_fuente_y_fecha_los_muestra(): void
    {
        $html = Blade::render('<x-stat-card label="Score" value="80" icon="fa-gauge-high" fuente="Auditoría SEO" :fecha="$f" tipo="auditoria" />', ['f' => Carbon::parse('2026-07-01')]);

        $this->assertStringContainsString('Auditoría SEO · 01/07/2026', $html);
        $this->assertStringContainsString('Hace 132 días', $html);
    }
}
