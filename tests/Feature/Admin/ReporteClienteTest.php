<?php

namespace Tests\Feature\Admin;

use App\Enums\AreaReporte;
use App\Enums\EstadoReporte;
use App\Enums\TipoSeccion;
use App\Models\Cliente;
use App\Models\Reporte;
use App\Models\ReporteEntrega;
use App\Models\ReporteSeccion;
use App\Models\User;
use App\Services\Reportes\Armador;
use App\Services\Reportes\RenderizadorXlsx;
use App\Support\Reportes\AvisoSeccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Sección `resumen_cliente` en sus tres salidas (editor, PDF, XLSX), la versión para
 * cliente de 1 página como entregable aparte, y el aviso de «dato desactualizado» con
 * fecha ISO real. Lo que no debe ocurrir nunca: que una sección interna llegue al cliente.
 */
class ReporteClienteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
        Carbon::setTestNow(Carbon::create(2026, 9, 15, 12, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function reporte(array $extra = []): Reporte
    {
        return Reporte::create($extra + [
            'cliente_id' => Cliente::factory()->create(['empresa' => 'Hotel Fratelli'])->id,
            'area' => AreaReporte::Seo->value,
            'origen' => 'mes',
            'titulo' => 'Reporte SEO — Hotel Fratelli — Agosto 2026',
            'periodo_inicio' => '2026-08-01',
            'periodo_fin' => '2026-08-31',
            'estado' => EstadoReporte::Borrador->value,
            'numero' => 'REP-SEO-2026-0001',
            'notas_alcance' => 'NOTA-INTERNA-DE-ALCANCE',
        ]);
    }

    /** @param  array<string, mixed>  $extra */
    private function resumen(Reporte $reporte, array $extra = [], array $contenido = []): ReporteSeccion
    {
        return $reporte->secciones()->create($extra + [
            'tipo' => TipoSeccion::ResumenCliente->value,
            'titulo' => 'Resumen para el cliente',
            'orden' => 1,
            'visible' => true,
            'contenido' => $contenido + [
                'logros' => [['texto' => 'El tráfico orgánico subió 20,0% frente a julio 2026 (de 1,000 a 1,200).', 'precargada' => true], ['texto' => 'Dos keywords entraron al top 3.']],
                'retos' => [['texto' => 'Los clics bajaron 10,0%; hay que revisar qué cambió.']],
                'proximos_pasos' => [['texto' => 'Optimizar las páginas de habitaciones.']],
                'grafica' => [
                    'titulo' => 'Tráfico orgánico y clics',
                    'etiqueta_x' => 'Mes',
                    'series' => [['clave' => 'trafico', 'titulo' => 'Tráfico'], ['clave' => 'clics', 'titulo' => 'Clics']],
                    'filas' => [
                        ['x' => 'jul 2026', 'valores' => ['trafico' => 1000, 'clics' => 200]],
                        ['x' => 'ago 2026', 'valores' => ['trafico' => 1200]],
                    ],
                ],
            ],
        ]);
    }

    private function interna(Reporte $reporte, string $texto = 'SECRETO-INTERNO-TARJETA'): ReporteSeccion
    {
        return $reporte->secciones()->create([
            'tipo' => 'texto', 'titulo' => 'Datos de pago', 'orden' => 2, 'visible' => true, 'interno' => true,
            'contenido' => ['formato' => 'pares', 'bloques' => [['titulo' => 'Tarjeta', 'cuerpo' => $texto]]],
        ]);
    }

    // --- resumen_cliente: editor / PDF / XLSX ----------------------------

    public function test_el_editor_pinta_la_seccion_resumen_con_sus_tres_listas(): void
    {
        $reporte = $this->reporte();
        $this->resumen($reporte);

        $html = $this->actingAs(User::factory()->create())->get(route('admin.reportes.show', $reporte))->assertOk()->getContent();

        $this->assertStringContainsString('data-editor="resumen_cliente"', $html);
        foreach (['logros', 'retos', 'proximos_pasos'] as $lista) {
            $this->assertStringContainsString('data-lista="'.$lista.'"', $html);
        }
        $this->assertStringContainsString('Resumen para el cliente', $html);
    }

    public function test_el_autosave_del_resumen_limita_logros_retos_y_pasos(): void
    {
        $user = User::factory()->create();
        $reporte = $this->reporte();
        $seccion = $this->resumen($reporte);
        $url = route('admin.reportes.secciones.update', ['reporte' => $reporte, 'seccion' => $seccion]);
        $base = $seccion->contenido;

        $cuatro = array_fill(0, 4, ['texto' => 'x']);
        $this->actingAs($user)->putJson($url, ['titulo' => 'R', 'contenido' => ['logros' => $cuatro] + $base])->assertUnprocessable()->assertJsonValidationErrors('contenido.logros');
        $this->actingAs($user)->putJson($url, ['titulo' => 'R', 'contenido' => ['retos' => $cuatro] + $base])->assertUnprocessable()->assertJsonValidationErrors('contenido.retos');
        $this->actingAs($user)->putJson($url, ['titulo' => 'R', 'contenido' => ['proximos_pasos' => array_fill(0, 6, ['texto' => 'x'])] + $base])->assertUnprocessable();

        $this->actingAs($user)->putJson($url, ['titulo' => 'R', 'contenido' => ['logros' => array_fill(0, 3, ['texto' => 'x'])] + $base])->assertOk();
    }

    public function test_el_resumen_sale_en_el_pdf_completo_con_sus_logros_y_retos(): void
    {
        $reporte = $this->reporte();
        $this->resumen($reporte);

        $armado = app(Armador::class)->armar($reporte);
        $this->assertSame('resumen_cliente', $armado['secciones'][0]['tipo']);
        $this->assertCount(2, $armado['secciones'][0]['derivados']['logros']);
        // La gráfica: el mes sin la segunda serie no dibuja barra (altura null), no una de cero.
        $barras = $armado['secciones'][0]['derivados']['grafica']['barras'];
        $this->assertNotNull($barras[0]['valores']['clics']['altura']);
        $this->assertNull($barras[1]['valores']['clics']['altura']);

        $html = $this->actingAs(User::factory()->create())->get(route('admin.reportes.preview', $reporte))->assertOk()->getContent();
        $this->assertStringContainsString('Dos keywords entraron al top 3.', html_entity_decode($html));
        $this->assertStringContainsString('LOGROS DEL MES', html_entity_decode($html));

        $this->actingAs(User::factory()->create())->post(route('admin.reportes.pdf', $reporte))->assertOk();
        $this->assertSame(1, ReporteEntrega::where('reporte_id', $reporte->id)->where('formato', 'pdf')->count());
    }

    public function test_el_resumen_sale_en_el_xlsx(): void
    {
        $reporte = $this->reporte();
        $this->resumen($reporte);

        $temporal = tempnam(sys_get_temp_dir(), 'rep_test_').'.xlsx';
        file_put_contents($temporal, (new RenderizadorXlsx)->generar($reporte));
        $libro = IOFactory::load($temporal);
        @unlink($temporal);

        $hoja = $libro->getSheetByName('Resumen para el cliente');
        $this->assertNotNull($hoja);
        $this->assertSame('Logro 1', $hoja->getCell('A2')->getValue());
        $this->assertStringContainsString('subió 20,0%', $hoja->getCell('B2')->getValue());
        $this->assertSame('Reto 1', $hoja->getCell('A4')->getValue());
        $this->assertSame('Próximo paso 1', $hoja->getCell('A5')->getValue());
        $this->assertStringContainsString('Tráfico: 1,200', $hoja->getCell('B7')->getValue());
        // La serie sin dato de clics en agosto dice n/d, no 0.
        $this->assertStringContainsString('Clics: n/d', $hoja->getCell('B7')->getValue());
    }

    public function test_una_seccion_interna_no_sale_en_ninguna_salida_del_resumen_ni_de_la_version_cliente(): void
    {
        $reporte = $this->reporte();
        $this->resumen($reporte);
        $this->interna($reporte);
        $user = User::factory()->create();

        $this->assertStringNotContainsString('SECRETO-INTERNO', $this->actingAs($user)->get(route('admin.reportes.preview', $reporte))->getContent());
        $this->assertStringNotContainsString('SECRETO-INTERNO', $this->actingAs($user)->get(route('admin.reportes.preview-cliente', $reporte))->getContent());

        $temporal = tempnam(sys_get_temp_dir(), 'rep_test_').'.xlsx';
        file_put_contents($temporal, (new RenderizadorXlsx)->generar($reporte));
        $libro = IOFactory::load($temporal);
        @unlink($temporal);
        $this->assertNull($libro->getSheetByName('Datos de pago'));
    }

    public function test_un_resumen_marcado_interno_no_cuenta_como_version_para_cliente(): void
    {
        $reporte = $this->reporte();
        $this->resumen($reporte, ['interno' => true]);

        $this->assertNull(app(Armador::class)->armarCliente($reporte)['resumen']);
        $this->actingAs(User::factory()->create())->get(route('admin.reportes.preview-cliente', $reporte))->assertUnprocessable();
    }

    public function test_la_version_cliente_sale_de_una_lista_blanca_sin_datos_internos(): void
    {
        $reporte = $this->reporte();
        $this->resumen($reporte);
        $this->interna($reporte);
        $reporte->secciones()->create(['tipo' => 'texto', 'titulo' => 'Alcance', 'orden' => 3, 'visible' => true, 'contenido' => ['formato' => 'pares', 'bloques' => [['titulo' => 'x', 'cuerpo' => 'CUERPO-OTRA-SECCION']]]]);

        $datos = app(Armador::class)->armarCliente($reporte);

        $this->assertSame(['titulo', 'numero', 'area_label', 'cliente_nombre', 'periodo_label', 'fecha_emision'], array_keys($datos['reporte']));
        $this->assertSame(['reporte', 'resumen'], array_keys($datos));
        $json = json_encode($datos);
        foreach (['NOTA-INTERNA-DE-ALCANCE', 'SECRETO-INTERNO', 'CUERPO-OTRA-SECCION', 'notas_alcance', 'seo_campana'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $json);
        }
        $this->assertSame('Hotel Fratelli', $datos['reporte']['cliente_nombre']);
    }

    // --- versión para cliente (entregable aparte) ------------------------

    public function test_el_pdf_para_cliente_es_un_entregable_aparte_de_una_pagina_con_su_propia_version(): void
    {
        $reporte = $this->reporte();
        $this->resumen($reporte);
        $user = User::factory()->create();

        $r1 = $this->actingAs($user)->post(route('admin.reportes.pdf-cliente', $reporte))->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $r1->headers->get('Content-Type'));
        $this->assertStringContainsString('-cliente-v1.pdf', (string) $r1->headers->get('Content-Disposition'));
        $this->assertStringContainsString('REP-SEO-2026-0001', (string) $r1->headers->get('Content-Disposition'));
        $this->actingAs($user)->post(route('admin.reportes.pdf-cliente', $reporte))->assertOk();
        $this->actingAs($user)->post(route('admin.reportes.pdf', $reporte))->assertOk();

        $entregas = ReporteEntrega::where('reporte_id', $reporte->id)->orderBy('id')->get();
        $this->assertSame(['pdf_cliente', 'pdf_cliente', 'pdf'], $entregas->pluck('formato')->all());
        // Cada formato lleva su serie de versiones: el PDF completo es v1 aunque ya hubo dos del cliente.
        $this->assertSame([1, 2, 1], $entregas->pluck('version')->all());

        $archivo = $entregas->first()->archivo;
        $this->assertStringEndsWith('-cliente-v1.pdf', $archivo->nombre);
        $this->assertStringContainsString('hotel-fratelli_2026-08_REP-SEO-2026-0001', $archivo->nombre);
        $this->assertNotSame($entregas->last()->archivo->ruta_archivo, $archivo->ruta_archivo);

        $bytes = Storage::disk('local')->get($archivo->ruta_archivo);
        $this->assertSame('%PDF', substr($bytes, 0, 4));
        $this->assertMatchesRegularExpression('/\/Count 1\b/', $bytes, 'la versión para cliente cabe en una página');
        $this->assertSame(EstadoReporte::Listo, $reporte->fresh()->estado);
    }

    public function test_sin_resumen_no_hay_version_cliente_y_no_se_gasta_nada(): void
    {
        $reporte = $this->reporte();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('admin.reportes.pdf-cliente', $reporte))->assertUnprocessable()
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Resumen para el cliente'));
        $this->actingAs($user)->get(route('admin.reportes.preview-cliente', $reporte))->assertUnprocessable();

        $fresco = $reporte->fresh();
        $this->assertSame(EstadoReporte::Borrador, $fresco->estado);
        $this->assertSame(0, $fresco->entregas()->count());
    }

    public function test_la_vista_previa_para_cliente_muestra_logo_folio_y_el_resumen(): void
    {
        $reporte = $this->reporte();
        $this->resumen($reporte);

        $html = html_entity_decode($this->actingAs(User::factory()->create())->get(route('admin.reportes.preview-cliente', $reporte))->assertOk()->getContent());

        $this->assertStringContainsString('FOLIO REP-SEO-2026-0001', $html);
        $this->assertStringContainsString('Hotel Fratelli', $html);
        $this->assertStringContainsString('Dos keywords entraron al top 3.', $html);
        $this->assertStringContainsString('PRÓXIMOS PASOS', $html);
    }

    public function test_el_resumen_nuevo_de_un_reporte_del_mes_nace_con_las_sugerencias(): void
    {
        $user = User::factory()->create();
        $id = $this->actingAs($user)->postJson(route('admin.reportes.generar-mes'), [
            'cliente_id' => Cliente::factory()->create()->id, 'area' => 'seo', 'mes' => '2026-08',
        ])->json('reporte.id');
        $reporte = Reporte::findOrFail($id);
        $reporte->secciones()->where('tipo', 'resumen_cliente')->delete();

        $r = $this->actingAs($user)->postJson(route('admin.reportes.secciones.store', $reporte), ['tipo' => 'resumen_cliente', 'titulo' => 'Resumen'])->assertCreated();

        $this->assertSame('resumen_cliente', $r->json('tipo'));
        $this->assertArrayHasKey('grafica', $r->json('contenido'));
        $this->assertSame('resumen', ReporteSeccion::find($r->json('id'))->precarga['clave']);
    }

    // --- aviso de dato desactualizado -------------------------------------

    public function test_el_aviso_exige_fecha_iso_y_se_imprime_con_etiqueta_fija(): void
    {
        $user = User::factory()->create();
        $reporte = $this->reporte();
        $seccion = $reporte->secciones()->create(['tipo' => 'texto', 'titulo' => 'Alcance', 'orden' => 1, 'visible' => true, 'contenido' => ['formato' => 'pares', 'bloques' => []]]);
        $url = route('admin.reportes.secciones.update', ['reporte' => $reporte, 'seccion' => $seccion]);
        $cuerpo = fn (array $aviso) => ['titulo' => 'Alcance', 'aviso' => $aviso, 'contenido' => ['formato' => 'pares', 'bloques' => []]];

        $this->actingAs($user)->putJson($url, $cuerpo(['texto' => 'Sin consolidar', 'fecha' => '18 ago 2026']))->assertUnprocessable()->assertJsonValidationErrors('aviso.fecha');
        $this->actingAs($user)->putJson($url, $cuerpo(['texto' => 'Sin consolidar', 'fecha' => '2026-02-30']))->assertUnprocessable();

        $this->actingAs($user)->putJson($url, $cuerpo(['texto' => 'Search Console no consolidó el último día', 'fecha' => '2026-08-18']))
            ->assertOk()->assertJsonPath('aviso.fecha', '2026-08-18');

        $html = html_entity_decode($this->actingAs($user)->get(route('admin.reportes.preview', $reporte))->getContent());
        $this->assertStringContainsString('DATO DESACTUALIZADO', $html);
        $this->assertStringContainsString('Search Console no consolidó el último día', $html);
        $this->assertStringContainsString('18/08/2026', $html);

        // Sin fecha también vale: el campo es opcional.
        $this->actingAs($user)->putJson($url, $cuerpo(['texto' => 'Solo texto', 'fecha' => null]))->assertOk()->assertJsonPath('aviso.fecha', null);
    }

    public function test_el_aviso_con_fecha_libre_se_conserva_como_texto(): void
    {
        $this->assertSame(['texto' => 'Sin consolidar (18 ago 2026)', 'fecha' => null], AvisoSeccion::normalizar(['texto' => 'Sin consolidar.', 'fecha' => '18 ago 2026']));
        $this->assertSame(['texto' => 'X', 'fecha' => '2026-08-18'], AvisoSeccion::normalizar(['texto' => 'X', 'fecha' => '2026-08-18']));
        $this->assertNull(AvisoSeccion::normalizar(['texto' => '  ', 'fecha' => '2026-08-18']));
        $this->assertSame('18/08/2026', AvisoSeccion::fechaVisible('2026-08-18'));
        $this->assertSame('', AvisoSeccion::fechaVisible('18 ago 2026'));
    }

    public function test_la_migracion_convierte_los_avisos_existentes_sin_perder_texto(): void
    {
        $reporte = $this->reporte();
        $libre = $reporte->secciones()->create(['tipo' => 'texto', 'titulo' => 'A', 'orden' => 1, 'visible' => true, 'contenido' => ['formato' => 'pares', 'bloques' => []]]);
        $iso = $reporte->secciones()->create(['tipo' => 'texto', 'titulo' => 'B', 'orden' => 2, 'visible' => true, 'contenido' => ['formato' => 'pares', 'bloques' => []]]);
        DB::table('reporte_secciones')->where('id', $libre->id)->update(['aviso' => json_encode(['texto' => 'Dato viejo', 'fecha' => '31 ago 2026'])]);
        DB::table('reporte_secciones')->where('id', $iso->id)->update(['aviso' => json_encode(['texto' => 'Dato bueno', 'fecha' => '2026-08-31'])]);

        (require database_path('migrations/2026_11_10_200200_normalizar_fecha_aviso_en_reporte_secciones.php'))->up();

        $this->assertEquals(['texto' => 'Dato viejo (31 ago 2026)', 'fecha' => null], $libre->fresh()->aviso);
        $this->assertEquals(['texto' => 'Dato bueno', 'fecha' => '2026-08-31'], $iso->fresh()->aviso);
    }
}
