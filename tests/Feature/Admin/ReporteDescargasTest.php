<?php

namespace Tests\Feature\Admin;

use App\Enums\AreaReporte;
use App\Enums\EstadoReporte;
use App\Models\Cliente;
use App\Models\Reporte;
use App\Models\ReporteEntrega;
use App\Models\User;
use App\Services\Reportes\Armador;
use App\Support\ClienteActivo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Descargas del editor de reportes: el reporte vacío o con todo oculto no debe
 * romper la generación (la sospecha de la auditoría), la marcación en la vista
 * que usa el manejador de descargas y la precarga del cliente activo.
 */
class ReporteDescargasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        // El folio lleva el año: se fija para que el test no caduque.
        Carbon::setTestNow(Carbon::create(2026, 9, 1, 12, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function reporte(array $overrides = []): Reporte
    {
        return Reporte::create(array_merge([
            'cliente_id' => Cliente::factory()->create()->id,
            'area' => AreaReporte::Seo->value,
            'titulo' => 'Reporte de prueba',
            'periodo_inicio' => '2026-08-01',
            'periodo_fin' => '2026-08-31',
            'estado' => EstadoReporte::Borrador->value,
        ], $overrides));
    }

    private function seccion(Reporte $reporte, array $overrides = [])
    {
        return $reporte->secciones()->create(array_merge([
            'tipo' => 'texto',
            'titulo' => 'Alcance',
            'orden' => 1,
            'contenido' => ['formato' => 'pares', 'bloques' => [['titulo' => 'Sitio', 'cuerpo' => 'ejemplo.com']]],
            'visible' => true,
        ], $overrides));
    }

    private function bytesDe(Reporte $reporte, string $formato): string
    {
        $entrega = ReporteEntrega::where('reporte_id', $reporte->id)->where('formato', $formato)->firstOrFail();

        return Storage::disk('local')->get($entrega->archivo->ruta_archivo);
    }

    public function test_un_reporte_sin_secciones_genera_pdf_con_folio_y_entrega(): void
    {
        $reporte = $this->reporte();

        $this->assertSame([], app(Armador::class)->armar($reporte)['secciones']);

        $this->actingAs(User::factory()->create())->post(route('admin.reportes.pdf', $reporte))->assertOk();

        $reporte->refresh();
        $this->assertSame('REP-SEO-2026-0001', $reporte->numero);
        $this->assertSame(EstadoReporte::Listo, $reporte->estado);
        $this->assertSame(1, ReporteEntrega::where('reporte_id', $reporte->id)->where('formato', 'pdf')->count());
        $this->assertSame('%PDF', substr($this->bytesDe($reporte, 'pdf'), 0, 4));
    }

    public function test_un_reporte_sin_secciones_genera_xlsx_legible(): void
    {
        $reporte = $this->reporte();

        $respuesta = $this->actingAs(User::factory()->create())->post(route('admin.reportes.xlsx', $reporte));

        $respuesta->assertOk();
        $this->assertSame(1, ReporteEntrega::where('reporte_id', $reporte->id)->where('formato', 'xlsx')->count());
        $this->assertNotNull($reporte->refresh()->numero);

        $temporal = tempnam(sys_get_temp_dir(), 'rep_vacio_').'.xlsx';
        file_put_contents($temporal, $this->bytesDe($reporte, 'xlsx'));
        try {
            $this->assertGreaterThanOrEqual(1, IOFactory::load($temporal)->getSheetCount());
        } finally {
            @unlink($temporal);
        }
    }

    public function test_un_reporte_con_todas_las_secciones_ocultas_genera_pdf_y_xlsx(): void
    {
        $reporte = $this->reporte();
        $this->seccion($reporte, ['visible' => false]);
        $this->seccion($reporte, ['visible' => false, 'titulo' => 'Otra', 'orden' => 2]);

        $this->assertSame([], app(Armador::class)->armar($reporte)['secciones']);

        $user = User::factory()->create();
        $this->actingAs($user)->post(route('admin.reportes.pdf', $reporte))->assertOk();
        $this->actingAs($user)->post(route('admin.reportes.xlsx', $reporte))->assertOk();

        $this->assertSame('%PDF', substr($this->bytesDe($reporte, 'pdf'), 0, 4));
        $this->assertSame(2, ReporteEntrega::where('reporte_id', $reporte->id)->count());
    }

    public function test_un_reporte_con_datos_genera_pdf_con_las_cabeceras_de_descarga(): void
    {
        $reporte = $this->reporte();
        $this->seccion($reporte);

        $respuesta = $this->actingAs(User::factory()->create())->post(route('admin.reportes.pdf', $reporte));

        $respuesta->assertOk();
        // El manejador de descargas valida el tipo y toma el nombre de aquí.
        $this->assertStringContainsString('application/pdf', (string) $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('REP-SEO-2026-0001', (string) $respuesta->headers->get('Content-Disposition'));
    }

    public function test_el_editor_usa_el_manejador_de_descargas_y_no_el_confirm_nativo(): void
    {
        $reporte = $this->reporte();

        $html = $this->actingAs(User::factory()->create())->get(route('admin.reportes.show', $reporte))->assertOk()->getContent();

        // PDF, Excel y la versión para cliente.
        $this->assertSame(3, substr_count($html, 'data-descarga data-descarga-recargar'));
        $this->assertStringNotContainsString('Se generará una entrega nueva', $html);
        $this->assertStringContainsString('id="repDescargaAviso"', $html);
    }

    public function test_el_editor_muestra_el_interruptor_y_la_etiqueta_de_interno(): void
    {
        $reporte = $this->reporte();
        $this->seccion($reporte, ['interno' => true]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.reportes.show', $reporte))
            ->assertOk()
            ->assertSee('Interno (no sale en el PDF del cliente)')
            ->assertSee('data-seccion-interno', false)
            ->assertSee('data-pago-aviso', false)
            ->assertSee('data-interno="1"', false);
    }

    public function test_el_interruptor_de_interno_se_guarda_y_se_valida(): void
    {
        $reporte = $this->reporte();
        $seccion = $this->seccion($reporte);
        $user = User::factory()->create();
        $url = route('admin.reportes.secciones.update', [$reporte, $seccion]);
        $base = ['titulo' => 'Alcance', 'contenido' => $seccion->contenido];

        $this->actingAs($user)->putJson($url, $base + ['interno' => true])
            ->assertOk()->assertJsonPath('interno', true);
        $this->assertTrue($seccion->fresh()->interno);

        // Un autosave de contenido sin el campo no puede quitar la marca.
        $this->actingAs($user)->putJson($url, $base)->assertOk()->assertJsonPath('interno', true);

        $this->actingAs($user)->putJson($url, $base + ['interno' => 'quizas'])->assertStatus(422);
    }

    public function test_el_pdf_y_el_xlsx_no_llevan_las_secciones_internas(): void
    {
        $reporte = $this->reporte();
        $this->seccion($reporte);
        $this->seccion($reporte, [
            'titulo' => 'Cuenta de anuncios interna',
            'orden' => 2,
            'interno' => true,
            'contenido' => ['formato' => 'pares', 'bloques' => [['titulo' => 'Tarjeta', 'cuerpo' => 'terminada en 4242 SALDO-SECRETO']]],
        ]);

        $vistas = [];
        view()->creator('pdf.reporte', function ($vista) use (&$vistas) {
            $vistas[] = $vista->getData();
        });

        $user = User::factory()->create();
        $this->actingAs($user)->post(route('admin.reportes.pdf', $reporte))->assertOk();
        $this->actingAs($user)->post(route('admin.reportes.xlsx', $reporte))->assertOk();

        $this->assertNotEmpty($vistas);
        foreach ($vistas as $datos) {
            $this->assertSame(['Alcance'], array_column($datos['secciones'], 'titulo'));
            $this->assertStringNotContainsString('SALDO-SECRETO', json_encode($datos['secciones']));
        }

        $temporal = tempnam(sys_get_temp_dir(), 'rep_int_').'.xlsx';
        file_put_contents($temporal, $this->bytesDe($reporte, 'xlsx'));
        try {
            $libro = IOFactory::load($temporal);
            $texto = '';
            foreach ($libro->getAllSheets() as $hoja) {
                $texto .= $hoja->getTitle().' '.json_encode($hoja->toArray(null, false, false, false));
            }
            $this->assertStringNotContainsString('SALDO-SECRETO', $texto);
            $this->assertStringNotContainsString('Cuenta de anuncios interna', $texto);
            $this->assertStringContainsString('ejemplo.com', $texto);
        } finally {
            @unlink($temporal);
        }
    }

    public function test_la_seccion_interna_sigue_visible_en_el_editor(): void
    {
        $reporte = $this->reporte();
        $this->seccion($reporte, ['titulo' => 'Notas del equipo', 'interno' => true]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.reportes.show', $reporte))
            ->assertOk()
            ->assertSee('Notas del equipo')
            ->assertSee('ejemplo.com');
    }

    // --- 9c · «Nuevo reporte» precarga el cliente activo --------------------

    public function test_nuevo_reporte_preselecciona_el_cliente_activo(): void
    {
        $a = Cliente::factory()->create(['nombre' => 'Aaa']);
        $b = Cliente::factory()->create(['nombre' => 'Bbb']);

        $html = $this->actingAs(User::factory()->create())
            ->withSession([ClienteActivo::CLAVE => $b->id])
            ->get(route('admin.reportes.index'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<option value="'.$b->id.'"\s+selected/', $html);
        $this->assertDoesNotMatchRegularExpression('/<option value="'.$a->id.'"\s+selected/', $html);
    }

    public function test_nuevo_reporte_sin_cliente_activo_no_preselecciona_nada(): void
    {
        $cliente = Cliente::factory()->create();

        $html = $this->actingAs(User::factory()->create())
            ->get(route('admin.reportes.index'))
            ->assertOk()
            ->getContent();

        $this->assertDoesNotMatchRegularExpression('/<option value="'.$cliente->id.'"\s+selected/', $html);
    }

    public function test_el_modal_de_programados_preselecciona_el_cliente_activo(): void
    {
        $cliente = Cliente::factory()->create();

        $html = $this->actingAs(User::factory()->create())
            ->withSession([ClienteActivo::CLAVE => $cliente->id])
            ->get(route('admin.reportes.programados.index'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<option value="'.$cliente->id.'"\s+selected/', $html);
    }

    public function test_los_placeholders_del_editor_no_parecen_contenido_real(): void
    {
        $reporte = $this->reporte();
        $this->seccion($reporte);

        $html = $this->actingAs(User::factory()->create())->get(route('admin.reportes.show', $reporte))->assertOk()->getContent();

        $this->assertStringNotContainsString('placeholder="SECCIÓN 2"', $html);
        $this->assertStringContainsString('placeholder="Rótulo de la sección (opcional)"', $html);
        $this->assertStringNotContainsString('Search Console no ha refrescado', $html);
        $this->assertStringContainsString('Aviso de dato desactualizado', $html);
    }
}
