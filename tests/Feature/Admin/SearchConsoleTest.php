<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\GscConexion;
use App\Models\GscMetricaMensual;
use App\Models\GscPropiedad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\LlaveGoogleDePrueba;
use Tests\TestCase;

/**
 * Pantalla Integraciones > Search Console: llave cifrada que nunca se muestra, prueba de conexión
 * con resultado persistente, propiedades por cliente y "Sincronizar ahora".
 */
class SearchConsoleTest extends TestCase
{
    use RefreshDatabase;

    private const SITIOS = 'https://www.googleapis.com/webmasters/v3/sites';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['services.google_search_console.espera_ms' => 0]);
    }

    private function usuario(): User
    {
        return User::factory()->create();
    }

    private function guardarLlave(?string $json = null): void
    {
        $this->actingAs($this->usuario())
            ->put(route('admin.integraciones.search-console.llave.guardar'), ['llave_json' => $json ?? LlaveGoogleDePrueba::json()])
            ->assertRedirect(route('admin.integraciones.search-console.index'));
    }

    private function tokenOk(): array
    {
        return ['oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.t', 'expires_in' => 3600])];
    }

    private function fragmentosSecretos(): array
    {
        $privada = LlaveGoogleDePrueba::par()['privada'];
        $cuerpo = trim(str_replace(['-----BEGIN PRIVATE KEY-----', '-----END PRIVATE KEY-----', "\n", "\r"], '', $privada));

        return ['BEGIN PRIVATE KEY', 'END PRIVATE KEY', substr($cuerpo, 20, 40), substr($cuerpo, 200, 40)];
    }

    private function assertSinSecretos(string $texto): void
    {
        foreach ($this->fragmentosSecretos() as $fragmento) {
            $this->assertStringNotContainsString($fragmento, $texto);
        }
    }

    // ---- acceso -----------------------------------------------------------

    public function test_todas_las_rutas_exigen_sesion(): void
    {
        $cliente = Cliente::factory()->create();
        $propiedad = GscPropiedad::create(['cliente_id' => $cliente->id, 'site_url' => 'sc-domain:e.com']);

        $this->get(route('admin.integraciones.search-console.index'))->assertRedirect(route('login'));
        $this->put(route('admin.integraciones.search-console.llave.guardar'), ['llave_json' => LlaveGoogleDePrueba::json()])->assertRedirect(route('login'));
        $this->delete(route('admin.integraciones.search-console.llave.quitar'))->assertRedirect(route('login'));
        $this->post(route('admin.integraciones.search-console.probar'))->assertRedirect(route('login'));
        $this->put(route('admin.integraciones.search-console.propiedad.guardar', $cliente), ['site_url' => 'sc-domain:x.com'])->assertRedirect(route('login'));
        $this->delete(route('admin.integraciones.search-console.propiedad.quitar', $propiedad))->assertRedirect(route('login'));
        $this->post(route('admin.integraciones.search-console.sincronizar', $propiedad))->assertRedirect(route('login'));

        $this->assertNull(GscConexion::actual());
    }

    // ---- estados vacíos ---------------------------------------------------

    public function test_sin_llave_ni_propiedades_muestra_estados_vacios_honestos(): void
    {
        Cliente::factory()->create(['nombre' => 'Hotel Prueba']);

        $this->actingAs($this->usuario())->get(route('admin.integraciones.search-console.index'))
            ->assertOk()
            ->assertSee('Aún no hay llave')
            ->assertSee('Sin propiedad asignada')
            ->assertSee('Google Search Console API')
            ->assertSee('Hotel Prueba')
            ->assertDontSee('data-gsc-probar', false);
    }

    public function test_el_indice_de_integraciones_enlaza_a_la_pantalla(): void
    {
        $this->actingAs($this->usuario())->get(route('admin.integraciones.index'))
            ->assertOk()
            ->assertSee(route('admin.integraciones.search-console.index'), false);
    }

    // ---- llave ------------------------------------------------------------

    public function test_guarda_la_llave_cifrada_y_nunca_la_muestra(): void
    {
        $this->guardarLlave();

        $conexion = GscConexion::actual();
        $this->assertSame(LlaveGoogleDePrueba::CORREO, $conexion->client_email);
        $this->assertSame(GscConexion::PENDIENTE, $conexion->estado);

        // En la columna cruda no se puede leer la llave privada: va cifrada.
        $crudo = DB::table('gsc_conexion')->where('id', 1)->value('llave_json');
        $this->assertNotEmpty($crudo);
        $this->assertSinSecretos($crudo);
        $this->assertStringNotContainsString('private_key', $crudo);
        // ...pero el servicio sí la recupera para firmar.
        $this->assertSame(LlaveGoogleDePrueba::par()['privada'], $conexion->credenciales()['private_key']);

        // Solo se guarda lo necesario, no el resto del JSON de Google.
        $this->assertArrayNotHasKey('private_key_id', json_decode($conexion->llave_json, true));
    }

    public function test_la_pantalla_muestra_el_correo_y_llave_guardada_pero_no_la_llave(): void
    {
        $this->guardarLlave();

        $respuesta = $this->actingAs($this->usuario())->get(route('admin.integraciones.search-console.index'));

        $respuesta->assertOk()
            ->assertSee(LlaveGoogleDePrueba::CORREO)
            ->assertSee('Llave guardada')
            ->assertSee('data-gsc-probar', false)
            ->assertDontSee('Aún no hay llave');
        $this->assertSinSecretos($respuesta->getContent());
        // (el placeholder del textarea menciona "private_key" como ejemplo de formato; no es la llave.)
        $this->assertStringNotContainsString('llave_json', json_encode(GscConexion::actual()->toArray()));
        $this->assertSinSecretos(json_encode(GscConexion::actual()));
    }

    public function test_la_respuesta_al_guardar_ni_la_sesion_contienen_la_llave(): void
    {
        $usuario = $this->usuario();
        $r = $this->actingAs($usuario)->putJson(route('admin.integraciones.search-console.llave.guardar'), ['llave_json' => LlaveGoogleDePrueba::json()]);

        $r->assertOk()->assertJsonPath('ok', true)->assertJsonPath('client_email', LlaveGoogleDePrueba::CORREO);
        $this->assertSinSecretos($r->getContent());

        $this->assertSinSecretos(json_encode(session()->all()));
    }

    public function test_acepta_la_llave_subida_como_archivo(): void
    {
        $archivo = UploadedFile::fake()->createWithContent('llave.json', LlaveGoogleDePrueba::json());

        $this->actingAs($this->usuario())
            ->put(route('admin.integraciones.search-console.llave.guardar'), ['llave_archivo' => $archivo])
            ->assertSessionHasNoErrors();

        $this->assertSame(LlaveGoogleDePrueba::CORREO, GscConexion::actual()->client_email);
    }

    /** @return array<string, array{0: string}> */
    public static function llavesInvalidas(): array
    {
        return [
            'no es json' => ['esto no es json'],
            'oauth de escritorio, no cuenta de servicio' => [json_encode(['installed' => ['client_id' => 'x']])],
            'sin correo' => [json_encode(['type' => 'service_account', 'private_key' => '-----BEGIN PRIVATE KEY-----x-----END PRIVATE KEY-----'])],
            'correo invalido' => [json_encode(['type' => 'service_account', 'client_email' => 'nada', 'private_key' => '-----BEGIN PRIVATE KEY-----x-----END PRIVATE KEY-----'])],
            'sin llave privada' => [json_encode(['type' => 'service_account', 'client_email' => 'a@b.iam.gserviceaccount.com'])],
            'llave privada ilegible' => [json_encode(['type' => 'service_account', 'client_email' => 'a@b.iam.gserviceaccount.com', 'private_key' => '-----BEGIN PRIVATE KEY-----basura-----END PRIVATE KEY-----'])],
            'enorme' => [str_repeat('a', 25000)],
        ];
    }

    /** @dataProvider llavesInvalidas */
    public function test_rechaza_un_json_que_no_es_una_llave_de_cuenta_de_servicio(string $json): void
    {
        $this->actingAs($this->usuario())
            ->from(route('admin.integraciones.search-console.index'))
            ->put(route('admin.integraciones.search-console.llave.guardar'), ['llave_json' => $json])
            ->assertRedirect(route('admin.integraciones.search-console.index'))
            ->assertSessionHasErrors('llave_json')
            // Lo pegado no vuelve en old(): la llave no se refleja en el formulario ni se guarda en sesión.
            ->assertSessionMissing('_old_input.llave_json');

        $this->assertNull(GscConexion::actual());
    }

    public function test_un_envio_vacio_pide_pegar_o_subir_la_llave(): void
    {
        $this->actingAs($this->usuario())
            ->put(route('admin.integraciones.search-console.llave.guardar'), ['llave_json' => '   '])
            ->assertSessionHasErrors('llave_json');
    }

    public function test_reemplazar_la_llave_reinicia_la_prueba_anterior(): void
    {
        $this->guardarLlave();
        GscConexion::actual()->forceFill(['estado' => GscConexion::OK, 'ultima_prueba_ok' => true, 'ultima_prueba_resultado' => 'Conexión correcta', 'propiedades_detectadas' => ['sc-domain:e.com']])->save();

        $this->guardarLlave(LlaveGoogleDePrueba::json(['client_email' => 'otra@qa-proyecto.iam.gserviceaccount.com']));

        $c = GscConexion::actual();
        $this->assertSame('otra@qa-proyecto.iam.gserviceaccount.com', $c->client_email);
        $this->assertSame(GscConexion::PENDIENTE, $c->estado);
        $this->assertNull($c->ultima_prueba_resultado);
        $this->assertNull($c->propiedades_detectadas);
        $this->assertSame(1, GscConexion::count());
    }

    public function test_quitar_la_llave_la_borra_de_verdad(): void
    {
        $this->guardarLlave();

        $this->actingAs($this->usuario())->delete(route('admin.integraciones.search-console.llave.quitar'))->assertRedirect();

        $c = GscConexion::actual();
        $this->assertFalse($c->tieneLlave());
        $this->assertNull(DB::table('gsc_conexion')->where('id', 1)->value('llave_json'));
        $this->assertNull($c->client_email);
        $this->assertSame(GscConexion::SIN_LLAVE, $c->estado);

        $this->actingAs($this->usuario())->get(route('admin.integraciones.search-console.index'))
            ->assertSee('Aún no hay llave')
            ->assertDontSee(LlaveGoogleDePrueba::CORREO);
    }

    // ---- probar conexión --------------------------------------------------

    public function test_probar_sin_llave_explica_que_falta_la_llave(): void
    {
        Http::fake();

        $this->actingAs($this->usuario())->postJson(route('admin.integraciones.search-console.probar'))
            ->assertStatus(422)->assertJsonPath('ok', false);

        Http::assertNothingSent();
    }

    public function test_probar_lista_las_propiedades_y_deja_el_resultado_guardado(): void
    {
        $this->guardarLlave();
        Http::fake($this->tokenOk() + [self::SITIOS => Http::response(['siteEntry' => [
            ['siteUrl' => 'https://ejemplo.com/', 'permissionLevel' => 'siteRestrictedUser'],
            ['siteUrl' => 'sc-domain:otro.com', 'permissionLevel' => 'siteOwner'],
        ]])]);

        $this->actingAs($this->usuario())->postJson(route('admin.integraciones.search-console.probar'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('propiedades', ['https://ejemplo.com/', 'sc-domain:otro.com']);

        $c = GscConexion::actual();
        $this->assertSame(GscConexion::OK, $c->estado);
        $this->assertTrue($c->ultima_prueba_ok);
        $this->assertStringContainsString('2 propiedades accesibles', $c->ultima_prueba_resultado);

        // Persistente: está en la página al volver a entrar, no solo en un aviso de una vez.
        $this->actingAs($this->usuario())->get(route('admin.integraciones.search-console.index'))
            ->assertSee('data-gsc-prueba', false)
            ->assertSee('2 propiedades accesibles')
            ->assertSee('<option value="sc-domain:otro.com">', false);
    }

    public function test_probar_sin_propiedades_avisa_que_hay_que_dar_de_alta_el_correo(): void
    {
        $this->guardarLlave();
        Http::fake($this->tokenOk() + [self::SITIOS => Http::response([])]);

        $this->actingAs($this->usuario())->postJson(route('admin.integraciones.search-console.probar'))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertStringContainsString(LlaveGoogleDePrueba::CORREO, GscConexion::actual()->ultima_prueba_resultado);
    }

    public function test_probar_con_error_deja_un_mensaje_persistente_y_sin_secretos(): void
    {
        $this->guardarLlave();
        Http::fake(['oauth2.googleapis.com/token' => Http::response([
            'error' => 'invalid_grant',
            'error_description' => 'Invalid JWT Signature en C:\\laragon\\www\\rankpro\\x.php '.LlaveGoogleDePrueba::par()['privada'],
        ], 400)]);

        $this->actingAs($this->usuario())->postJson(route('admin.integraciones.search-console.probar'))
            ->assertStatus(502)
            ->assertJsonPath('ok', false);

        $c = GscConexion::actual();
        $this->assertSame(GscConexion::ERROR, $c->estado);
        $this->assertFalse($c->ultima_prueba_ok);
        $this->assertStringContainsString('rechazó la llave', $c->ultima_prueba_resultado);
        $this->assertSinSecretos($c->ultima_prueba_resultado);
        $this->assertStringNotContainsString('laragon', $c->ultima_prueba_resultado);

        $pagina = $this->actingAs($this->usuario())->get(route('admin.integraciones.search-console.index'));
        $pagina->assertSee('data-gsc-prueba', false)->assertSee('data-ok="0"', false)->assertSee('rechazó la llave');
        $this->assertSinSecretos($pagina->getContent());
    }

    public function test_probar_desde_el_formulario_redirige_con_el_aviso(): void
    {
        $this->guardarLlave();
        Http::fake($this->tokenOk() + [self::SITIOS => Http::response(['siteEntry' => [['siteUrl' => 'sc-domain:e.com', 'permissionLevel' => 'siteOwner']]])]);

        $this->actingAs($this->usuario())->post(route('admin.integraciones.search-console.probar'))
            ->assertRedirect(route('admin.integraciones.search-console.index'))
            ->assertSessionHas('status');
    }

    // ---- propiedades ------------------------------------------------------

    public function test_asigna_una_propiedad_al_cliente_y_agrega_la_barra_final_a_las_url(): void
    {
        $cliente = Cliente::factory()->create();

        $this->actingAs($this->usuario())
            ->put(route('admin.integraciones.search-console.propiedad.guardar', $cliente), ['site_url' => ' https://ejemplo.com ', 'activa' => '1'])
            ->assertRedirect();

        $p = GscPropiedad::where('cliente_id', $cliente->id)->first();
        $this->assertSame('https://ejemplo.com/', $p->site_url);
        $this->assertTrue($p->activa);
    }

    public function test_acepta_propiedades_de_dominio_y_rechaza_valores_invalidos(): void
    {
        $cliente = Cliente::factory()->create();
        $u = $this->usuario();

        $this->actingAs($u)->put(route('admin.integraciones.search-console.propiedad.guardar', $cliente), ['site_url' => 'sc-domain:ejemplo.com'])->assertSessionHasNoErrors();
        $this->assertSame('sc-domain:ejemplo.com', GscPropiedad::first()->site_url);

        foreach (['ejemplo.com', 'ftp://ejemplo.com/', 'sc-domain:con espacio.com', ''] as $malo) {
            $this->actingAs($u)->put(route('admin.integraciones.search-console.propiedad.guardar', $cliente), ['site_url' => $malo])->assertSessionHasErrors('site_url');
        }
        $this->assertSame('sc-domain:ejemplo.com', GscPropiedad::first()->site_url);
    }

    public function test_hay_una_sola_propiedad_por_cliente_y_se_puede_pausar(): void
    {
        $cliente = Cliente::factory()->create();
        $u = $this->usuario();

        $this->actingAs($u)->put(route('admin.integraciones.search-console.propiedad.guardar', $cliente), ['site_url' => 'sc-domain:a.com', 'activa' => '1']);
        $this->actingAs($u)->put(route('admin.integraciones.search-console.propiedad.guardar', $cliente), ['site_url' => 'sc-domain:a.com', 'activa' => '0']);

        $this->assertSame(1, GscPropiedad::count());
        $this->assertFalse(GscPropiedad::first()->activa);
    }

    public function test_cambiar_de_sitio_borra_los_totales_del_anterior_y_el_resultado(): void
    {
        $cliente = Cliente::factory()->create();
        $p = GscPropiedad::create(['cliente_id' => $cliente->id, 'site_url' => 'sc-domain:viejo.com', 'ultima_sync_en' => now(), 'ultimo_ok' => true, 'ultimo_resultado' => 'ok']);
        GscMetricaMensual::create(['propiedad_id' => $p->id, 'periodo' => '2026-09-01', 'clics' => 5, 'impresiones' => 50, 'ctr' => 0.1]);

        $this->actingAs($this->usuario())->put(route('admin.integraciones.search-console.propiedad.guardar', $cliente), ['site_url' => 'sc-domain:nuevo.com']);

        $p->refresh();
        $this->assertSame('sc-domain:nuevo.com', $p->site_url);
        $this->assertNull($p->ultima_sync_en);
        $this->assertNull($p->ultimo_ok);
        $this->assertSame(0, GscMetricaMensual::count());
    }

    public function test_desvincular_la_propiedad_borra_sus_totales_pero_no_las_mediciones(): void
    {
        $p = GscPropiedad::create(['cliente_id' => Cliente::factory()->create()->id, 'site_url' => 'sc-domain:a.com']);
        GscMetricaMensual::create(['propiedad_id' => $p->id, 'periodo' => '2026-09-01', 'clics' => 5, 'impresiones' => 50, 'ctr' => 0.1]);

        $this->actingAs($this->usuario())->delete(route('admin.integraciones.search-console.propiedad.quitar', $p))->assertRedirect();

        $this->assertSame(0, GscPropiedad::count());
        $this->assertSame(0, GscMetricaMensual::count());
    }

    // ---- sincronizar ahora y vista de resultados -------------------------

    private function conLlaveYPropiedad(array $extra = []): GscPropiedad
    {
        (new GscConexion())->forceFill([
            'id' => 1, 'client_email' => LlaveGoogleDePrueba::CORREO, 'estado' => GscConexion::OK,
            'llave_json' => json_encode(['client_email' => LlaveGoogleDePrueba::CORREO, 'private_key' => LlaveGoogleDePrueba::par()['privada']]),
        ])->save();

        return GscPropiedad::create(array_merge(['cliente_id' => Cliente::factory()->create(['nombre' => 'Cliente QA'])->id, 'site_url' => 'sc-domain:ejemplo.com'], $extra));
    }

    public function test_sincronizar_ahora_importa_y_la_pantalla_muestra_resultado_y_meses(): void
    {
        $p = $this->conLlaveYPropiedad();
        Http::fake($this->tokenOk() + ['www.googleapis.com/*' => Http::response(['rows' => [
            ['keys' => ['2026-09-10'], 'clicks' => 12, 'impressions' => 400, 'ctr' => 0.03, 'position' => 8.25],
        ]])]);

        $this->actingAs($this->usuario())->postJson(route('admin.integraciones.search-console.sincronizar', $p))
            ->assertOk()->assertJsonPath('ok', true);

        $this->actingAs($this->usuario())->get(route('admin.integraciones.search-console.index'))
            ->assertSee('data-gsc-resultado', false)
            ->assertSee('data-ok="1"', false)
            ->assertSee('data-periodo="2026-09"', false)
            ->assertSee('3.00%')
            ->assertSee('8.3');
    }

    public function test_una_sincronizacion_fallida_queda_visible_en_rojo_con_el_mensaje_completo(): void
    {
        $p = $this->conLlaveYPropiedad();
        Http::fake($this->tokenOk() + ['www.googleapis.com/*' => Http::response(['error' => ['message' => 'x']], 403)]);

        $this->actingAs($this->usuario())->postJson(route('admin.integraciones.search-console.sincronizar', $p))
            ->assertStatus(502)->assertJsonPath('ok', false);

        $this->actingAs($this->usuario())->get(route('admin.integraciones.search-console.index'))
            ->assertSee('data-gsc-resultado', false)
            ->assertSee('data-ok="0"', false)
            ->assertSee('form-status--error', false)
            ->assertSee('no tiene acceso a la propiedad sc-domain:ejemplo.com');
    }

    public function test_sincronizar_desde_el_formulario_redirige_y_una_propiedad_pausada_se_rechaza(): void
    {
        $p = $this->conLlaveYPropiedad(['activa' => false]);
        Http::fake();

        $this->actingAs($this->usuario())->post(route('admin.integraciones.search-console.sincronizar', $p))
            ->assertRedirect(route('admin.integraciones.search-console.index'))
            ->assertSessionHas('error');

        Http::assertNothingSent();
    }

    public function test_sincronizar_sin_llave_responde_con_el_error_guardado(): void
    {
        $p = GscPropiedad::create(['cliente_id' => Cliente::factory()->create()->id, 'site_url' => 'sc-domain:e.com']);
        Http::fake();

        $this->actingAs($this->usuario())->postJson(route('admin.integraciones.search-console.sincronizar', $p))
            ->assertStatus(502)
            ->assertJsonPath('ok', false);

        $this->assertStringContainsString('Aún no hay llave', $p->fresh()->ultimo_resultado);

        $this->actingAs($this->usuario())->get(route('admin.integraciones.search-console.index'))
            ->assertSee('Aún no hay llave')
            ->assertSee('disabled', false);
    }
}
