<?php

namespace Tests\Feature\Admin;

use App\Models\ConfiguracionMedicion;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Medicion;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Configuración > Medición y Google: guardar los códigos de Google (a mano o pegando el bloque
 * que da Google) para que el sitio público los cargue sin tocar el .env.
 */
class ConfiguracionMedicionTest extends TestCase
{
    use RefreshDatabase;

    private const PEGADO_SECRETO = "<script async src=\"https://www.googletagmanager.com/gtag/js?id=AW-18553224619\"></script>\n<script>gtag('config', 'AW-18553224619'); /* TEXTO-PEGADO-UNICO-XYZ */</script>";

    private function payload(array $extra = []): array
    {
        return array_merge([
            'activa' => '1',
            'gtm_id' => '',
            'ga4_id' => 'G-ABCDE12345',
            'google_ads_id' => 'AW-18553224619',
            'dominios' => 'rankprosolutions.com.mx',
        ], $extra);
    }

    private function usuarioConPermisos(array $modulos): User
    {
        $rol = Role::query()->create(['name' => 'operador', 'label' => 'Operador']);
        foreach ($modulos as $modulo) {
            $permiso = Permission::query()->firstOrCreate(['name' => $modulo], ['label' => $modulo, 'module' => $modulo]);
            $rol->permissions()->attach($permiso->id);
        }

        return User::factory()->create(['role_id' => $rol->id, 'is_active' => true]);
    }

    public function test_invitado_va_al_login(): void
    {
        $this->get(route('admin.configuracion.medicion.edit'))->assertRedirect(route('login'));
        $this->put(route('admin.configuracion.medicion.update'), $this->payload())->assertRedirect(route('login'));
    }

    public function test_sin_el_permiso_configuracion_recibe_403(): void
    {
        config(['permisos.aplicar' => true]);
        $usuario = $this->usuarioConPermisos(['clientes']);

        $this->actingAs($usuario)->get(route('admin.configuracion.medicion.edit'))->assertForbidden();
        $this->actingAs($usuario)->put(route('admin.configuracion.medicion.update'), $this->payload())->assertForbidden();
        $this->assertNull(ConfiguracionMedicion::actual());
    }

    public function test_con_el_permiso_configuracion_entra(): void
    {
        config(['permisos.aplicar' => true]);
        $usuario = $this->usuarioConPermisos(['configuracion']);

        $this->actingAs($usuario)->get(route('admin.configuracion.medicion.edit'))->assertOk();
    }

    public function test_edit_pasa_los_datos_a_la_vista_sin_configuracion_previa(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.configuracion.medicion.edit'))
            ->assertOk()
            ->assertViewHas('pageTitle', 'Medición y Google')
            ->assertViewHas('config', null)
            ->assertViewHas('hostActual', 'localhost')
            ->assertViewHas('formatos', fn ($f) => $f['gtm'] === 'GTM-ABC1234' && $f['conversion'] === 'AW-123456789/AbC_dEfGhIj')
            ->assertViewHas('estado', fn ($e) => $e['fuente'] === 'env');
    }

    public function test_las_rutas_nuevas_no_chocan_con_smtp_ni_agenda(): void
    {
        $this->assertSame(url('/admin/configuracion/medicion'), route('admin.configuracion.medicion.edit'));
        $this->assertNotSame(route('admin.configuracion.smtp.edit'), route('admin.configuracion.medicion.edit'));

        $usuario = User::factory()->create();
        $this->actingAs($usuario)->get(route('admin.configuracion.smtp.edit'))->assertOk();
        $this->actingAs($usuario)->get(route('admin.agenda.index'))->assertOk();
    }

