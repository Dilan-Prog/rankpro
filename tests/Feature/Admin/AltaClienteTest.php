<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\GscConexion;
use App\Models\GscPropiedad;
use App\Models\IdentidadMarca;
use App\Models\Keyword;
use App\Models\MetricaCliente;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Servicio;
use App\Models\User;
use App\Support\FinanzasMetrics;
use App\Support\Plantillas\AplicadorPlantilla;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Asistente de alta de cliente: validación por pasos, alta completa y rápida, MRR en vivo, permisos y atomicidad. */
class AltaClienteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    private function usuario(): User
    {
        return User::factory()->create();
    }

    private function datosCompletos(array $sobre = []): array
    {
        return array_replace_recursive([
            'modo' => 'completo',
            'nombre_comercial' => 'Hotel Prueba',
            'giro' => 'hotel',
            'plantilla' => 'hotel',
            'sitio_web' => 'hotelprueba.com',
            'ciudad' => 'Aguascalientes',
            'tema' => 'Plaza de la Patria',
            'contacto_nombre' => 'Laura Gómez',
            'telefono' => '+52 449 000 0000',
            'email' => 'laura@hotelprueba.com',
            'fecha_inicio_contrato' => '2026-11-01',
            'fecha_renovacion_contrato' => '2027-11-01',
            'forma_pago' => 'mensual',
            'metodo_pago' => 'transferencia',
            'servicios' => [
                ['tipo' => 'seo', 'nombre' => 'SEO local', 'tipo_cobro' => 'mensual', 'precio_mensual' => '8000'],
                ['tipo' => 'google_ads', 'nombre' => 'Google Ads', 'tipo_cobro' => 'mensual', 'precio_mensual' => '12000.50'],
                ['tipo' => 'rediseno', 'nombre' => 'Página web', 'tipo_cobro' => 'unico', 'precio_mensual' => '25000'],
                ['tipo' => 'seo', 'nombre' => '1 URL de SEO', 'tipo_cobro' => 'gratuito', 'precio_mensual' => ''],
            ],
            'colores' => [['nombre' => 'Color 1', 'hex' => '#0f9d6e'], ['nombre' => 'Color 2', 'hex' => '#112233']],
            'tonos' => ['cercana', 'profesional'],
            'conexiones' => ['gsc' => '1'],
        ], $sobre);
    }

    private function conectarGoogle(): void
    {
        (new GscConexion())->forceFill([
            'id' => 1,
            'client_email' => 'rankpro@proyecto.iam.gserviceaccount.com',
            'llave_json' => '{"private_key":"x"}',
            'estado' => GscConexion::PENDIENTE,
        ])->save();
    }

    // ---- acceso -------------------------------------------------------------

    public function test_invitado_no_puede_usar_el_asistente(): void
    {
        $this->postJson(route('admin.clientes.asistente.store'), $this->datosCompletos())->assertUnauthorized();
        $this->postJson(route('admin.clientes.asistente.validar'), ['paso' => 1])->assertUnauthorized();
        $this->postJson(route('admin.clientes.asistente.servicios'), [])->assertUnauthorized();
        $this->assertSame(0, Cliente::count());
    }

    public function test_un_rol_sin_el_permiso_clientes_recibe_403(): void
    {
        config(['permisos.aplicar' => true]);
        $rol = Role::query()->create(['name' => 'operador', 'label' => 'Operador']);
        $permiso = Permission::query()->firstOrCreate(['name' => 'dashboard'], ['label' => 'dashboard', 'module' => 'dashboard']);
        $rol->permissions()->attach($permiso->id);
        $usuario = User::factory()->create(['role_id' => $rol->id, 'is_active' => true]);

        $this->actingAs($usuario)->postJson(route('admin.clientes.asistente.store'), $this->datosCompletos())->assertForbidden();
        $this->actingAs($usuario)->postJson(route('admin.clientes.importar.vista-previa'), ['texto' => 'a'])->assertForbidden();
        $this->assertSame(0, Cliente::count());
    }

    public function test_el_crm_ofrece_el_asistente_y_la_importacion(): void
    {
        $this->actingAs($this->usuario())->get(route('admin.clientes.index'))
            ->assertOk()
            ->assertSee('altaClienteModal', false)
            ->assertSee('Alta rápida')
            ->assertSee('Importar clientes')
            ->assertSee('No disponible')
            ->assertSee('data-abrir-asistente', false);
    }

    // ---- validación por pasos -------------------------------------------------

    public function test_cada_paso_valida_lo_suyo_y_no_guarda_nada(): void
    {
        $u = $this->usuario();
        $ok = $this->datosCompletos();

        $this->actingAs($u)->postJson(route('admin.clientes.asistente.validar'), ['paso' => 1] + $ok)->assertOk()->assertJson(['ok' => true, 'paso' => 1]);
        $this->actingAs($u)->postJson(route('admin.clientes.asistente.validar'), ['paso' => 4] + $ok)->assertOk();
        $this->assertSame(0, Cliente::count());

        $this->actingAs($u)->postJson(route('admin.clientes.asistente.validar'), ['paso' => 1, 'nombre_comercial' => ''])->assertStatus(422)->assertJsonValidationErrors('nombre_comercial');
        $this->actingAs($u)->postJson(route('admin.clientes.asistente.validar'), ['paso' => 1] + $this->datosCompletos(['sitio_web' => 'esto no es un sitio']))->assertStatus(422)->assertJsonValidationErrors('sitio_web');
        $this->actingAs($u)->postJson(route('admin.clientes.asistente.validar'), ['paso' => 1] + $this->datosCompletos(['giro' => 'astronauta']))->assertStatus(422)->assertJsonValidationErrors('giro');
        $this->actingAs($u)->postJson(route('admin.clientes.asistente.validar'), ['paso' => 2, 'nombre_comercial' => 'X'])->assertStatus(422)->assertJsonValidationErrors('contacto_nombre');
        $this->actingAs($u)->postJson(route('admin.clientes.asistente.validar'), ['paso' => 2] + $this->datosCompletos(['fecha_renovacion_contrato' => '2026-01-01']))->assertStatus(422)->assertJsonValidationErrors('fecha_renovacion_contrato');
        $this->actingAs($u)->postJson(route('admin.clientes.asistente.validar'), ['paso' => 3] + $this->datosCompletos(['servicios' => [['tipo' => 'seo', 'nombre' => '', 'tipo_cobro' => 'mensual']]]))->assertStatus(422)->assertJsonValidationErrors('servicios.0.nombre');
        $this->actingAs($u)->postJson(route('admin.clientes.asistente.validar'), ['paso' => 4] + $this->datosCompletos(['colores' => [['hex' => 'rojo']]]))->assertStatus(422)->assertJsonValidationErrors('colores.0.hex');
        $this->actingAs($u)->postJson(route('admin.clientes.asistente.validar'), ['paso' => 5])->assertStatus(422);
        $this->assertSame(0, Cliente::count());
    }

    public function test_el_correo_duplicado_se_avisa_en_el_paso_2(): void
    {
        Cliente::factory()->create(['email' => 'laura@hotelprueba.com']);

        $this->actingAs($this->usuario())->postJson(route('admin.clientes.asistente.validar'), ['paso' => 2] + $this->datosCompletos())
            ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    // ---- alta completa -----------------------------------------------------------

    public function test_alta_completa_crea_cliente_servicios_metricas_keywords_identidad_y_conexion(): void
    {
        $this->conectarGoogle();
        $datos = $this->datosCompletos();
        $datos['logo'] = UploadedFile::fake()->image('logo.png', 120, 120);

        $r = $this->actingAs($this->usuario())->post(route('admin.clientes.asistente.store'), $datos, ['Accept' => 'application/json']);

        $r->assertCreated()->assertJson([
            'modo' => 'completo',
            'perfil_completo' => true,
            'faltantes' => [],
            'conexiones' => ['gsc' => 'creada'],
            'resumen' => ['servicios_creados' => 4, 'metricas_creadas' => 5, 'keywords_creadas' => 3],
        ]);

        $cliente = Cliente::sole();
        $this->assertSame('Hotel Prueba', $cliente->empresa);
        $this->assertSame('Laura Gómez', $cliente->nombre);
        $this->assertSame('Laura Gómez', $cliente->contacto_nombre);
        $this->assertSame('hotel', $cliente->giro);
        $this->assertSame('https://hotelprueba.com', $cliente->sitio_web);
        $this->assertSame('Aguascalientes', $cliente->ciudad);
        $this->assertSame('activo', $cliente->estado->value);
        $this->assertSame('2027-11-01', $cliente->fecha_renovacion_contrato->toDateString());
        $this->assertTrue($cliente->perfilCompleto());

        $this->assertSame(4, Servicio::where('cliente_id', $cliente->id)->count());
        $this->assertSame(5, MetricaCliente::where('cliente_id', $cliente->id)->count());
        $this->assertContains('hotel cerca de plaza de la patria', Keyword::where('cliente_id', $cliente->id)->pluck('keyword')->all());

        $identidad = IdentidadMarca::where('cliente_id', $cliente->id)->sole();
        $this->assertEquals([['nombre' => 'Color 1', 'hex' => '#0F9D6E'], ['nombre' => 'Color 2', 'hex' => '#112233']], $identidad->paleta);
        $this->assertSame(['cercana', 'profesional'], $identidad->tonos);
        $this->assertNotNull($identidad->logo_ruta);
        Storage::disk('local')->assertExists($identidad->logo_ruta);

        $this->assertSame('https://hotelprueba.com/', GscPropiedad::where('cliente_id', $cliente->id)->value('site_url'));
        $this->assertSame(route('admin.clientes.show', $cliente), $r->json('url_ficha'));
        $this->assertSame(route('admin.clientes.integraciones', $cliente), $r->json('url_integraciones'));
    }

    public function test_el_mrr_de_la_respuesta_y_el_calculo_en_vivo_son_la_fuente_unica(): void
    {
        $u = $this->usuario();
        $datos = $this->datosCompletos();

        $vivo = $this->actingAs($u)->postJson(route('admin.clientes.asistente.servicios'), ['servicios' => $datos['servicios']])->assertOk();
        $vivo->assertJson(['mensuales' => 2, 'unicos' => 1, 'gratuitos' => 1, 'sin_precio' => 0]);
        $this->assertEquals(20000.5, $vivo->json('mrr'));
        $this->assertEquals(25000, $vivo->json('unico'));

        $r = $this->actingAs($u)->postJson(route('admin.clientes.asistente.store'), $datos)->assertCreated();

        $this->assertEquals($vivo->json('mrr'), $r->json('mrr'));
        $this->assertEquals(FinanzasMetrics::mrr(), $r->json('mrr'));
    }

    public function test_los_servicios_sin_precio_se_cuentan_en_cero_y_se_avisa(): void
    {
        $vivo = $this->actingAs($this->usuario())->postJson(route('admin.clientes.asistente.servicios'), ['servicios' => [
            ['tipo_cobro' => 'mensual', 'precio_mensual' => ''],
            ['tipo_cobro' => 'mensual', 'precio_mensual' => '1500'],
            ['tipo_cobro' => 'gratuito', 'precio_mensual' => '999'],
        ]])->assertOk();

        $this->assertEquals(1500, $vivo->json('mrr'));
        $this->assertSame(1, $vivo->json('sin_precio'));
    }

    public function test_sin_conexion_de_google_no_se_crea_la_propiedad_pero_el_alta_sigue(): void
    {
        $r = $this->actingAs($this->usuario())->postJson(route('admin.clientes.asistente.store'), $this->datosCompletos())->assertCreated();

        $r->assertJson(['conexiones' => ['gsc' => 'sin_conexion_google']]);
        $this->assertSame(0, GscPropiedad::count());
    }

    public function test_sin_sitio_web_no_se_vincula_search_console(): void
    {
        $this->conectarGoogle();

        $r = $this->actingAs($this->usuario())->postJson(route('admin.clientes.asistente.store'), $this->datosCompletos(['sitio_web' => '']))->assertCreated();

        $r->assertJson(['conexiones' => ['gsc' => 'sin_sitio']]);
        $this->assertSame(0, GscPropiedad::count());
    }

    public function test_sin_plantilla_solo_se_crean_los_servicios_capturados(): void
    {
        $r = $this->actingAs($this->usuario())->postJson(route('admin.clientes.asistente.store'), $this->datosCompletos(['plantilla' => '']))->assertCreated();

        $r->assertJson(['resumen' => ['servicios_creados' => 4, 'metricas_creadas' => 0, 'keywords_creadas' => 0]]);
        $this->assertSame(0, MetricaCliente::count());
    }

    public function test_el_correo_duplicado_da_422_y_no_crea_nada(): void
    {
        Cliente::factory()->create(['email' => 'laura@hotelprueba.com']);

        $this->actingAs($this->usuario())->postJson(route('admin.clientes.asistente.store'), $this->datosCompletos())
            ->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertSame(1, Cliente::count());
        $this->assertSame(0, Servicio::count());
    }

    public function test_el_correo_de_un_cliente_eliminado_se_puede_reutilizar(): void
    {
        Cliente::factory()->create(['email' => 'laura@hotelprueba.com'])->delete();

        $this->actingAs($this->usuario())->postJson(route('admin.clientes.asistente.store'), $this->datosCompletos())->assertCreated();
    }

    public function test_el_alta_aisla_los_datos_del_cliente_nuevo_de_los_demas(): void
    {
        $otro = Cliente::factory()->create();
        Servicio::factory()->create(['cliente_id' => $otro->id, 'nombre' => 'SEO local', 'tipo' => 'seo']);

        $this->actingAs($this->usuario())->postJson(route('admin.clientes.asistente.store'), $this->datosCompletos())->assertCreated();

        $nuevo = Cliente::where('empresa', 'Hotel Prueba')->sole();
        $this->assertSame(1, Servicio::where('cliente_id', $otro->id)->count(), 'el otro cliente conserva solo lo suyo');
        $this->assertSame(0, MetricaCliente::where('cliente_id', $otro->id)->count());
        $this->assertSame(4, Servicio::where('cliente_id', $nuevo->id)->count());
    }

    // ---- alta rápida ----------------------------------------------------------------

    public function test_alta_rapida_guarda_con_perfil_incompleto(): void
    {
        $r = $this->actingAs($this->usuario())->postJson(route('admin.clientes.asistente.store'), [
            'modo' => 'rapido',
            'nombre_comercial' => 'Taller Rápido',
            'contacto_nombre' => 'Pedro Ruiz',
            // Lo que llegue de los pasos 3 y 4 se ignora en el modo rápido.
            'servicios' => [['tipo' => 'seo', 'nombre' => 'SEO', 'tipo_cobro' => 'mensual', 'precio_mensual' => 5000]],
        ])->assertCreated();

        $r->assertJson(['modo' => 'rapido', 'perfil_completo' => false, 'resumen' => null]);
        $this->assertEqualsCanonicalizing(['Correo', 'WhatsApp', 'Fecha de renovación'], $r->json('faltantes'));

        $cliente = Cliente::sole();
        $this->assertSame('Taller Rápido', $cliente->nombre_comercial);
        $this->assertSame('Pedro Ruiz', $cliente->nombre);
        $this->assertFalse($cliente->perfilCompleto());
        $this->assertSame(0, Servicio::count());
        $this->assertSame(0, MetricaCliente::count());
    }

    public function test_alta_rapida_exige_nombre_comercial_y_contacto(): void
    {
        $u = $this->usuario();

        $this->actingAs($u)->postJson(route('admin.clientes.asistente.store'), ['modo' => 'rapido', 'nombre_comercial' => 'X'])
            ->assertStatus(422)->assertJsonValidationErrors('contacto_nombre');
        $this->actingAs($u)->postJson(route('admin.clientes.asistente.store'), ['modo' => 'rapido', 'contacto_nombre' => 'Y'])
            ->assertStatus(422)->assertJsonValidationErrors('nombre_comercial');
        $this->assertSame(0, Cliente::count());
    }

    public function test_alta_rapida_tambien_rechaza_un_correo_duplicado(): void
    {
        Cliente::factory()->create(['email' => 'a@b.com']);

        $this->actingAs($this->usuario())->postJson(route('admin.clientes.asistente.store'), [
            'modo' => 'rapido', 'nombre_comercial' => 'X', 'contacto_nombre' => 'Y', 'email' => 'a@b.com',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    // ---- atomicidad ------------------------------------------------------------------

    public function test_si_falla_la_plantilla_no_queda_ni_el_cliente(): void
    {
        $this->mock(AplicadorPlantilla::class, fn ($m) => $m->shouldReceive('aplicar')->andThrow(new \RuntimeException('falla simulada')));
        $datos = $this->datosCompletos();
        $datos['logo'] = UploadedFile::fake()->image('logo.png');

        $this->actingAs($this->usuario())->post(route('admin.clientes.asistente.store'), $datos, ['Accept' => 'application/json'])->assertStatus(500);

        $this->assertSame(0, Cliente::count());
        $this->assertSame(0, Servicio::count());
        $this->assertSame(0, IdentidadMarca::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
