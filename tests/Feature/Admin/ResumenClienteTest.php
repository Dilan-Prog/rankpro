<?php

namespace Tests\Feature\Admin;

use App\Models\AdsCampana;
use App\Models\AdsClic;
use App\Models\AdsMetrica;
use App\Models\Archivo;
use App\Models\Cliente;
use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Models\KeywordMedicion;
use App\Models\Permission;
use App\Models\Reporte;
use App\Models\Role;
use App\Models\SeoCampana;
use App\Models\SeoMetricaMensual;
use App\Models\Servicio;
use App\Models\User;
use App\Support\ClienteActivo;
use App\Support\CommandPaletteIndex;
use App\Support\ResumenAgencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pantalla «Resumen de cliente» (admin.clientes.show): ficha mínima + tablero. El cálculo por bloque se prueba
 * sin HTTP en Tests\Unit\Support\Tablero\ResumenClienteTest; aquí van la respuesta, el aislamiento, los
 * permisos por bloque, el cliente activo, la cota de consultas y que los enlaces que ya apuntaban a la ficha sigan sirviendo.
 */
class ResumenClienteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function usuarioConPermisos(array $modulos): User
    {
        config(['permisos.aplicar' => true]);
        $rol = Role::query()->create(['name' => 'rol'.uniqid(), 'label' => 'Rol']);
        foreach ($modulos as $modulo) {
            $permiso = Permission::query()->firstOrCreate(['name' => $modulo], ['label' => $modulo, 'module' => $modulo]);
            $rol->permissions()->attach($permiso->id);
        }

        return User::factory()->create(['role_id' => $rol->id, 'is_active' => true]);
    }

    private function ver(User $usuario, Cliente $cliente)
    {
        return $this->actingAs($usuario)->get(route('admin.clientes.show', $cliente));
    }

    // --- acceso y ficha ------------------------------------------------------

    public function test_invitado_va_al_login(): void
    {
        $this->get(route('admin.clientes.show', Cliente::factory()->create()))->assertRedirect('/login');
    }

    public function test_muestra_la_ficha_sin_el_marcador_en_desarrollo(): void
    {
        $cliente = Cliente::factory()->create([
            'empresa' => 'Hotel Fratelli', 'nombre' => 'Marco Rossi', 'contacto_nombre' => null,
            'email' => 'marco@fratelli.test', 'telefono' => '449 123 4567',
            'fecha_inicio_contrato' => '2026-01-10', 'fecha_renovacion_contrato' => '2027-01-10',
            'forma_pago' => 'mensual', 'metodo_pago' => 'transferencia',
        ]);

        $this->ver(User::factory()->create(), $cliente)
            ->assertOk()
            ->assertSee('Hotel Fratelli')
            // El contacto es dato secundario: aparece, pero el título es el nombre comercial.
            ->assertSee('Contacto: Marco Rossi')
            ->assertSee('marco@fratelli.test')
            ->assertSee('tel:4491234567', false)
            ->assertSee('10/01/2026')
            ->assertSee('10/01/2027')
            ->assertSee('Mensual')
            ->assertSee('Transferencia')
            ->assertDontSee('está en desarrollo')
            ->assertSee('data-rc-ficha', false)
            ->assertSee('data-rc-tablero', false);
    }

    public function test_cliente_inexistente_o_eliminado_da_404(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario)->get('/admin/clientes/999999')->assertNotFound();

        $borrado = Cliente::factory()->create();
        $borrado->delete();
        $this->actingAs($usuario)->get(route('admin.clientes.show', $borrado->id))->assertNotFound();
    }

    public function test_servicios_muestran_el_mrr_real_y_los_ingresos_por_proyecto_aparte(): void
    {
        $cliente = Cliente::factory()->create();
        Servicio::factory()->create(['cliente_id' => $cliente->id, 'nombre' => 'SEO mensual', 'tipo' => 'seo', 'precio_mensual' => 7000]);
        Servicio::factory()->create(['cliente_id' => $cliente->id, 'nombre' => 'Sitio nuevo', 'tipo' => 'software', 'tipo_cobro' => 'unico', 'precio_mensual' => 30000]);
        Servicio::factory()->create(['cliente_id' => $cliente->id, 'nombre' => 'Servicio pausado', 'estado' => 'pausado', 'precio_mensual' => 2000]);
        // Un servicio de OTRO cliente no entra en ningún total.
        Servicio::factory()->create(['nombre' => 'Servicio ajeno', 'precio_mensual' => 99999]);

        $html = $this->ver(User::factory()->create(), $cliente)->assertOk()->getContent();

        $this->assertStringContainsString('SEO mensual', $html);
        $this->assertStringContainsString('Sitio nuevo', $html);
        $this->assertStringNotContainsString('Servicio ajeno', $html);
        $this->assertMatchesRegularExpression('/data-rc-mrr.*?\$7,000\.00 \/ mes/s', $html);
        $this->assertMatchesRegularExpression('/data-rc-proyectos.*?\$30,000\.00.*?1 servicio de pago único/s', $html);
    }

    public function test_lista_reportes_y_contratos_del_cliente_y_no_los_de_otro(): void
    {
        $cliente = Cliente::factory()->create();
        $otro = Cliente::factory()->create();
        Reporte::create(['cliente_id' => $cliente->id, 'area' => 'seo', 'titulo' => 'Reporte de octubre', 'estado' => 'listo', 'periodo_inicio' => '2026-10-01', 'periodo_fin' => '2026-10-31', 'fecha_emision' => '2026-11-02']);
        Reporte::create(['cliente_id' => $otro->id, 'area' => 'seo', 'titulo' => 'Reporte ajeno', 'estado' => 'listo', 'periodo_inicio' => '2026-10-01', 'periodo_fin' => '2026-10-31', 'fecha_emision' => '2026-11-02']);
        Archivo::create(['cliente_id' => $cliente->id, 'nombre' => 'Contrato 2026.pdf', 'tipo' => 'contrato', 'ruta_archivo' => 'x/contrato.pdf', 'tamano' => 10, 'extension' => 'pdf']);
        Archivo::create(['cliente_id' => $cliente->id, 'nombre' => 'Logo.png', 'tipo' => 'diseno', 'ruta_archivo' => 'x/logo.png', 'tamano' => 10, 'extension' => 'png']);

        $this->ver(User::factory()->create(), $cliente)
            ->assertOk()
            ->assertSee('Reporte de octubre')
            ->assertDontSee('Reporte ajeno')
            ->assertSee('Contrato 2026.pdf')
            ->assertDontSee('Logo.png');
    }

    // --- tablero: sin dato ---------------------------------------------------

    public function test_cliente_sin_datos_pinta_los_bloques_en_neutro_con_su_explicacion(): void
    {
        $cliente = Cliente::factory()->create();

        $html = $this->ver(User::factory()->create(), $cliente)->assertOk()->getContent();

        foreach (['seo', 'keywords', 'google_ads', 'meta_ads', 'conversiones', 'metricas'] as $clave) {
            $this->assertMatchesRegularExpression('/data-bloque="'.$clave.'" data-estado="neutro"/', $html, "{$clave} debería salir neutro");
        }
        $this->assertStringContainsString('data-sin-dato', $html);
        $this->assertStringContainsString('Sin integración', $html);
        // Nada se pinta verde por no tener datos: los verdes del cliente vacío son solo Salud y Alertas, que sí evalúan.
        $this->assertDoesNotMatchRegularExpression('/data-bloque="(seo|keywords|google_ads|meta_ads|conversiones|metricas)" data-estado="verde"/', $html);
    }

    public function test_con_datos_muestra_fecha_y_fuente_de_cada_cifra_y_marca_el_mes_parcial(): void
    {
        $cliente = Cliente::factory()->create();
        $campana = SeoCampana::create([
            'cliente_id' => $cliente->id, 'servicio_id' => Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'seo'])->id,
            'nombre' => 'SEO', 'url_sitio' => 'https://x.test', 'estado' => 'activa', 'fase_actual' => 'ejecucion', 'ciclo_actual' => 1, 'fecha_inicio' => '2026-01-01',
        ]);
        SeoMetricaMensual::create(['seo_campana_id' => $campana->id, 'ciclo' => 1, 'mes' => 10, 'anio' => 2026, 'trafico_organico' => 321]);
        $propiedad = \App\Models\GscPropiedad::create(['cliente_id' => $cliente->id, 'site_url' => 'https://x.test/', 'activa' => true, 'ultima_sync_en' => now(), 'ultimo_ok' => true]);
        \App\Models\GscMetricaMensual::create(['propiedad_id' => $propiedad->id, 'periodo' => now()->startOfMonth(), 'clics' => 12, 'impresiones' => 300, 'ctr' => 0.04, 'posicion_media' => 9.5, 'parcial' => true]);

        $this->ver(User::factory()->create(), $cliente)
            ->assertOk()
            ->assertSee('321')
            ->assertSee('octubre 2026')
            ->assertSee('Captura manual · Módulo SEO', false)
            ->assertSee('data-cifra-fecha', false)
            ->assertSee('data-cifra-fuente', false)
            ->assertSee('data-cifra-parcial', false)
            ->assertSee('Mes parcial');
    }

    // --- aislamiento ---------------------------------------------------------

    public function test_los_datos_de_otro_cliente_no_aparecen_en_la_ficha(): void
    {
        $cliente = Cliente::factory()->create();
        $otro = Cliente::factory()->create(['empresa' => 'Competidor Secreto SA']);
        $campana = AdsCampana::factory()->create(['cliente_id' => $otro->id, 'servicio_id' => Servicio::factory()->create(['cliente_id' => $otro->id])->id, 'nombre' => 'Campaña del otro']);
        AdsMetrica::factory()->create(['ads_campana_id' => $campana->id, 'cliente_id' => $otro->id, 'inversion_real' => 77777.77]);
        AdsClic::create(['cliente_id' => $otro->id, 'visitor_id' => 'v', 'landing_url' => 'https://otro.test']);

        // Solo el contenido de la pantalla: la paleta de comandos del layout lista a todos los clientes por diseño.
        $html = Str::between($this->ver(User::factory()->create(), $cliente)->assertOk()->getContent(), '<div class="rc"', '</main>');

        $this->assertStringNotContainsString('Competidor Secreto', $html);
        $this->assertStringNotContainsString('77,777.77', $html);
        $this->assertMatchesRegularExpression('/data-bloque="google_ads" data-estado="neutro"/', $html);
    }

    // --- cliente activo ------------------------------------------------------

    public function test_trabaja_sobre_el_cliente_de_la_url_y_ofrece_fijarlo_como_activo(): void
    {
        $activo = Cliente::factory()->create(['empresa' => 'Cliente Activo del Menu']);
        $cliente = Cliente::factory()->create(['empresa' => 'Cliente de la URL']);
        $usuario = User::factory()->create();

        $html = $this->withSession([ClienteActivo::CLAVE => $activo->id])->actingAs($usuario)->get(route('admin.clientes.show', $cliente))->assertOk()->getContent();

        $this->assertStringContainsString('<h1 class="page-header__title">Cliente de la URL</h1>', $html);
        $this->assertStringContainsString('data-rc-fijar-activo', $html);
        $this->assertStringContainsString('name="cliente_id" value="'.$cliente->id.'"', $html);
        $this->assertStringContainsString('action="'.route('admin.cliente-activo').'"', $html);
        $this->assertStringNotContainsString('data-rc-activo', $html);
    }

    public function test_fijar_como_activo_reutiliza_el_endpoint_existente_y_la_ficha_lo_reconoce(): void
    {
        $cliente = Cliente::factory()->create();
        $usuario = User::factory()->create();
        $url = route('admin.clientes.show', $cliente);

        $this->actingAs($usuario)->from($url)->post(route('admin.cliente-activo'), ['cliente_id' => $cliente->id])->assertRedirect($url);
        $this->assertSame($cliente->id, ClienteActivo::id());

        $this->get($url)->assertOk()->assertSee('data-rc-activo', false)->assertDontSee('data-rc-fijar-activo', false);
    }

    // --- permisos por bloque -------------------------------------------------

    public function test_un_bloque_sin_permiso_se_oculta(): void
    {
        $cliente = Cliente::factory()->create();
        $soloClientes = $this->usuarioConPermisos(['clientes']);

        $html = $this->ver($soloClientes, $cliente)->assertOk()->getContent();

        $this->assertStringContainsString('data-bloque="salud"', $html);
        foreach (['seo', 'keywords', 'google_ads', 'meta_ads', 'conversiones', 'metricas', 'audiencias', 'contenido'] as $clave) {
            $this->assertStringNotContainsString('data-bloque="'.$clave.'"', $html, "{$clave} no debería verse sin su permiso");
        }
        // Dentro de Salud, lo de Finanzas, Desarrollo y Captura tampoco se muestra.
        $this->assertStringNotContainsString('Pagos vencidos', $html);
        $this->assertStringNotContainsString('data-rc-servicios', $html);
        $this->assertStringNotContainsString('data-rc-reportes', $html);
        $this->assertStringNotContainsString('data-rc-propuestas', $html);
        $this->assertStringNotContainsString('data-rc-archivos', $html);
        $this->assertStringNotContainsString('data-rc-tablero-reportes', $html);
    }

    public function test_con_permiso_de_un_modulo_ese_bloque_aparece_y_los_demas_no(): void
    {
        $cliente = Cliente::factory()->create();
        $usuario = $this->usuarioConPermisos(['clientes', 'seo', 'finanzas', 'reportes']);

        $html = $this->ver($usuario, $cliente)->assertOk()->getContent();

        $this->assertStringContainsString('data-bloque="seo"', $html);
        $this->assertStringContainsString('Pagos vencidos', $html);
        $this->assertStringContainsString('data-rc-servicios', $html);
        $this->assertStringContainsString('data-rc-tablero-reportes', $html);
        $this->assertStringNotContainsString('data-bloque="keywords"', $html);
        $this->assertStringNotContainsString('data-bloque="google_ads"', $html);
    }

    public function test_sin_el_permiso_de_clientes_la_ficha_da_403(): void
    {
        $cliente = Cliente::factory()->create();

        $this->ver($this->usuarioConPermisos(['seo']), $cliente)->assertForbidden();
    }

    // --- rendimiento ---------------------------------------------------------

    private function consultas(Cliente $cliente): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs(User::factory()->create())->get(route('admin.clientes.show', $cliente))->assertOk();
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    }

    /** Un cliente con $n campañas SEO y Ads (ambas plataformas), listas con 5 keywords medidas, clics y reportes. */
    private function clienteConDatos(int $n): Cliente
    {
        $cliente = Cliente::factory()->create();

        for ($i = 0; $i < $n; $i++) {
            $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id]);
            $campana = AdsCampana::factory()->create(['cliente_id' => $cliente->id, 'servicio_id' => $servicio->id, 'plataforma' => $i % 2 ? 'meta_ads' : 'google_ads']);
            AdsMetrica::factory()->create(['ads_campana_id' => $campana->id, 'cliente_id' => $cliente->id, 'mes' => $i + 1, 'anio' => 2026]);
            $seo = SeoCampana::create(['cliente_id' => $cliente->id, 'servicio_id' => $servicio->id, 'nombre' => 'SEO '.$i, 'url_sitio' => 'https://x.test', 'estado' => 'activa', 'fase_actual' => 'ejecucion', 'ciclo_actual' => 1, 'fecha_inicio' => '2026-01-01']);
            SeoMetricaMensual::create(['seo_campana_id' => $seo->id, 'ciclo' => 1, 'mes' => $i + 1, 'anio' => 2026, 'trafico_organico' => 10]);
            $lista = KeywordLista::create(['cliente_id' => $cliente->id, 'nombre' => 'L'.$i, 'canal' => 'seo', 'estado' => 'en_uso']);
            for ($k = 0; $k < 5; $k++) {
                $kw = Keyword::create(['cliente_id' => $cliente->id, 'lista_id' => $lista->id, 'keyword' => "kw {$i} {$k}", 'tipo' => 'principal', 'estado' => 'en_uso']);
                KeywordMedicion::create(['keyword_id' => $kw->id, 'lista_id' => $lista->id, 'fecha' => now()->subDays($k * 5)->toDateString(), 'posicion' => 3 + $k]);
            }
            AdsClic::create(['cliente_id' => $cliente->id, 'visitor_id' => 'v'.$i, 'landing_url' => 'https://x.test']);
            Reporte::create(['cliente_id' => $cliente->id, 'area' => 'seo', 'titulo' => 'R'.$i, 'estado' => 'listo', 'periodo_inicio' => '2026-10-01', 'periodo_fin' => '2026-10-31', 'fecha_emision' => '2026-11-02']);
        }

        return $cliente;
    }

    public function test_las_consultas_no_crecen_con_la_cantidad_de_datos(): void
    {
        $poco = $this->clienteConDatos(2);
        $mucho = $this->clienteConDatos(12);

        $conPoco = $this->consultas($poco);
        $conMucho = $this->consultas($mucho);

        // Mismo número de consultas con 2 o con 12 campañas: ninguna se hace «por campaña», «por keyword» ni «por reporte».
        $this->assertSame($conPoco, $conMucho, "Con 12 campañas hay {$conMucho} consultas y con 2 hay {$conPoco}: hay un N+1");
        $this->assertLessThanOrEqual(90, $conMucho, 'La ficha hace demasiadas consultas en total');
        $this->assertLessThanOrEqual(90, $this->consultas(Cliente::factory()->create()));
    }

    // --- los enlaces que ya apuntaban a la ficha ------------------------------

    public function test_los_siete_enlaces_existentes_a_la_ficha_siguen_resolviendo(): void
    {
        $usuario = User::factory()->create();
        $cliente = Cliente::factory()->create(['estado' => 'activo', 'fecha_renovacion_contrato' => now()->addDays(10)]);
        $url = route('admin.clientes.show', $cliente);
        $this->actingAs($usuario);

        // 1. Lista de clientes (clientes/index).
        $this->get(route('admin.clientes.index'))->assertOk()->assertSee($url, false);
        // 2. Fila que dibuja clientes.js tras crear/editar (la ruta se arma en el navegador).
        $this->assertStringContainsString('/admin/clientes/${client.id}"', file_get_contents(resource_path('js/clientes.js')));
        // 3. Contratos por vencer del Dashboard.
        $this->get(route('admin.dashboard'))->assertOk()->assertSee($url, false);
        // 4. «Ficha del cliente» en el banco de keywords por cliente.
        $this->get(route('admin.keywords.cliente', $cliente))->assertOk()->assertSee($url, false);
        // 5. Panel de notificaciones del encabezado (renovación a 10 días), presente en cualquier página del panel.
        $this->get(route('admin.inicio'))->assertOk()->assertSee($url, false);
        // 6. Paleta de comandos.
        $this->assertContains($url, array_column(CommandPaletteIndex::build()['clientes'], 'url'));
        // 7. Alertas de renovación de Inicio / Dashboard.
        $this->assertContains($url, array_column(ResumenAgencia::alertas(Carbon::now(), null), 'href'));

        // Y el destino de todos ellos responde.
        $this->get($url)->assertOk();
    }
}
