<?php

namespace Tests\Feature;

use App\Models\ConfiguracionMedicion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `php artisan analytics:estado` dice qué etiquetas de Google emite el sitio y por qué no emite el resto.
 */
class EstadoMedicionComandoTest extends TestCase
{
    use RefreshDatabase;

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

        // GTM sí se emite (exit 0); lo que se avisa es que la directa de Ads se apaga.
        $this->artisan('analytics:estado')
            ->expectsOutputToContain('Hay GTM_ID')
            ->expectsOutputToContain('Modo de carga: gtm')
            ->assertExitCode(0);
    }

    public function test_con_un_id_invalido_o_vacio_lo_senala(): void
    {
        config(['app.env' => 'production', 'analytics.gtm_id' => null, 'analytics.google_ads_id' => '']);

        $this->artisan('analytics:estado')
            ->expectsOutputToContain('GOOGLE_ADS_ID está vacío')
            ->assertExitCode(1);
    }

    public function test_con_configuracion_del_panel_activa_dice_la_fuente_y_lo_que_se_emite(): void
    {
        config(['app.env' => 'local']);
        ConfiguracionMedicion::query()->forceCreate([
            'id' => 1, 'activa' => true, 'dominios' => 'rankprosolutions.com.mx',
            'ga4_id' => 'G-ABCDE12345', 'google_ads_id' => 'AW-18553224619',
        ]);

        $this->artisan('analytics:estado')
            ->expectsOutputToContain('panel (Configuración > Medición y Google)')
            ->expectsOutputToContain('Modo de carga: gtag')
            ->expectsOutputToContain('SÍ se emite')
            ->assertExitCode(0);
    }

    public function test_con_el_panel_desactivado_explica_el_motivo(): void
    {
        config(['app.env' => 'production']);
        ConfiguracionMedicion::query()->forceCreate([
            'id' => 1, 'activa' => false, 'dominios' => 'rankprosolutions.com.mx', 'google_ads_id' => 'AW-18553224619',
        ]);

        $this->artisan('analytics:estado')
            ->expectsOutputToContain('desactivada en el panel')
            ->assertExitCode(1);
    }

    public function test_la_opcion_host_evalua_otro_host(): void
    {
        ConfiguracionMedicion::query()->forceCreate([
            'id' => 1, 'activa' => true, 'dominios' => 'rankprosolutions.com.mx', 'google_ads_id' => 'AW-18553224619',
        ]);

        $this->artisan('analytics:estado', ['--host' => 'staging.ejemplo.com'])
            ->expectsOutputToContain('staging.ejemplo.com')
            ->assertExitCode(1);
    }
}
