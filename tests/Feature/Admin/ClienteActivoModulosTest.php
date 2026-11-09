<?php

namespace Tests\Feature\Admin;

use App\Enums\EstadoReporte;
use App\Models\AdsConversion;
use App\Models\AutomatizacionProyecto;
use App\Models\Cliente;
use App\Models\EventoCalendario;
use App\Models\Finanza;
use App\Models\GscPropiedad;
use App\Models\Reporte;
use App\Models\ReporteProgramacion;
use App\Models\Reunion;
use App\Models\Servicio;
use App\Models\User;
use App\Support\ClienteActivo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * El cliente activo (selector del menú y de la barra superior) acota las listas de Conversiones, Servicios,
 * Finanzas, Reportes, Programados, Automatizaciones, Captura, Integraciones, Search Console, Agenda y Calendario,
 * con el mismo aviso y «Ver todos». Archivos solo lo usa como valor por defecto.
 *
 * Los nombres de los clientes salen en los selectores de toda página, así que se comprueba con datos propios
 * de cada fila (títulos, conceptos) o con los datos que el controlador pasa a la vista.
 */
class ClienteActivoModulosTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $a;

    private Cliente $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->a = Cliente::factory()->create(['empresa' => 'Alfa Corp', 'estado' => 'activo']);
        $this->b = Cliente::factory()->create(['empresa' => 'Beta Corp', 'estado' => 'activo']);
    }

    /** Con A activo. */
    private function conActivo(string $ruta, array $parametros = [])
    {
        return $this->actingAs(User::factory()->create())
            ->withSession(['cliente_activo_id' => $this->a->id])
            ->get(route($ruta, $parametros));
    }

    /** Sin cliente activo. */
    private function sinActivo(string $ruta, array $parametros = [])
    {
        // La sesión de prueba sobrevive entre peticiones del mismo test: sin vaciarla seguiría el activo anterior.
        $this->flushSession();

        return $this->actingAs(User::factory()->create())->get(route($ruta, $parametros));
    }

    private function avisoVisible($respuesta): void
    {
        $respuesta->assertSee('Mostrando solo a')->assertSee('Ver todos los clientes');
    }

    // --- Conversiones ---

    public function test_conversiones_filtra_por_cliente_activo(): void
    {
        AdsConversion::create(['cliente_id' => $this->a->id, 'visitor_id' => 'va', 'tipo' => 'whatsapp', 'valor' => 10]);
        AdsConversion::create(['cliente_id' => $this->b->id, 'visitor_id' => 'vb', 'tipo' => 'whatsapp', 'valor' => 20]);

        $con = $this->conActivo('admin.conversiones.index')->assertOk();
        $this->assertSame([$this->a->id], $con->viewData('conversiones')->pluck('cliente_id')->unique()->values()->all());
        $this->avisoVisible($con);

        $sin = $this->sinActivo('admin.conversiones.index')->assertOk()->assertDontSee('Mostrando solo a');
        $this->assertCount(2, $sin->viewData('conversiones'));
    }

    public function test_conversiones_un_cliente_id_explicito_manda_sobre_el_activo(): void
    {
        AdsConversion::create(['cliente_id' => $this->a->id, 'visitor_id' => 'va', 'tipo' => 'whatsapp']);
        AdsConversion::create(['cliente_id' => $this->b->id, 'visitor_id' => 'vb', 'tipo' => 'whatsapp']);

        $r = $this->conActivo('admin.conversiones.index', ['cliente_id' => $this->b->id])->assertOk()->assertDontSee('Mostrando solo a');
        $this->assertSame([$this->b->id], $r->viewData('conversiones')->pluck('cliente_id')->unique()->values()->all());
    }

    // --- Servicios ---

    public function test_servicios_filtra_por_cliente_activo(): void
    {
        Servicio::factory()->create(['cliente_id' => $this->a->id, 'nombre' => 'Servicio de Alfa']);
        Servicio::factory()->create(['cliente_id' => $this->b->id, 'nombre' => 'Servicio de Beta']);

        $con = $this->conActivo('admin.servicios.index')->assertOk()->assertSee('Servicio de Alfa')->assertDontSee('Servicio de Beta');
        $this->avisoVisible($con);

        $this->sinActivo('admin.servicios.index')->assertSee('Servicio de Alfa')->assertSee('Servicio de Beta')->assertDontSee('Mostrando solo a');
    }

    // --- Finanzas ---

    private function finanza(Cliente $cliente, string $concepto): Finanza
    {
        return Finanza::create([
            'cliente_id' => $cliente->id, 'concepto' => $concepto, 'tipo' => 'ingreso', 'monto' => 100, 'estado' => 'pendiente',
            'fecha_emision' => now()->toDateString(), 'mes' => now()->month, 'anio' => now()->year,
        ]);
    }

    public function test_finanzas_filtra_por_cliente_activo(): void
    {
        $this->finanza($this->a, 'Minuta de Alfa');
        $this->finanza($this->b, 'Minuta de Beta');

        $con = $this->conActivo('admin.finanzas.index')->assertOk()->assertSee('Minuta de Alfa')->assertDontSee('Minuta de Beta');
        $this->avisoVisible($con);

        $this->sinActivo('admin.finanzas.index')->assertSee('Minuta de Alfa')->assertSee('Minuta de Beta')->assertDontSee('Mostrando solo a');
    }

    // --- Reportes y programados ---

    private function reporte(Cliente $cliente, string $titulo): Reporte
    {
        return Reporte::create([
            'cliente_id' => $cliente->id, 'area' => 'seo', 'titulo' => $titulo,
            'periodo_inicio' => '2026-08-01', 'periodo_fin' => '2026-08-31', 'estado' => EstadoReporte::Borrador->value,
        ]);
    }

    public function test_reportes_filtra_por_cliente_activo(): void
    {
        $this->reporte($this->a, 'Informe de Alfa');
        $this->reporte($this->b, 'Informe de Beta');

        $con = $this->conActivo('admin.reportes.index')->assertOk()->assertSee('Informe de Alfa')->assertDontSee('Informe de Beta');
        $this->avisoVisible($con);

        $this->sinActivo('admin.reportes.index')->assertSee('Informe de Alfa')->assertSee('Informe de Beta')->assertDontSee('Mostrando solo a');
    }

    public function test_reportes_programados_filtra_por_cliente_activo(): void
    {
        ReporteProgramacion::factory()->create(['cliente_id' => $this->a->id, 'destinatarios' => ['alfa@example.com']]);
        ReporteProgramacion::factory()->create(['cliente_id' => $this->b->id, 'destinatarios' => ['beta@example.com']]);

        $con = $this->conActivo('admin.reportes.programados.index')->assertOk()->assertSee('alfa@example.com')->assertDontSee('beta@example.com');
        $this->avisoVisible($con);

        $this->sinActivo('admin.reportes.programados.index')->assertSee('alfa@example.com')->assertSee('beta@example.com')->assertDontSee('Mostrando solo a');
    }

    // --- Automatizaciones ---

    private function proyectoAutomatizacion(Cliente $cliente, string $nombre): AutomatizacionProyecto
    {
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'automatizacion']);

        return AutomatizacionProyecto::create([
            'cliente_id' => $cliente->id, 'servicio_id' => $servicio->id, 'nombre' => $nombre,
            'estado' => 'activa', 'fase_actual' => 'diagnostico', 'ciclo_actual' => 1,
        ]);
    }

    public function test_automatizaciones_filtra_por_cliente_activo(): void
    {
        $this->proyectoAutomatizacion($this->a, 'Flujo de Alfa');
        $this->proyectoAutomatizacion($this->b, 'Flujo de Beta');

        $con = $this->conActivo('admin.automatizaciones.index')->assertOk()->assertSee('Flujo de Alfa')->assertDontSee('Flujo de Beta');
        $this->avisoVisible($con);

        $this->sinActivo('admin.automatizaciones.index')->assertSee('Flujo de Alfa')->assertSee('Flujo de Beta')->assertDontSee('Mostrando solo a');
    }

    // --- Captura ---

    public function test_captura_filtra_por_cliente_activo(): void
    {
        $con = $this->conActivo('admin.captura.index')->assertOk();
        $this->assertSame([$this->a->id], collect($con->viewData('tablero')['filas'])->map(fn ($f) => $f['cliente']->id)->all());
        $this->avisoVisible($con);

        $sin = $this->sinActivo('admin.captura.index')->assertOk()->assertDontSee('Mostrando solo a');
        $this->assertCount(2, $sin->viewData('tablero')['filas']);
    }

    // --- Integraciones y Search Console ---

    public function test_integraciones_filtra_por_cliente_activo(): void
    {
        $con = $this->conActivo('admin.integraciones.index')->assertOk();
        $this->assertSame([$this->a->id], $con->viewData('clientes')->pluck('cliente_id')->all());
        $this->avisoVisible($con);

        $sin = $this->sinActivo('admin.integraciones.index')->assertOk()->assertDontSee('Mostrando solo a');
        $this->assertCount(2, $sin->viewData('clientes'));
    }

    public function test_search_console_filtra_por_cliente_activo(): void
    {
        GscPropiedad::create(['cliente_id' => $this->a->id, 'site_url' => 'sc-domain:alfa.com']);
        GscPropiedad::create(['cliente_id' => $this->b->id, 'site_url' => 'sc-domain:beta.com']);

        $con = $this->conActivo('admin.integraciones.search-console.index')->assertOk();
        $this->assertSame([$this->a->id], $con->viewData('clientes')->pluck('id')->all());
        $this->assertSame([$this->a->id], $con->viewData('propiedades')->keys()->all());
        $this->avisoVisible($con);

        $sin = $this->sinActivo('admin.integraciones.search-console.index')->assertOk()->assertDontSee('Mostrando solo a');
        $this->assertCount(2, $sin->viewData('propiedades'));
    }

    // --- Agenda y Calendario ---

    public function test_agenda_filtra_las_reuniones_por_cliente_activo(): void
    {
        Reunion::factory()->create(['cliente_id' => $this->a->id, 'nombre' => 'Reunion con Alfa']);
        Reunion::factory()->create(['cliente_id' => $this->b->id, 'nombre' => 'Reunion con Beta']);

        $con = $this->conActivo('admin.agenda.index')->assertOk();
        $this->assertSame([$this->a->id], $con->viewData('reuniones')->pluck('cliente_id')->all());
        $this->avisoVisible($con);

        $sin = $this->sinActivo('admin.agenda.index')->assertOk()->assertDontSee('Mostrando solo a');
        $this->assertCount(2, $sin->viewData('reuniones'));
    }

    public function test_calendario_filtra_eventos_con_cliente_pero_conserva_los_de_la_agencia(): void
    {
        EventoCalendario::create(['titulo' => 'Evento de Alfa', 'tipo' => 'reunion', 'fecha' => '2026-11-10', 'cliente_id' => $this->a->id]);
        EventoCalendario::create(['titulo' => 'Evento de Beta', 'tipo' => 'reunion', 'fecha' => '2026-11-10', 'cliente_id' => $this->b->id]);
        EventoCalendario::create(['titulo' => 'Evento de la agencia', 'tipo' => 'reunion', 'fecha' => '2026-11-10']);

        $pagina = $this->conActivo('admin.calendario.index', ['mes' => '2026-11'])->assertOk()
            ->assertSee('Evento de Alfa')->assertSee('Evento de la agencia')->assertDontSee('Evento de Beta');
        $this->avisoVisible($pagina);
        $pagina->assertSee('sin cliente (de la agencia)');

        // El JSON que usa el JS al cambiar de mes aplica el mismo filtro.
        $json = $this->actingAs(User::factory()->create())->withSession(['cliente_activo_id' => $this->a->id])
            ->getJson(route('admin.calendario.eventos', ['desde' => '2026-10-26', 'hasta' => '2026-12-06']))->assertOk();
        $this->assertEqualsCanonicalizing(['Evento de Alfa', 'Evento de la agencia'], collect($json->json('eventos'))->pluck('titulo')->all());

        $this->sinActivo('admin.calendario.index', ['mes' => '2026-11'])
            ->assertSee('Evento de Alfa')->assertSee('Evento de Beta')->assertSee('Evento de la agencia')->assertDontSee('Mostrando solo a');
    }

    // --- Archivos: el activo es solo el valor por defecto ---

    public function test_archivos_usa_el_cliente_activo_como_valor_por_defecto_sin_forzarlo(): void
    {
        $this->assertSame($this->b->id, $this->actingAs(User::factory()->create())->withSession(['cliente_activo_id' => $this->b->id])
            ->get(route('admin.archivos.index'))->assertOk()->viewData('clienteSeleccionado'));

        // Un ?cliente= explícito (su propio selector) gana sobre el activo.
        $this->assertSame($this->a->id, $this->actingAs(User::factory()->create())->withSession(['cliente_activo_id' => $this->b->id])
            ->get(route('admin.archivos.index', ['cliente' => $this->a->id]))->viewData('clienteSeleccionado'));
    }

    // --- Barra superior y rendimiento ---

    public function test_la_barra_superior_muestra_el_nombre_comercial_y_el_boton_para_limpiar(): void
    {
        $con = $this->conActivo('admin.servicios.index')->assertOk();
        $this->assertMatchesRegularExpression('#class="topbar__cliente\s+is-activo\s*"#', $con->getContent());
        $con->assertSee('topbar__cliente-limpiar', false);
        $this->assertMatchesRegularExpression('#<option value="'.$this->a->id.'" selected>Alfa Corp</option>#', $con->getContent());

        $this->sinActivo('admin.servicios.index')->assertDontSee('topbar__cliente-limpiar', false)->assertDontSee('is-activo', false)->assertSee('Todos los clientes');
    }

    public function test_el_boton_todos_los_clientes_limpia_la_sesion(): void
    {
        $this->actingAs(User::factory()->create())->withSession(['cliente_activo_id' => $this->a->id])
            ->post(route('admin.cliente-activo'), ['cliente_id' => ''])->assertRedirect();

        $this->assertNull(session('cliente_activo_id'));
    }

    public function test_cliente_activo_se_resuelve_una_vez_por_peticion(): void
    {
        Cliente::factory()->count(25)->create();
        session(['cliente_activo_id' => $this->a->id]);

        DB::enableQueryLog();
        for ($i = 0; $i < 30; $i++) {
            $this->assertSame($this->a->id, ClienteActivo::id());
        }
        $consultas = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from `clientes`'))->count();
        DB::disableQueryLog();

        $this->assertSame(1, $consultas);
    }

    public function test_una_pagina_no_hace_una_consulta_de_cliente_por_opcion_del_selector(): void
    {
        Cliente::factory()->count(25)->create();

        DB::enableQueryLog();
        $this->conActivo('admin.servicios.index')->assertOk();
        $porId = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from `clientes` where `clientes`.`id` = ?'))->count();
        DB::disableQueryLog();

        // 27 clientes en el selector (menú y barra): sin memo habrían sido decenas de búsquedas por id.
        $this->assertLessThanOrEqual(3, $porId);
    }
}
