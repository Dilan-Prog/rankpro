<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * `php artisan analytics:estado` dice por qué el sitio no emite la etiqueta de Google Ads.
 */
class EstadoMedicionComandoTest extends TestCase
{
    public function test_en_produccion_sin_gtm_dice_que_la_etiqueta_se_emite(): void
    {
        config(['app.env' => 'production', 'analytics.gtm_id' => null, 'analytics.google_ads_id' => 'AW-18553224619']);

        $this->artisan('analytics:estado')
            ->expectsOutputToContain('SÍ se emite')
            ->assertExitCode(0);
    }

    public function test_con_un_entorno_distinto_de_produccion_explica_que_hay_que_poner_app_env(): void
    {
        config(['app.env' => 'local', 'analytics.gtm_id' => null, 'analytics.google_ads_id' => 'AW-18553224619']);

        $this->artisan('analytics:estado')
            ->expectsOutputToContain('APP_ENV es «local»')
            ->assertExitCode(1);
    }

    public function test_con_gtm_definido_explica_que_la_directa_se_apaga(): void
    {
        config(['app.env' => 'production', 'analytics.gtm_id' => 'GTM-ABC123', 'analytics.google_ads_id' => 'AW-18553224619']);

        $this->artisan('analytics:estado')
            ->expectsOutputToContain('Hay GTM_ID')
            ->assertExitCode(1);
    }

    public function test_con_un_id_invalido_o_vacio_lo_senala(): void
    {
        config(['app.env' => 'production', 'analytics.gtm_id' => null, 'analytics.google_ads_id' => '']);

        $this->artisan('analytics:estado')
            ->expectsOutputToContain('GOOGLE_ADS_ID está vacío')
            ->assertExitCode(1);
    }
}