    public function test_guardar_crea_la_fila_unica_normaliza_y_registra_quien(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->put(route('admin.configuracion.medicion.update'), $this->payload([
                'gtm_id' => ' gtm-abc1234 ',
                'ga4_id' => 'g-abcde12345',
                'conversion_agendar' => 'aw-18553224619/AbC_dEfGhIj',
                'dominios' => "RankProSolutions.com.mx,\nhttps://www.Otro.com/ruta, otro.com",
            ]))
            ->assertRedirect(route('admin.configuracion.medicion.edit'))
            ->assertSessionHas('status');

        $config = ConfiguracionMedicion::actual();
        $this->assertSame(1, $config->id);
        $this->assertTrue($config->activa);
        $this->assertSame('GTM-ABC1234', $config->gtm_id);
        $this->assertSame('G-ABCDE12345', $config->ga4_id);
        $this->assertSame('AW-18553224619', $config->google_ads_id);
        $this->assertSame('AW-18553224619/AbC_dEfGhIj', $config->conversion_agendar);
        $this->assertSame('rankprosolutions.com.mx, www.otro.com, otro.com', $config->dominios);
        $this->assertSame($usuario->id, $config->actualizado_por);
        $this->assertSame(1, ConfiguracionMedicion::query()->count());

        // Un segundo guardado actualiza la misma fila.
        $this->actingAs($usuario)->put(route('admin.configuracion.medicion.update'), $this->payload(['activa' => '0', 'ga4_id' => '']));
        $this->assertSame(1, ConfiguracionMedicion::query()->count());
        $this->assertFalse(ConfiguracionMedicion::actual()->activa);
        $this->assertNull(ConfiguracionMedicion::actual()->ga4_id);
    }

    public function test_ids_invalidos_dan_422_con_mensajes_en_espanol(): void
    {
        $respuesta = $this->actingAs(User::factory()->create())
            ->putJson(route('admin.configuracion.medicion.update'), $this->payload([
                'gtm_id' => 'GTM-1',
                'ga4_id' => 'UA-12345-1',
                'google_ads_id' => 'AW-12',
                'conversion_whatsapp' => 'AW-123456789',
                'conversion_telefono' => 'AW-123456789/a b',
            ]));

        $respuesta->assertStatus(422)->assertJsonValidationErrors(['gtm_id', 'ga4_id', 'google_ads_id', 'conversion_whatsapp', 'conversion_telefono']);
        $respuesta->assertJsonPath('errors.google_ads_id.0', 'El ID de Google Ads debe verse así: AW-123456789');
        $respuesta->assertJsonPath('errors.gtm_id.0', 'El ID de Google Tag Manager debe verse así: GTM-ABC1234');
        $this->assertNull(ConfiguracionMedicion::actual());
    }

