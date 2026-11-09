<?php

namespace Tests\Feature\Admin;

use App\Models\AdsBriefing;
use App\Models\AdsCampana;
use App\Models\AdsConversion;
use App\Models\Cliente;
use App\Models\EventoCalendario;
use App\Models\Finanza;
use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Models\MetricaCliente;
use App\Models\MetricaClienteValor;
use App\Models\Proyecto;
use App\Models\ProyectoPlaneacion;
use App\Models\Reunion;
use App\Models\SeoCampana;
use App\Models\SeoFaseAuditoria;
use App\Models\Servicio;
use App\Models\Tarea;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookEntrega;
use App\Support\ClienteActivo;
use App\Support\CommandPaletteIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * `clientes.nombre` guarda a la persona y `empresa` el nombre comercial. Una auditoría halló que
 * unas pantallas mostraban la empresa y otras el contacto; este barrido fija que el panel
 * entero identifica al cliente por su nombre comercial.
 */
class NombreClienteUnificadoTest extends TestCase
{
    use RefreshDatabase;

    private const EMPRESA = 'Empresa Comercial SA';

    private const CONTACTO = 'Persona Contacto';

    private Cliente $cliente;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');

        $this->usuario = User::factory()->create();
        $this->cliente = Cliente::factory()->create([
            'nombre' => self::CONTACTO,
            'empresa' => self::EMPRESA,
            'contacto_nombre' => null,
            'email' => 'contacto@empresa-comercial.test',
            'fecha_renovacion_contrato' => now()->addDays(12),
        ]);
    }

    /** Un dato de cada módulo, para que las pantallas pinten al cliente en tablas y tarjetas y no solo en selectores. */
    private function datos(): void
    {
        $c = $this->cliente;
        $seo = Servicio::factory()->create(['cliente_id' => $c->id, 'tipo' => 'seo']);
        $ads = Servicio::factory()->create(['cliente_id' => $c->id, 'tipo' => 'google_ads']);

        $campanaSeo = SeoCampana::create(['cliente_id' => $c->id, 'servicio_id' => $seo->id, 'nombre' => 'SEO Prueba', 'url_sitio' => 'https://ejemplo.com',
            'estado' => 'activa', 'fase_actual' => 'auditoria', 'ciclo_actual' => 1, 'fecha_inicio' => now()->subMonth()]);
        SeoFaseAuditoria::create(['seo_campana_id' => $campanaSeo->id, 'ciclo' => 1]);
        $campanaAds = AdsCampana::factory()->create(['cliente_id' => $c->id, 'servicio_id' => $ads->id, 'nombre' => 'Campana Prueba', 'fase_actual' => 'briefing']);
        // La pantalla de detalle lee la fila de cada fase del ciclo.
        $campanaAds->briefings()->create(['ciclo' => 1, 'checklist' => collect(array_keys(AdsBriefing::CHECKLIST))->mapWithKeys(fn ($k) => [$k => false])->all()]);
        $campanaAds->configuraciones()->create(['ciclo' => 1, 'checklist' => []]);
        $campanaAds->lanzamientos()->create(['ciclo' => 1, 'checklist' => []]);
        $campanaAds->reportes()->create(['ciclo' => 1, 'checklist' => []]);
        Finanza::create(['cliente_id' => $c->id, 'concepto' => 'Minuta', 'tipo' => 'ingreso', 'monto' => 1000, 'estado' => 'vencido',
            'fecha_vencimiento' => now()->subDays(3)->toDateString(), 'mes' => (int) now()->month, 'anio' => (int) now()->year]);
        $lista = KeywordLista::create(['cliente_id' => $c->id, 'nombre' => 'Lista SEO', 'canal' => 'seo', 'estado' => 'en_uso']);
        Keyword::create(['cliente_id' => $c->id, 'lista_id' => $lista->id, 'keyword' => 'hotel en prueba', 'tipo' => 'principal', 'estado' => 'seguimiento']);
        AdsConversion::create(['cliente_id' => $c->id, 'visitor_id' => 'v1', 'tipo' => 'whatsapp', 'valor' => 10]);
        $proyecto = Proyecto::create(['cliente_id' => $c->id, 'nombre' => 'Sitio nuevo', 'tipo' => 'web_nueva', 'fase_actual' => 'planeacion', 'estado' => 'activo']);
        $proyecto->planeacion()->create(['checklist' => collect(array_keys(ProyectoPlaneacion::CHECKLIST))->mapWithKeys(fn ($k) => [$k => false])->all()]);
        MetricaCliente::create(['cliente_id' => $c->id, 'nombre' => 'Llamadas', 'unidad' => 'llamadas', 'meta' => 10, 'sentido' => 'mayor', 'umbral_amarillo_pct' => 80]);
        Reunion::factory()->create(['cliente_id' => $c->id, 'inicia_en' => now()->addDay()->setTime(10, 0), 'termina_en' => now()->addDay()->setTime(10, 30)]);
        Tarea::create(['user_id' => $this->usuario->id, 'cliente_id' => $c->id, 'titulo' => 'Llamar', 'prioridad' => 'media', 'estado' => 'pendiente', 'orden' => 0,
            'fecha_limite' => now()->addDay()->toDateString()]);
        EventoCalendario::create(['titulo' => 'Reunion con cliente', 'tipo' => 'otro', 'fecha' => now()->toDateString(), 'cliente_id' => $c->id, 'user_id' => $this->usuario->id]);
    }

    private function visitar(string $ruta, array $params = []): string
    {
        $respuesta = $this->actingAs($this->usuario)
            ->withSession([ClienteActivo::CLAVE => $this->cliente->id])
            ->get(route($ruta, $params));
        $this->assertSame(200, $respuesta->getStatusCode(), "{$ruta} respondió ".$respuesta->getStatusCode().": ".($respuesta->exception?->getMessage() ?? ""));

        $html = (string) $respuesta->getContent();
        // Texto de búsqueda oculto, no visible: el índice de la paleta lleva la persona para encontrar al cliente,
        // y el JSON `data-lista`/`data-keyword` del banco de keywords comparte con la API el campo `cliente` (la persona).
        $html = preg_replace('#<script id="agencyos-search-index".*?</script>#s', '', $html);

        return (string) preg_replace('#data-(lista|keyword)="[^"]*"#', '', $html);
    }

    public function test_las_pantallas_del_panel_identifican_al_cliente_por_su_nombre_comercial(): void
    {
        $this->datos();
        $c = $this->cliente;
        $campanaAds = AdsCampana::firstOrFail();
        $campanaSeo = SeoCampana::firstOrFail();
        $proyecto = Proyecto::firstOrFail();
        $metrica = MetricaCliente::firstOrFail();

        $pantallas = [
            'CRM' => ['admin.clientes.index', []],
            'Ficha del cliente' => ['admin.clientes.show', [$c]],
            'Servicios' => ['admin.servicios.index', []],
            'Finanzas' => ['admin.finanzas.index', []],
            'Dashboard' => ['admin.dashboard', []],
            'Inicio' => ['admin.inicio', []],
            'Ads' => ['admin.ads.index', []],
            'Ads detalle' => ['admin.ads.show', [$campanaAds]],
            'SEO' => ['admin.seo.index', []],
            'SEO detalle' => ['admin.seo.show', [$campanaSeo]],
            'Keywords' => ['admin.keywords.index', []],
            'Keywords del cliente' => ['admin.keywords.cliente', [$c]],
            'Conversiones' => ['admin.conversiones.index', []],
            'Calendario' => ['admin.calendario.index', []],
            'Desarrollo' => ['admin.desarrollo.index', []],
            'Desarrollo detalle' => ['admin.desarrollo.show', [$proyecto]],
            'Integraciones' => ['admin.integraciones.index', []],
            'Integraciones del cliente' => ['admin.clientes.integraciones', [$c]],
            'Clics del cliente' => ['admin.clientes.clics', [$c]],
            'Conversiones del cliente' => ['admin.clientes.conversiones', [$c]],
            'Embudo del cliente' => ['admin.clientes.conversiones.embudo', [$c]],
            'Search Console' => ['admin.integraciones.search-console.index', []],
            'Metricas' => ['admin.metricas.index', ['cliente' => $c->id]],
            'Metrica detalle' => ['admin.metricas.show', [$metrica]],
            'Reportes' => ['admin.reportes.index', []],
            'Reportes programados' => ['admin.reportes.programados.index', []],
            'Tablero de reportes' => ['admin.reportes.tablero.index', ['cliente' => $c->id]],
            'Propuestas' => ['admin.propuestas.index', []],
            'Agenda' => ['admin.agenda.index', []],
            'Mi espacio' => ['admin.mi-espacio.index', []],
            'Contenido' => ['admin.contenido.index', []],
            'Identidad' => ['admin.identidad.index', []],
            'Audiencia' => ['admin.audiencia.index', []],
            'SEO Plan' => ['admin.seo-plan.index', []],
            'Archivos' => ['admin.archivos.index', []],
            'Captura' => ['admin.captura.index', []],
            'Automatizaciones' => ['admin.automatizaciones.create', []],
        ];

        // Las únicas pantallas donde la persona sí se muestra, como dato secundario «Contacto».
        $conContactoSecundario = ['CRM', 'Ficha del cliente'];

        $fallos = [];

        foreach ($pantallas as $nombre => [$ruta, $params]) {
            $html = $this->visitar($ruta, $params);

            if (! str_contains($html, self::EMPRESA)) {
                $fallos[] = "{$nombre}: no muestra el nombre comercial";
            }

            $muestraContacto = str_contains($html, self::CONTACTO);
            if (in_array($nombre, $conContactoSecundario, true) && ! $muestraContacto) {
                $fallos[] = "{$nombre}: debe mostrar el contacto como dato secundario";
            }
            if (! in_array($nombre, $conContactoSecundario, true) && $muestraContacto) {
                $fallos[] = "{$nombre}: muestra al contacto como identidad del cliente";
            }
        }

        $this->assertSame([], $fallos);
    }

    public function test_el_crm_muestra_el_contacto_como_dato_secundario_y_la_empresa_como_principal(): void
    {
        $html = $this->visitar('admin.clientes.index');

        $this->assertMatchesRegularExpression('#font-weight:500">\s*'.preg_quote(self::EMPRESA, '#').'\s*</div>#', $html);
        $this->assertMatchesRegularExpression('#<td>\s*<div>'.preg_quote(self::CONTACTO, '#').'</div>#', $html);

        $this->actingAs($this->usuario)->get(route('admin.clientes.show', $this->cliente))
            ->assertSee('Contacto: '.self::CONTACTO);
    }

    public function test_el_crm_prefiere_contacto_nombre_como_contacto_secundario(): void
    {
        $this->cliente->update(['contacto_nombre' => 'Gerente Ventas']);

        $html = $this->visitar('admin.clientes.index');

        $this->assertStringContainsString('<div>Gerente Ventas</div>', $html);
    }

    public function test_cliente_sin_empresa_se_muestra_por_su_nombre(): void
    {
        $this->cliente->update(['empresa' => '   ']);

        $html = $this->visitar('admin.clientes.index');

        $this->assertStringContainsString(self::CONTACTO, $html);
        $this->assertStringNotContainsString(self::EMPRESA, $html);
    }

    public function test_los_exportes_nombran_el_archivo_con_el_nombre_comercial(): void
    {
        AdsConversion::create(['cliente_id' => $this->cliente->id, 'visitor_id' => 'v1', 'tipo' => 'whatsapp', 'valor' => 10]);

        $respuesta = $this->actingAs($this->usuario)->post(route('admin.clientes.conversiones.exportar', $this->cliente));

        $respuesta->assertOk();
        $disposicion = (string) $respuesta->headers->get('Content-Disposition');
        $this->assertStringContainsString('empresa-comercial-sa', $disposicion);
        $this->assertStringNotContainsString('persona-contacto', $disposicion);
    }

    public function test_la_paleta_de_comandos_muestra_la_empresa_y_busca_tambien_por_contacto(): void
    {
        $this->cliente->update(['contacto_nombre' => 'Gerente Ventas']);
        $this->actingAs($this->usuario);

        $clientes = collect(CommandPaletteIndex::build()['clientes']);
        $fila = $clientes->firstWhere('label', self::EMPRESA);

        $this->assertNotNull($fila, 'La paleta debe etiquetar al cliente con su empresa.');
        $this->assertStringContainsString(self::CONTACTO, $fila['busqueda']);
        $this->assertStringContainsString('Gerente Ventas', $fila['busqueda']);
        $this->assertNull($clientes->firstWhere('label', self::CONTACTO));
    }

    public function test_los_eventos_de_calendario_llevan_el_nombre_comercial(): void
    {
        $this->datos();

        $this->actingAs($this->usuario)
            ->getJson(route('admin.calendario.eventos', ['desde' => now()->startOfMonth()->subWeek()->toDateString(), 'hasta' => now()->endOfMonth()->addWeek()->toDateString()]))
            ->assertOk()
            ->assertSee(self::EMPRESA)
            ->assertDontSee(self::CONTACTO);
    }

    // --- API v1 y webhooks: cambio aditivo ------------------------------------

    public function test_la_api_de_clientes_agrega_nombre_comercial_sin_quitar_nombre(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);

        $this->getJson("/api/v1/clientes/{$this->cliente->id}")
            ->assertOk()
            ->assertJsonPath('data.nombre', self::CONTACTO)
            ->assertJsonPath('data.empresa', self::EMPRESA)
            ->assertJsonPath('data.nombre_comercial', self::EMPRESA);

        $lista = $this->getJson('/api/v1/clientes')->assertOk();
        $this->assertSame(self::EMPRESA, $lista->json('data.0.nombre_comercial'));
        $this->assertSame(self::CONTACTO, $lista->json('data.0.nombre'));
    }

    public function test_la_api_de_keywords_conserva_cliente_y_agrega_el_nombre_comercial(): void
    {
        $this->datos();
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);

        $fila = $this->getJson('/api/v1/keywords')->assertOk()->json('data.0');

        $this->assertSame(self::CONTACTO, $fila['cliente']);
        $this->assertSame(self::EMPRESA, $fila['cliente_nombre_comercial']);
    }

    public function test_el_webhook_de_cliente_creado_lleva_nombre_comercial_y_los_campos_de_siempre(): void
    {
        Webhook::factory()->create(['eventos' => ['cliente.creado']]);

        $nuevo = Cliente::factory()->create(['nombre' => 'Otra Persona', 'empresa' => 'Otra Empresa SA']);

        $datos = WebhookEntrega::where('evento', 'cliente.creado')->firstOrFail()->payload['datos'];
        $this->assertSame($nuevo->id, $datos['cliente_id']);
        $this->assertSame('Otra Persona', $datos['nombre']);
        $this->assertSame('Otra Empresa SA', $datos['empresa']);
        $this->assertSame('Otra Empresa SA', $datos['nombre_comercial']);
    }

    public function test_el_webhook_de_metricas_conserva_cliente_y_agrega_el_nombre_comercial(): void
    {
        Webhook::factory()->create(['eventos' => ['metrica.valor_registrado']]);
        $metrica = MetricaCliente::create(['cliente_id' => $this->cliente->id, 'nombre' => 'Llamadas', 'unidad' => 'llamadas', 'meta' => 100, 'sentido' => 'mayor', 'umbral_amarillo_pct' => 80]);

        MetricaClienteValor::create(['metrica_id' => $metrica->id, 'periodo' => '2026-10-01', 'valor' => 95]);

        $datos = WebhookEntrega::where('evento', 'metrica.valor_registrado')->firstOrFail()->payload['datos'];
        $this->assertSame(self::CONTACTO, $datos['cliente']);
        $this->assertSame(self::EMPRESA, $datos['cliente_nombre_comercial']);
    }
}
