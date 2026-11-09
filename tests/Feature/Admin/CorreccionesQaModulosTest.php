<?php

namespace Tests\Feature\Admin;

use App\Models\AdsCampana;
use App\Models\AdsMetrica;
use App\Models\Articulo;
use App\Models\AutomatizacionFlujo;
use App\Models\AutomatizacionProyecto;
use App\Models\Cliente;
use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Models\SeoCampana;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Regresiones de la auditoría QA (ingreso de Ads, MRR de SEO, conteos de
 * Automatizaciones, importación de keywords, búsqueda de Clientes, etc.).
 */
class CorreccionesQaModulosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $this->actingAs($u);

        return $u;
    }

    private function campanaAds(Cliente $cliente): AdsCampana
    {
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'google_ads']);
        $campana = AdsCampana::create([
            'cliente_id' => $cliente->id, 'servicio_id' => $servicio->id, 'nombre' => 'Camp', 'plataforma' => 'google_ads',
            'objetivo' => 'ventas', 'presupuesto_mensual' => 5000, 'estado' => 'activa', 'fase_actual' => 'briefing', 'ciclo_actual' => 1,
        ]);
        $campana->briefings()->create(['ciclo' => 1, 'checklist' => []]);
        $campana->configuraciones()->create(['ciclo' => 1, 'checklist' => []]);
        $campana->lanzamientos()->create(['ciclo' => 1, 'checklist' => []]);
        $campana->reportes()->create(['ciclo' => 1, 'checklist' => []]);

        return $campana;
    }

    // ---- 4) ingreso atribuido de Ads --------------------------------------

    public function test_ingreso_atribuido_pondera_por_inversion_y_usa_valor_conversion(): void
    {
        $this->admin();
        $cliente = Cliente::factory()->create();
        $campana = $this->campanaAds($cliente);
        $base = ['ads_campana_id' => $campana->id, 'cliente_id' => $cliente->id, 'anio' => 2026];

        // Mes sin valor_conversion: inversión x roas = 100 x 10 = 1,000.
        AdsMetrica::create($base + ['mes' => 1, 'inversion_real' => 100, 'roas' => 10]);
        // Mes grande: 10,000 x 1 = 10,000 (el promedio simple de roas daba 5.5x).
        AdsMetrica::create($base + ['mes' => 2, 'inversion_real' => 10000, 'roas' => 1]);
        // Con valor_conversion manda éste, no el roas capturado.
        AdsMetrica::create($base + ['mes' => 3, 'inversion_real' => 100, 'roas' => 2, 'valor_conversion' => 500]);
        // Sin ningún dato de ingreso: no suma ingreso ni entra al denominador.
        AdsMetrica::create($base + ['mes' => 4, 'inversion_real' => 400]);

        $fila = $this->get(route('admin.ads.index'))->viewData('campanas')->first();

        $this->assertSame(11500.0, $fila['ingreso_atribuido']);
        $this->assertSame(10200.0, $fila['inversion_con_ingreso']);
        $this->assertSame(round(11500 / 10200, 2), $fila['roas']);
        $this->assertSame(10600.0, $fila['inversion']);
    }

    public function test_campana_sin_metricas_tiene_roas_cero_sin_dividir_entre_cero(): void
    {
        $this->admin();
        $this->campanaAds(Cliente::factory()->create());

        $fila = $this->get(route('admin.ads.index'))->viewData('campanas')->first();
        $this->assertSame(0.0, $fila['roas']);
        $this->assertSame(0.0, $fila['ingreso_atribuido']);
    }

    // ---- 10) conversiones en Ads show --------------------------------------

    public function test_ads_show_etiqueta_las_conversiones_como_del_cliente(): void
    {
        $this->admin();
        $campana = $this->campanaAds(Cliente::factory()->create());
        $campana->update(['fase_actual' => 'lanzamiento']);

        $this->get(route('admin.ads.show', $campana))
            ->assertOk()
            ->assertSee('Conversiones del cliente', false)
            ->assertSee('de todas sus campañas', false);
    }

    // ---- 6) MRR de SEO solo con servicios activos ---------------------------

    public function test_seo_mrr_cuenta_solo_servicios_activos(): void
    {
        $this->admin();
        $cliente = Cliente::factory()->create();
        Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'seo', 'estado' => 'activo', 'precio_mensual' => 1000]);
        Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'seo', 'estado' => 'pausado', 'precio_mensual' => 700]);
        Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'seo', 'estado' => 'cancelado', 'precio_mensual' => 500]);
        Servicio::factory()->unico()->create(['cliente_id' => $cliente->id, 'tipo' => 'seo', 'estado' => 'activo', 'precio_mensual' => 6000]);
        Servicio::factory()->gratuito()->create(['cliente_id' => $cliente->id, 'tipo' => 'seo', 'estado' => 'activo']);

        $fila = $this->get(route('admin.seo.index'))->viewData('clientes')->firstWhere('cliente_id', $cliente->id);

        $this->assertSame(1000.0, $fila['mrr']);
    }

    // ---- 7) Automatizaciones ------------------------------------------------

    private function proyectoAutomatizacion(array $overrides = []): AutomatizacionProyecto
    {
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'automatizacion']);
        $this->post('/admin/automatizaciones', ['cliente_id' => $cliente->id, 'servicio_id' => $servicio->id, 'nombre' => 'P'.uniqid()])->assertRedirect();
        $p = AutomatizacionProyecto::latest('id')->first();
        if ($overrides) {
            $p->forceFill($overrides)->save();
        }

        return $p->fresh();
    }

    public function test_automatizaciones_en_proceso_cuenta_solo_activos(): void
    {
        $this->admin();
        $this->proyectoAutomatizacion();
        $this->proyectoAutomatizacion(['estado' => 'pausada']);
        $this->proyectoAutomatizacion(['estado' => 'finalizada', 'fase_actual' => 'cerrada']);

        $this->assertSame(1, $this->get(route('admin.automatizaciones.index'))->viewData('enProceso'));
    }

    public function test_automatizacion_update_exige_coherencia_estado_fase(): void
    {
        $this->admin();
        $cerrado = $this->proyectoAutomatizacion(['estado' => 'finalizada', 'fase_actual' => 'cerrada']);
        $abierto = $this->proyectoAutomatizacion();

        $payload = fn ($p, $estado) => ['cliente_id' => $p->cliente_id, 'servicio_id' => $p->servicio_id, 'nombre' => $p->nombre, 'estado' => $estado];

        $this->put(route('admin.automatizaciones.update', $cerrado), $payload($cerrado, 'activa'))->assertSessionHasErrors('estado');
        $this->put(route('admin.automatizaciones.update', $abierto), $payload($abierto, 'finalizada'))->assertSessionHasErrors('estado');
        $this->put(route('admin.automatizaciones.update', $abierto), $payload($abierto, 'pausada'))->assertSessionDoesntHaveErrors();
        $this->assertSame('pausada', $abierto->fresh()->estado->value);
    }

    public function test_seo_update_exige_coherencia_estado_fase(): void
    {
        $this->admin();
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'seo']);
        $this->postJson('/admin/seo', ['cliente_id' => $cliente->id, 'servicio_id' => $servicio->id, 'nombre' => 'C'])->assertStatus(201);
        $campana = SeoCampana::latest('id')->first();
        $payload = fn ($estado) => ['cliente_id' => $cliente->id, 'servicio_id' => $servicio->id, 'nombre' => 'C', 'estado' => $estado];

        $this->putJson(route('admin.seo.update', $campana), $payload('finalizada'))->assertStatus(422)->assertJsonValidationErrors('estado');

        $campana->forceFill(['fase_actual' => 'cerrada', 'estado' => 'finalizada'])->save();
        $this->putJson(route('admin.seo.update', $campana), $payload('activa'))->assertStatus(422)->assertJsonValidationErrors('estado');
    }

    // ---- 11) reporte de automatización: calculado junto al capturado -------

    public function test_reporte_automatizacion_muestra_calculado_de_flujos_junto_al_capturado(): void
    {
        $this->admin();
        $p = $this->proyectoAutomatizacion(['fase_actual' => 'reporte']);
        $base = ['proyecto_id' => $p->id, 'tipo' => 'crm', 'complejidad' => 'basico'];
        AutomatizacionFlujo::create($base + ['nombre' => 'A', 'estado' => 'activo', 'horas_ahorradas_mes' => 4.5]);
        AutomatizacionFlujo::create($base + ['nombre' => 'B', 'estado' => 'activo', 'horas_ahorradas_mes' => 2]);
        AutomatizacionFlujo::create($base + ['nombre' => 'C', 'estado' => 'pausado', 'horas_ahorradas_mes' => 10]);
        $p->reportes()->first()->update(['flujos_activos_total' => 5, 'horas_ahorradas_mes' => 6.5]);

        $html = $this->get(route('admin.automatizaciones.show', $p))->assertOk()->getContent();

        $this->assertStringContainsString('Según la tabla de flujos: 2 activo(s).', $html);
        $this->assertStringContainsString('6.5 h/mes.', $html);
        $this->assertStringContainsString('No coincide con lo capturado.', $html); // 5 capturados vs 2 calculados
    }

    // ---- 8) importación de keywords -----------------------------------------

    private function lista(): KeywordLista
    {
        return KeywordLista::create(['cliente_id' => Cliente::factory()->create()->id, 'nombre' => 'L', 'canal' => 'seo', 'estado' => 'en_uso']);
    }

    public function test_importar_dos_veces_no_duplica_y_reporta_duplicadas(): void
    {
        $this->admin();
        $lista = $this->lista();
        $texto = "dentista cdmx, 8100\northodoncia, 2200";

        $this->postJson(route('admin.keywords.listas.importar', $lista), ['texto' => $texto])->assertCreated()->assertJsonPath('creadas', 2)->assertJsonPath('duplicadas', 0);
        $this->postJson(route('admin.keywords.listas.importar', $lista), ['texto' => $texto."\nDentista CDMX\nnueva"])
            ->assertCreated()->assertJsonPath('creadas', 1)->assertJsonPath('duplicadas', 3);

        $this->assertSame(3, Keyword::where('lista_id', $lista->id)->count());
    }

    public function test_importar_solo_duplicadas_responde_200_no_error(): void
    {
        $this->admin();
        $lista = $this->lista();
        $this->postJson(route('admin.keywords.listas.importar', $lista), ['texto' => 'uno'])->assertCreated();

        $this->postJson(route('admin.keywords.listas.importar', $lista), ['texto' => 'uno'])->assertOk()->assertJsonPath('creadas', 0)->assertJsonPath('duplicadas', 1);
    }

    public function test_importar_csv_de_excel_con_bom_y_punto_y_coma(): void
    {
        $this->admin();
        $lista = $this->lista();
        $csv = "\xEF\xBB\xBFdentista cdmx;8100;42;45.20;transaccional;/dentista\r\northodoncia;2200;45;38.00;informacional;/orto\r\n";
        $archivo = UploadedFile::fake()->createWithContent('keywords.csv', $csv);

        $this->post(route('admin.keywords.listas.importar', $lista), ['archivo' => $archivo], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('creadas', 2)->assertJsonPath('errores', []);

        $this->assertDatabaseHas('keywords', ['lista_id' => $lista->id, 'keyword' => 'dentista cdmx', 'volumen_busqueda' => 8100, 'dificultad' => 42]);
    }

    public function test_importar_con_tabulador(): void
    {
        $this->admin();
        $lista = $this->lista();

        $this->postJson(route('admin.keywords.listas.importar', $lista), ['texto' => "alfa\t100\t10\nbeta\t200\t20"])
            ->assertCreated()->assertJsonPath('creadas', 2);
        $this->assertDatabaseHas('keywords', ['keyword' => 'beta', 'volumen_busqueda' => 200]);
    }

    // ---- 9) búsqueda de Clientes --------------------------------------------

    public function test_data_search_de_clientes_incluye_nombre_y_correo(): void
    {
        $this->admin();
        Cliente::factory()->create(['nombre' => 'Nombre Unico', 'empresa' => 'Empresa XYZ', 'contacto_nombre' => 'Contacto Q', 'email' => 'buscame@ejemplo.com']);

        $html = $this->get(route('admin.clientes.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-search="[^"]*nombre unico[^"]*"/', $html);
        $this->assertMatchesRegularExpression('/data-search="[^"]*buscame@ejemplo\.com[^"]*"/', $html);
    }

    // ---- 5) Blog sin paginación en servidor ---------------------------------

    public function test_indice_del_blog_lista_todos_los_articulos_para_que_el_filtro_cliente_los_vea(): void
    {
        $user = $this->admin();
        Articulo::factory()->count(30)->create(['autor_id' => $user->id]);

        $html = $this->get('/admin/blog')->assertOk()->getContent();

        $this->assertSame(30, substr_count($html, '<tr data-blog-row'));
    }
}