    public function test_dominio_invalido_da_error(): void
    {
        $this->actingAs(User::factory()->create())
            ->putJson(route('admin.configuracion.medicion.update'), $this->payload(['dominios' => 'mi sitio$.com<script>']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['dominios']);
    }

    public function test_activar_sin_ningun_id_es_un_error_claro(): void
    {
        $this->actingAs(User::factory()->create())
            ->putJson(route('admin.configuracion.medicion.update'), $this->payload(['ga4_id' => '', 'google_ads_id' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['activa']);

        $this->assertNull(ConfiguracionMedicion::actual());
    }

    public function test_guardar_sin_ids_y_desactivada_si_se_permite(): void
    {
        $this->actingAs(User::factory()->create())
            ->put(route('admin.configuracion.medicion.update'), $this->payload(['activa' => '0', 'ga4_id' => '', 'google_ads_id' => '']))
            ->assertRedirect();

        $this->assertFalse(ConfiguracionMedicion::actual()->activa);
    }

    public function test_guardar_desde_codigo_pegado_rellena_los_ids_y_sobrescribe(): void
    {
        $this->actingAs(User::factory()->create())
            ->put(route('admin.configuracion.medicion.update'), [
                'activa' => '1',
                'ga4_id' => 'G-VIEJO12345',
                'codigo_pegado' => self::PEGADO_SECRETO,
                'dominios' => 'rankprosolutions.com.mx',
            ])
            ->assertRedirect(route('admin.configuracion.medicion.edit'))
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'AW-18553224619'));

        $config = ConfiguracionMedicion::actual();
        $this->assertSame('AW-18553224619', $config->google_ads_id);
        $this->assertSame('G-VIEJO12345', $config->ga4_id, 'Lo no detectado en el pegado se conserva.');
        $this->assertNull($config->gtm_id);
    }

    public function test_el_pegado_sobrescribe_el_id_escrito_a_mano_del_mismo_tipo(): void
    {
        $this->actingAs(User::factory()->create())
            ->put(route('admin.configuracion.medicion.update'), [
                'activa' => '1',
                'google_ads_id' => 'AW-111111111',
                'codigo_pegado' => self::PEGADO_SECRETO,
                'dominios' => 'rankprosolutions.com.mx',
            ]);

        $this->assertSame('AW-18553224619', ConfiguracionMedicion::actual()->google_ads_id);
    }

    public function test_la_conversion_del_pegado_solo_se_asigna_con_destino(): void
    {
        $texto = "gtag('event', 'conversion', {'send_to': 'AW-18553224619/AbC_dEfGhIj'});";
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->put(route('admin.configuracion.medicion.update'), [
            'activa' => '1', 'codigo_pegado' => $texto, 'dominios' => 'rankprosolutions.com.mx',
        ])->assertSessionHas('status', fn ($s) => str_contains($s, 'AW-18553224619/AbC_dEfGhIj'));

        $this->assertNull(ConfiguracionMedicion::actual()->conversion_agendar);
        $this->assertSame('AW-18553224619', ConfiguracionMedicion::actual()->google_ads_id);

        $this->actingAs($usuario)->put(route('admin.configuracion.medicion.update'), [
            'activa' => '1', 'codigo_pegado' => $texto, 'conversion_destino' => 'whatsapp', 'dominios' => 'rankprosolutions.com.mx',
        ]);

        $this->assertSame('AW-18553224619/AbC_dEfGhIj', ConfiguracionMedicion::actual()->conversion_whatsapp);
    }

    public function test_un_pegado_sin_ningun_id_reconocible_da_error_y_no_se_reimprime(): void
    {
        $respuesta = $this->actingAs(User::factory()->create())
            ->from(route('admin.configuracion.medicion.edit'))
            ->put(route('admin.configuracion.medicion.update'), [
                'codigo_pegado' => '<script>TEXTO-PEGADO-UNICO-XYZ</script>',
                'dominios' => 'rankprosolutions.com.mx',
            ]);

        $respuesta->assertRedirect(route('admin.configuracion.medicion.edit'))->assertSessionHasErrors('codigo_pegado');

        // Ni en la sesión (old input) ni en la página que vuelve a pintar el formulario.
        $this->assertNull(session()->getOldInput('codigo_pegado'));
        $this->get(route('admin.configuracion.medicion.edit'))->assertDontSee('TEXTO-PEGADO-UNICO-XYZ', false);
    }

    public function test_el_texto_pegado_nunca_se_guarda_ni_se_imprime(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->put(route('admin.configuracion.medicion.update'), [
                'activa' => '1', 'codigo_pegado' => self::PEGADO_SECRETO, 'dominios' => 'rankprosolutions.com.mx',
            ])
            ->assertSessionMissing('_old_input.codigo_pegado');

        $pagina = $this->actingAs($usuario)->get(route('admin.configuracion.medicion.edit'));
        $pagina->assertOk()->assertDontSee('TEXTO-PEGADO-UNICO-XYZ', false);

        $this->assertStringNotContainsString('TEXTO-PEGADO-UNICO-XYZ', json_encode(ConfiguracionMedicion::actual()->getAttributes()));
    }

    public function test_guardar_invalida_la_cache_y_el_sitio_publico_lo_refleja(): void
    {
        $usuario = User::factory()->create();

        // Leer antes de guardar deja cacheado el "no hay fila".
        $this->assertSame('env', Medicion::fuente());
        $this->assertStringNotContainsString('gtag/js', $this->get('http://rankprosolutions.com.mx/')->getContent());

        $this->actingAs($usuario)->put(route('admin.configuracion.medicion.update'), $this->payload());

        $this->assertSame('panel', Medicion::fuente());
        $this->assertStringContainsString('gtag/js?id=G-ABCDE12345', $this->get('http://rankprosolutions.com.mx/')->getContent());

        $this->actingAs($usuario)->put(route('admin.configuracion.medicion.update'), $this->payload(['activa' => '0']));

        $this->assertStringNotContainsString('gtag/js', $this->get('http://rankprosolutions.com.mx/')->getContent());
    }

    public function test_el_menu_resalta_solo_el_item_de_medicion(): void
    {
        $this->actingAs(User::factory()->create());

        $enlaces = collect(Navigation::todos()['Administración'])->keyBy('label');

        $this->assertSame('admin.configuracion.medicion.*', $enlaces['Medición y Google']['active']);
        $this->assertSame('admin.configuracion.smtp.*', $enlaces['Configuración de correo']['active']);
        $this->assertSame('configuracion', $enlaces['Medición y Google']['permiso']);
    }
}
