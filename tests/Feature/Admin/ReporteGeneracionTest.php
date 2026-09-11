<?php

namespace Tests\Feature\Admin;

use App\Enums\AreaReporte;
use App\Enums\EstadoReporte;
use App\Enums\TipoArchivo;
use App\Enums\TipoSeccion;
use App\Models\Archivo;
use App\Models\Cliente;
use App\Models\Reporte;
use App\Models\ReporteEntrega;
use App\Models\User;
use App\Services\Reportes\Armador;
use App\Support\Reportes\EsquemaSeccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

/**
 * Generación de entregables: PDF y XLSX, folio, versionado del histórico y
 * paso de borrador a listo (ReporteGeneracionController + PublicadorReporte).
 *
 * Aquí vive además la prueba que cierra el contrato del módulo: el número que
 * calcula el Armador —el que imprime el PDF— y el que produce la FÓRMULA del
 * XLSX tienen que ser el mismo, modo de total a modo de total y también en los
 * casos degenerados. Si esos dos divergen, el cliente recibe dos documentos
 * que se contradicen.
 */
class ReporteGeneracionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        // El folio lleva el año dentro: se fija para que el test no caduque.
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
            'titulo' => 'Reporte mensual de agosto',
            'periodo_inicio' => '2026-08-01',
            'periodo_fin' => '2026-08-31',
            'estado' => EstadoReporte::Borrador->value,
        ], $overrides));
    }

    /** Reporte con contenido real en tres secciones visibles y una oculta. */
    private function reporteConSecciones(array $overrides = []): Reporte
    {
        $reporte = $this->reporte($overrides);

        $reporte->secciones()->create([
            'tipo' => 'texto',
            'titulo' => 'Alcance del reporte',
            'orden' => 1,
            'contenido' => [
                'formato' => 'pares',
                'bloques' => [['titulo' => 'Sitio', 'cuerpo' => 'ejemplo.com']],
            ],
            'visible' => true,
        ]);

        $tabla = EsquemaSeccion::vacio(TipoSeccion::Tabla);
        $tabla['columnas'] = [
            ['clave' => 'consulta', 'titulo' => 'Consulta', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
            ['clave' => 'clics', 'titulo' => 'Clics', 'tipo' => 'numero', 'alineacion' => 'derecha'],
            ['clave' => 'impresiones', 'titulo' => 'Impresiones', 'tipo' => 'numero', 'alineacion' => 'derecha'],
        ];
        $tabla['filas'] = [
            ['valores' => ['consulta' => 'dentista cdmx', 'clics' => 90, 'impresiones' => 100], 'destacada' => true, 'motivo' => 'Consulta principal'],
            ['valores' => ['consulta' => 'cola larga', 'clics' => 10, 'impresiones' => 900], 'destacada' => false, 'motivo' => ''],
        ];
        $tabla['totales'] = ['clics' => 'suma', 'impresiones' => 'suma'];

        $reporte->secciones()->create([
            'tipo' => 'tabla',
            'titulo' => 'Consultas',
            'rotulo' => 'SECCIÓN 2',
            'orden' => 2,
            'contenido' => $tabla,
            'aviso' => ['texto' => 'Search Console no había consolidado el último día', 'fecha' => '31 ago 2026'],
            'visible' => true,
        ]);

        $reporte->secciones()->create([
            'tipo' => 'plan',
            'titulo' => 'Plan de acción',
            'orden' => 3,
            'contenido' => [
                'acciones' => [[
                    'prioridad' => 'p0',
                    'accion' => 'Corregir canonical',
                    'evidencia' => '312 URLs',
                    'area' => 'Técnico',
                    'impacto' => 5,
                    'esfuerzo' => 2,
                    'kpi' => 'Indexación',
                ]],
                'leyenda' => 'Score = impacto / esfuerzo.',
            ],
            'visible' => true,
        ]);

        $reporte->secciones()->create([
            'tipo' => 'kpis',
            'titulo' => 'Sección oculta',
            'orden' => 4,
            'contenido' => EsquemaSeccion::vacio(TipoSeccion::Kpis),
            'visible' => false,
        ]);

        return $reporte;
    }

    /**
     * Ruta versionada del entregable. Cada generación escribe su propio
     * fichero: sin la versión en el nombre, regenerar sobrescribía el PDF que
     * ya se le había enviado al cliente y el histórico apuntaba N veces al
     * mismo fichero.
     */
    private function ruta(Reporte $reporte, string $formato, int $version = 1): string
    {
        return "clientes/{$reporte->cliente_id}/reportes/{$reporte->numero}-v{$version}.{$formato}";
    }

    /**
     * Carga un .xlsx del disco falso. PhpSpreadsheet lee de disco real, así que
     * hay que volcarlo a un temporal.
     */
    private function abrirLibro(string $ruta): Spreadsheet
    {
        $temporal = tempnam(sys_get_temp_dir(), 'rep_test_').'.xlsx';
        file_put_contents($temporal, Storage::disk('local')->get($ruta));

        try {
            return IOFactory::load($temporal);
        } finally {
            @unlink($temporal);
        }
    }

    /** Número de la fila de «TOTAL COMPUTADO» de una hoja, o null si no la hay. */
    private function filaDeTotales(Worksheet $hoja, string $etiqueta = 'TOTAL COMPUTADO'): ?int
    {
        foreach ($hoja->getRowIterator() as $fila) {
            $celdas = $hoja->rangeToArray('A'.$fila->getRowIndex().':'.$hoja->getHighestColumn().$fila->getRowIndex(), null, false, false);

            foreach ($celdas[0] ?? [] as $valor) {
                if (is_string($valor) && str_starts_with($valor, $etiqueta)) {
                    return $fila->getRowIndex();
                }
            }
        }

        return null;
    }

    // --- PDF ----------------------------------------------------------------

    public function test_generating_pdf_stores_the_file_and_registers_archivo_and_entrega(): void
    {
        $user = User::factory()->create();
        $reporte = $this->reporteConSecciones();

        $response = $this->actingAs($user)->post(route('admin.reportes.pdf', $reporte));

        $response->assertOk();

        $reporte->refresh();
        $ruta = $this->ruta($reporte, 'pdf');
        Storage::disk('local')->assertExists($ruta);

        $archivo = Archivo::where('ruta_archivo', $ruta)->firstOrFail();
        $this->assertSame(TipoArchivo::Reporte, $archivo->tipo);
        $this->assertSame('pdf', $archivo->extension);
        $this->assertSame($reporte->cliente_id, $archivo->cliente_id);
        $this->assertSame($user->id, $archivo->subido_por);
        $this->assertSame(Storage::disk('local')->size($ruta), (int) $archivo->tamano);

        $entrega = ReporteEntrega::where('reporte_id', $reporte->id)->firstOrFail();
        $this->assertSame('pdf', $entrega->formato);
        $this->assertSame(1, $entrega->version);
        $this->assertSame($archivo->id, $entrega->archivo_id);
        $this->assertSame($user->id, $entrega->generado_por);
    }

    public function test_generated_pdf_is_not_empty_and_starts_with_the_pdf_magic_bytes(): void
    {
        $user = User::factory()->create();
        $reporte = $this->reporteConSecciones();

        $this->actingAs($user)->post(route('admin.reportes.pdf', $reporte))->assertOk();

        $bytes = Storage::disk('local')->get($this->ruta($reporte->refresh(), 'pdf'));

        $this->assertNotEmpty($bytes);
        $this->assertGreaterThan(1000, strlen($bytes));
        $this->assertSame('%PDF', substr($bytes, 0, 4));
    }

    // --- folio y estado -------------------------------------------------------

    public function test_folio_follows_the_area_year_sequence_format(): void
    {
        $user = User::factory()->create();
        $reporte = $this->reporteConSecciones();

        $this->actingAs($user)->post(route('admin.reportes.pdf', $reporte))->assertOk();

        $this->assertSame('REP-SEO-2026-0001', $reporte->refresh()->numero);
    }

    public function test_folio_sequence_advances_per_area(): void
    {
        $user = User::factory()->create();
        $primero = $this->reporteConSecciones();
        $segundo = $this->reporteConSecciones(['titulo' => 'Segundo reporte SEO']);
        $ads = $this->reporteConSecciones(['area' => AreaReporte::Ads->value, 'titulo' => 'Reporte de Ads']);

        $this->actingAs($user)->post(route('admin.reportes.pdf', $primero))->assertOk();
        $this->actingAs($user)->post(route('admin.reportes.pdf', $segundo))->assertOk();
        $this->actingAs($user)->post(route('admin.reportes.pdf', $ads))->assertOk();

        $this->assertSame('REP-SEO-2026-0001', $primero->refresh()->numero);
        $this->assertSame('REP-SEO-2026-0002', $segundo->refresh()->numero);
        $this->assertSame('REP-ADS-2026-0001', $ads->refresh()->numero);
    }

    public function test_first_generation_moves_reporte_from_borrador_to_listo_and_sets_fecha_emision(): void
    {
        $user = User::factory()->create();
        $reporte = $this->reporteConSecciones();

        $this->assertSame(EstadoReporte::Borrador, $reporte->estado);
        $this->assertNull($reporte->fecha_emision);

        $this->actingAs($user)->post(route('admin.reportes.pdf', $reporte))->assertOk();

        $reporte->refresh();
        $this->assertSame(EstadoReporte::Listo, $reporte->estado);
        $this->assertSame('2026-09-01', $reporte->fecha_emision->format('Y-m-d'));
    }

    public function test_generating_does_not_downgrade_an_already_entregado_reporte(): void
    {
        $user = User::factory()->create();
        $reporte = $this->reporteConSecciones(['estado' => EstadoReporte::Entregado->value]);

        $this->actingAs($user)->post(route('admin.reportes.pdf', $reporte))->assertOk();

        $this->assertSame(EstadoReporte::Entregado, $reporte->refresh()->estado);
    }

    // --- regeneración y versionado (B1) -----------------------------------------

    public function test_regenerating_adds_a_second_entrega_without_deleting_the_first_and_reuses_the_folio(): void
    {
        $user = User::factory()->create();
        $reporte = $this->reporteConSecciones();

        $this->actingAs($user)->post(route('admin.reportes.pdf', $reporte))->assertOk();

        $primera = ReporteEntrega::where('reporte_id', $reporte->id)->firstOrFail();
        $folio = $reporte->refresh()->numero;

        $this->actingAs($user)->post(route('admin.reportes.pdf', $reporte))->assertOk();

        $this->assertSame($folio, $reporte->refresh()->numero);
        $this->assertSame(2, ReporteEntrega::where('reporte_id', $reporte->id)->count());
        $this->assertDatabaseHas('reporte_entregas', ['id' => $primera->id, 'reporte_id' => $reporte->id]);

        // Dos entregas, dos Archivos y DOS ficheros distintos en el disco.
        $this->assertSame(2, Archivo::where('cliente_id', $reporte->cliente_id)->where('tipo', TipoArchivo::Reporte->value)->count());
    }

    /**
     * B1 · Regenerar conserva la versión anterior DESCARGABLE. Antes, todas las
     * generaciones compartían la ruta `{numero}.{formato}` y la última
     * sobrescribía a las demás: el histórico existía en la tabla, pero las N
     * filas apuntaban al mismo fichero, así que era imposible volver a bajar
     * exactamente lo que se le había enviado al cliente.
     *
     * El contenido del reporte se cambia entre las dos generaciones para que
     * los ficheros no puedan coincidir por casualidad.
     */
    public function test_regenerating_keeps_the_previous_version_downloadable_with_its_own_bytes_and_size(): void
    {
        $user = User::factory()->create();
        $reporte = $this->reporteConSecciones();

        $this->actingAs($user)->post(route('admin.reportes.pdf', $reporte))->assertOk();

        $folio = $reporte->refresh()->numero;
        $rutaV1 = $this->ruta($reporte, 'pdf', 1);
        Storage::disk('local')->assertExists($rutaV1);
        $bytesV1 = Storage::disk('local')->get($rutaV1);

        // El equipo corrige el reporte y vuelve a generar.
        $reporte->secciones()->where('titulo', 'Plan de acción')->delete();
        $reporte->secciones()->create([
            'tipo' => 'hallazgos',
            'titulo' => 'Hallazgos añadidos tras la revisión del cliente',
            'orden' => 5,
            'contenido' => ['items' => [
                ['titulo' => 'Canonical cruzado en 312 fichas de producto', 'severidad' => 'critico', 'evidencia' => 'Crawl del 30 ago'],
                ['titulo' => 'Sitemap con 40 URLs redirigidas', 'severidad' => 'alto', 'evidencia' => 'sitemap.xml'],
            ]],
            'visible' => true,
        ]);

        $this->actingAs($user)->post(route('admin.reportes.pdf', $reporte))->assertOk();

        $rutaV2 = $this->ruta($reporte->refresh(), 'pdf', 2);

        // El folio se reutiliza: las dos entregas son el mismo documento.
        $this->assertSame($folio, $reporte->numero);

        // Las dos versiones coexisten en el disco, cada una con sus bytes.
        Storage::disk('local')->assertExists($rutaV1);
        Storage::disk('local')->assertExists($rutaV2);
        $this->assertNotSame($rutaV1, $rutaV2);
        $this->assertSame($bytesV1, Storage::disk('local')->get($rutaV1));
        $this->assertNotSame($bytesV1, Storage::disk('local')->get($rutaV2));

        // Y cada Archivo declara el tamaño de SU fichero, no el del último.
        $archivoV1 = Archivo::where('ruta_archivo', $rutaV1)->firstOrFail();
        $archivoV2 = Archivo::where('ruta_archivo', $rutaV2)->firstOrFail();

        $this->assertSame(Storage::disk('local')->size($rutaV1), (int) $archivoV1->tamano);
        $this->assertSame(Storage::disk('local')->size($rutaV2), (int) $archivoV2->tamano);
        $this->assertNotSame((int) $archivoV1->tamano, (int) $archivoV2->tamano);

        // La versión sube y queda en el nombre del Archivo, que es lo que
        // distingue las dos filas en el módulo de Archivos.
        $this->assertSame(
            [1, 2],
            ReporteEntrega::where('reporte_id', $reporte->id)->orderBy('version')->pluck('version')->all()
        );
        $this->assertStringContainsString("{$folio} v1", $archivoV1->nombre);
        $this->assertStringContainsString("{$folio} v2", $archivoV2->nombre);
    }

    /** B1 · Cada formato lleva su propia serie de versiones. */
    public function test_pdf_and_xlsx_keep_independent_version_series(): void
    {
        $user = User::factory()->create();
        $reporte = $this->reporteConSecciones();

        $this->actingAs($user)->post(route('admin.reportes.pdf', $reporte))->assertOk();
        $this->actingAs($user)->post(route('admin.reportes.pdf', $reporte))->assertOk();
        $this->actingAs($user)->post(route('admin.reportes.xlsx', $reporte))->assertOk();

        $reporte->refresh();

        $this->assertSame(
            [1, 2],
            ReporteEntrega::where('reporte_id', $reporte->id)->where('formato', 'pdf')->orderBy('version')->pluck('version')->all()
        );
        // El XLSX empieza por la v1 aunque el PDF ya vaya por la v2.
        $this->assertSame(
            [1],
            ReporteEntrega::where('reporte_id', $reporte->id)->where('formato', 'xlsx')->orderBy('version')->pluck('version')->all()
        );

        Storage::disk('local')->assertExists($this->ruta($reporte, 'pdf', 1));
        Storage::disk('local')->assertExists($this->ruta($reporte, 'pdf', 2));
        Storage::disk('local')->assertExists($this->ruta($reporte, 'xlsx', 1));
        Storage::disk('local')->assertMissing($this->ruta($reporte, 'xlsx', 2));
    }

    public function test_pdf_and_xlsx_of_the_same_reporte_share_the_folio_and_produce_two_entregas(): void
    {
        $user = User::factory()->create();
        $reporte = $this->reporteConSecciones();

        $this->actingAs($user)->post(route('admin.reportes.pdf', $reporte))->assertOk();
        $folio = $reporte->refresh()->numero;
        $this->actingAs($user)->post(route('admin.reportes.xlsx', $reporte))->assertOk();

        $this->assertSame($folio, $reporte->refresh()->numero);
        $this->assertSame(
            ['pdf', 'xlsx'],
            ReporteEntrega::where('reporte_id', $reporte->id)->orderBy('id')->pluck('formato')->all()
        );
        Storage::disk('local')->assertExists($this->ruta($reporte, 'pdf', 1));
        Storage::disk('local')->assertExists($this->ruta($reporte, 'xlsx', 1));
    }

    // --- borrado (B18 / B19) ---------------------------------------------------

    /**
     * B18 · El force delete de un reporte se lleva por delante los Archivo de
     * sus entregas y sus ficheros del disco. Las secciones y las entregas caen
     * solas (FK en cascada); el Archivo no, porque su FK apunta al revés, y el
     * fichero quedaba ocupando espacio para siempre sin ninguna fila que lo
     * referenciara.
     */
    public function test_force_deleting_a_reporte_removes_its_archivos_and_their_files(): void
    {
        $user = User::factory()->create();
        $reporte = $this->reporteConSecciones();

        $this->actingAs($user)->post(route('admin.reportes.pdf', $reporte))->assertOk();
        $this->actingAs($user)->post(route('admin.reportes.xlsx', $reporte))->assertOk();

        $reporte->refresh();
        $rutas = [$this->ruta($reporte, 'pdf'), $this->ruta($reporte, 'xlsx')];
        $archivoIds = ReporteEntrega::where('reporte_id', $reporte->id)->pluck('archivo_id')->all();

        $this->assertCount(2, $archivoIds);
        foreach ($rutas as $ruta) {
            Storage::disk('local')->assertExists($ruta);
        }

        $reporte->forceDelete();

        foreach ($rutas as $ruta) {
            Storage::disk('local')->assertMissing($ruta);
        }
        foreach ($archivoIds as $id) {
            $this->assertDatabaseMissing('archivos', ['id' => $id]);
        }

        $this->assertDatabaseMissing('reportes', ['id' => $reporte->id]);
        $this->assertSame(0, ReporteEntrega::where('reporte_id', $reporte->id)->count());
        $this->assertSame(0, $reporte->secciones()->count());
    }

    /**
     * B19 · Borrar el PDF desde el módulo de Archivos no puede llevarse por
     * delante el registro de que esa entrega existió: el histórico ES el valor
     * de la tabla. La FK pasó de cascade a nullOnDelete.
     */
    public function test_deleting_the_archivo_of_an_entrega_keeps_the_entrega(): void
    {
        $user = User::factory()->create();
        $reporte = $this->reporteConSecciones();

        $this->actingAs($user)->post(route('admin.reportes.pdf', $reporte))->assertOk();

        $entrega = ReporteEntrega::where('reporte_id', $reporte->id)->firstOrFail();
        $archivo = Archivo::findOrFail($entrega->archivo_id);

        $archivo->delete();

        $this->assertDatabaseMissing('archivos', ['id' => $archivo->id]);
        $this->assertDatabaseHas('reporte_entregas', [
            'id' => $entrega->id,
            'reporte_id' => $reporte->id,
            'formato' => 'pdf',
            'version' => 1,
            'archivo_id' => null,
        ]);
        $this->assertNull($entrega->refresh()->archivo);
    }

    // --- XLSX ---------------------------------------------------------------------

    public function test_generating_xlsx_produces_a_readable_workbook_with_one_sheet_per_visible_section_plus_index(): void
    {
        $user = User::factory()->create();
        $reporte = $this->reporteConSecciones();

        $this->actingAs($user)->post(route('admin.reportes.xlsx', $reporte))->assertOk();

        $reporte->refresh();
        $ruta = $this->ruta($reporte, 'xlsx');
        Storage::disk('local')->assertExists($ruta);

        $libro = $this->abrirLibro($ruta);
        $hojas = $libro->getSheetNames();
        $libro->disconnectWorksheets();

        $visibles = $reporte->seccionesVisibles()->pluck('titulo')->all();

        $this->assertCount(count($visibles) + 1, $hojas);
        $this->assertSame('Índice', $hojas[0]);
        foreach ($visibles as $titulo) {
            $this->assertContains($titulo, $hojas);
        }
        $this->assertNotContains('Sección oculta', $hojas);
    }

    public function test_xlsx_archivo_and_entrega_are_registered_with_the_xlsx_format(): void
    {
        $user = User::factory()->create();
        $reporte = $this->reporteConSecciones();

        $this->actingAs($user)->post(route('admin.reportes.xlsx', $reporte))->assertOk();

        $archivo = Archivo::where('ruta_archivo', $this->ruta($reporte->refresh(), 'xlsx'))->firstOrFail();

        $this->assertSame(TipoArchivo::Reporte, $archivo->tipo);
        $this->assertSame('xlsx', $archivo->extension);
        $this->assertDatabaseHas('reporte_entregas', [
            'reporte_id' => $reporte->id,
            'archivo_id' => $archivo->id,
            'formato' => 'xlsx',
            'version' => 1,
        ]);
    }

    /**
     * B13 · «Índice» es el nombre de la hoja de portada, y Excel no admite dos
     * hojas con el mismo nombre: una sección titulada así hacía que setTitle()
     * lanzara y el usuario recibía un 500 sin mensaje. `History` está reservado
     * por el propio Excel para el libro compartido y tiene el mismo problema.
     */
    public function test_a_section_titled_indice_does_not_break_the_workbook(): void
    {
        $user = User::factory()->create();
        $reporte = $this->reporte();

        foreach (['Índice', 'History', 'Índice'] as $i => $titulo) {
            $reporte->secciones()->create([
                'tipo' => 'texto',
                'titulo' => $titulo,
                'orden' => $i + 1,
                'contenido' => ['formato' => 'pares', 'bloques' => [['titulo' => 'Sitio', 'cuerpo' => 'ejemplo.com']]],
                'visible' => true,
            ]);
        }

        $this->actingAs($user)->post(route('admin.reportes.xlsx', $reporte))->assertOk();

        $libro = $this->abrirLibro($this->ruta($reporte->refresh(), 'xlsx'));
        $hojas = $libro->getSheetNames();
        $libro->disconnectWorksheets();

        // Portada + tres secciones, todas con nombre propio y distinto.
        $this->assertCount(4, $hojas);
        $this->assertSame('Índice', $hojas[0]);
        $this->assertSame($hojas, array_values(array_unique($hojas)));
    }

    // --- B7/B8/B9/B10 · el Armador y las fórmulas del XLSX coinciden -------------

    /**
     * Reporte con una tabla por escenario de total. Los números están elegidos
     * para que cada modo dé una cifra distinta de la que darían los demás, de
     * modo que una fórmula equivocada no pueda pasar por casualidad.
     */
    private function reporteDeTotales(): Reporte
    {
        $reporte = $this->reporte(['titulo' => 'Reporte de totales']);

        // 1 · Los cuatro modos que calculan, más los dos casos degenerados que
        //     obligan al IFERROR: una columna sin ningún valor (AVERAGE sobre
        //     un rango sin números → #DIV/0!) y una celda de texto dentro del
        //     rango de un SUMPRODUCT (→ #VALUE!).
        $consultas = EsquemaSeccion::vacio(TipoSeccion::Tabla);
        $consultas['columnas'] = [
            ['clave' => 'consulta', 'titulo' => 'Consulta', 'tipo' => 'texto'],
            ['clave' => 'clics', 'titulo' => 'Clics', 'tipo' => 'numero'],
            ['clave' => 'impresiones', 'titulo' => 'Impresiones', 'tipo' => 'numero'],
            ['clave' => 'ctr', 'titulo' => 'CTR', 'tipo' => 'porcentaje'],
            ['clave' => 'posicion', 'titulo' => 'Posición media', 'tipo' => 'decimal'],
            ['clave' => 'autoridad', 'titulo' => 'Autoridad', 'tipo' => 'decimal'],
            ['clave' => 'calidad', 'titulo' => 'Calidad', 'tipo' => 'decimal'],
            ['clave' => 'vacia', 'titulo' => 'Columna sin datos', 'tipo' => 'decimal'],
        ];
        $consultas['filas'] = [
            ['valores' => [
                'consulta' => 'marca principal',
                'clics' => 90, 'impresiones' => 100, 'ctr' => 0.9,
                'posicion' => 3, 'autoridad' => 5, 'calidad' => 10, 'vacia' => null,
            ], 'destacada' => true, 'motivo' => 'Consulta de marca'],
            ['valores' => [
                'consulta' => 'cola larga',
                'clics' => 10, 'impresiones' => 900, 'ctr' => 0.0111,
                // B9 · texto dentro del rango de un SUMPRODUCT y de un AVERAGE.
                'posicion' => 30, 'autoridad' => 'n/d', 'calidad' => 'n/d', 'vacia' => null,
            ], 'destacada' => false, 'motivo' => ''],
        ];
        $consultas['filas_excluidas'] = [
            ['valores' => [
                'consulta' => 'consulta de marca excluida',
                'clics' => 5000, 'impresiones' => 5000, 'ctr' => 1.0,
                'posicion' => 1, 'autoridad' => 4, 'calidad' => 4, 'vacia' => null,
            ], 'motivo' => 'Tráfico de marca'],
        ];
        $consultas['totales'] = [
            'clics' => 'suma',
            'impresiones' => 'suma',
            'ctr' => 'ctr',
            'posicion' => 'ponderado',
            'autoridad' => 'ponderado',
            'calidad' => 'promedio',
            'vacia' => 'promedio',
        ];

        $reporte->secciones()->create([
            'tipo' => 'tabla', 'titulo' => 'Consultas', 'orden' => 1,
            'contenido' => $consultas, 'visible' => true,
        ]);

        // 2 · B10 · impresiones a cero: el CTR agregado sería una división por
        //     cero, así que el Armador degrada a media y el Excel tiene que
        //     escribir la fórmula de la media, no la del CTR.
        $sinImpresiones = EsquemaSeccion::vacio(TipoSeccion::Tabla);
        $sinImpresiones['columnas'] = [
            ['clave' => 'consulta', 'titulo' => 'Consulta', 'tipo' => 'texto'],
            ['clave' => 'clics', 'titulo' => 'Clics', 'tipo' => 'numero'],
            ['clave' => 'impresiones', 'titulo' => 'Impresiones', 'tipo' => 'numero'],
            ['clave' => 'ctr', 'titulo' => 'CTR', 'tipo' => 'porcentaje'],
        ];
        $sinImpresiones['filas'] = [
            ['valores' => ['consulta' => 'consulta fantasma', 'clics' => 0, 'impresiones' => 0, 'ctr' => 0.2], 'destacada' => false, 'motivo' => ''],
            ['valores' => ['consulta' => 'consulta sin servir', 'clics' => 0, 'impresiones' => 0, 'ctr' => 0.4], 'destacada' => false, 'motivo' => ''],
        ];
        $sinImpresiones['totales'] = ['clics' => 'suma', 'impresiones' => 'suma', 'ctr' => 'ctr'];

        $reporte->secciones()->create([
            'tipo' => 'tabla', 'titulo' => 'Sin impresiones', 'orden' => 2,
            'contenido' => $sinImpresiones, 'visible' => true,
        ]);

        // 3 · Modo `ninguno` en todas las columnas: no hay pie de totales que
        //     escribir, ni en el PDF ni en el Excel.
        $sinTotales = EsquemaSeccion::vacio(TipoSeccion::Tabla);
        $sinTotales['columnas'] = [
            ['clave' => 'pagina', 'titulo' => 'Página', 'tipo' => 'texto'],
            ['clave' => 'estado', 'titulo' => 'Estado', 'tipo' => 'nivel'],
            ['clave' => 'ttfb', 'titulo' => 'TTFB', 'tipo' => 'numero'],
        ];
        $sinTotales['filas'] = [
            ['valores' => ['pagina' => '/', 'estado' => 'alta', 'ttfb' => 320], 'destacada' => false, 'motivo' => ''],
            ['valores' => ['pagina' => '/blog', 'estado' => 'baja', 'ttfb' => 980], 'destacada' => false, 'motivo' => ''],
        ];
        $sinTotales['totales'] = ['ttfb' => 'ninguno'];

        $reporte->secciones()->create([
            'tipo' => 'tabla', 'titulo' => 'Sin totales', 'orden' => 3,
            'contenido' => $sinTotales, 'visible' => true,
        ]);

        return $reporte;
    }

    /**
     * B7/B8/B9/B10 · La prueba que sostiene el módulo entero: para cada modo de
     * total y para cada caso degenerado, el número que calcula el Armador —el
     * que imprime el PDF— y el que produce la fórmula del XLSX son el mismo.
     *
     * Se compara celda a celda contra `derivados.totales` reabriendo el libro
     * con IOFactory, que recalcula las fórmulas: no se comprueba el texto de la
     * fórmula, sino su RESULTADO, que es lo que ve el cliente.
     */
    public function test_every_total_mode_produces_the_same_number_in_the_armador_and_in_the_xlsx_formula(): void
    {
        $user = User::factory()->create();
        $reporte = $this->reporteDeTotales();

        $this->actingAs($user)->post(route('admin.reportes.xlsx', $reporte))->assertOk();

        $armador = app(Armador::class);
        $datos = $armador->armar($reporte->refresh());
        $libro = $this->abrirLibro($this->ruta($reporte, 'xlsx'));

        $modosComprobados = [];

        try {
            foreach ($datos['secciones'] as $seccion) {
                if ($seccion['tipo'] !== 'tabla') {
                    continue;
                }

                $hoja = $libro->getSheetByName($seccion['titulo']);
                $this->assertNotNull($hoja, "Falta la hoja de la sección {$seccion['titulo']}.");

                $derivados = $seccion['derivados'];
                $totales = (array) $derivados['totales'];
                $efectivos = (array) $derivados['modos_efectivos'];
                $fila = $this->filaDeTotales($hoja);

                if ($totales === []) {
                    // Modo `ninguno`: sin totales que calcular no se dibuja pie.
                    $this->assertNull($fila, "La sección {$seccion['titulo']} no debería tener pie de totales.");
                    $modosComprobados['ninguno'] = true;

                    continue;
                }

                $this->assertNotNull($fila, "Falta el pie de totales de {$seccion['titulo']}.");

                $letraDe = [];
                foreach ($seccion['contenido']['columnas'] as $i => $columna) {
                    $letraDe[(string) $columna['clave']] = [
                        'letra' => Coordinate::stringFromColumnIndex($i + 1),
                        'tipo' => (string) ($columna['tipo'] ?? 'texto'),
                    ];
                }

                foreach ($efectivos as $clave => $modo) {
                    $modosComprobados[$modo] = true;

                    $celda = $letraDe[$clave]['letra'].$fila;
                    $tipo = $letraDe[$clave]['tipo'];
                    $calculado = $hoja->getCell($celda)->getCalculatedValue();
                    $esperado = $totales[$clave] ?? null;
                    $donde = "{$seccion['titulo']}!{$celda} (columna «{$clave}», modo {$modo})";

                    if ($esperado === null) {
                        // B8 · El Armador no tiene número; el PDF imprime «n/d»
                        // y el IFERROR de la fórmula tiene que mostrar lo mismo
                        // en vez de un #DIV/0! en rojo.
                        $this->assertSame('n/d', $calculado, "Se esperaba «n/d» en {$donde}.");

                        continue;
                    }

                    // Los porcentajes viajan como fracción en la celda porque
                    // el formato 0.00% de Excel multiplica por 100 al mostrar.
                    $esperado = $tipo === 'porcentaje'
                        ? $armador->aPorcentaje((float) $esperado) / 100
                        : (float) $esperado;

                    $this->assertIsNumeric($calculado, "La fórmula de {$donde} no devolvió un número: ".var_export($calculado, true));
                    $this->assertEqualsWithDelta($esperado, (float) $calculado, 0.000001, "El Armador y la fórmula discrepan en {$donde}.");
                }
            }
        } finally {
            $libro->disconnectWorksheets();
        }

        // Todos los modos del esquema quedan ejercitados por este test.
        $this->assertSame(
            EsquemaSeccion::TOTALES,
            array_values(array_intersect(EsquemaSeccion::TOTALES, array_keys($modosComprobados))),
            'Faltó ejercitar algún modo de total: '.implode(', ', array_keys($modosComprobados))
        );
    }

    /**
     * B8/B9 · Ninguna celda del libro puede entregar un error de Excel al
     * cliente. El renderizador escribe «n/d» —texto— donde no hay dato, y ese
     * texto es justo lo que hace estallar a SUMPRODUCT (#VALUE!) y lo que deja
     * a AVERAGE sin números (#DIV/0!).
     */
    public function test_the_workbook_contains_no_div_zero_or_value_errors_in_any_cell(): void
    {
        $user = User::factory()->create();
        $reporte = $this->reporteDeTotales();

        $this->actingAs($user)->post(route('admin.reportes.xlsx', $reporte))->assertOk();

        $libro = $this->abrirLibro($this->ruta($reporte->refresh(), 'xlsx'));
        $errores = [];

        try {
            foreach ($libro->getAllSheets() as $hoja) {
                foreach ($hoja->getRowIterator() as $fila) {
                    $celdas = $fila->getCellIterator();
                    $celdas->setIterateOnlyExistingCells(true);

                    foreach ($celdas as $celda) {
                        try {
                            $valor = $celda->getCalculatedValue();
                        } catch (\Throwable $e) {
                            $errores[] = $hoja->getTitle().'!'.$celda->getCoordinate().' lanzó '.$e->getMessage();

                            continue;
                        }

                        // Sólo los códigos de error de Excel: la cabecera «#»
                        // de la columna de numeración del índice es texto
                        // legítimo y empieza igual.
                        $codigos = ['#DIV/0!', '#VALUE!', '#REF!', '#NAME?', '#NUM!', '#N/A', '#NULL!'];

                        if (is_string($valor) && in_array(trim($valor), $codigos, true)) {
                            $errores[] = $hoja->getTitle().'!'.$celda->getCoordinate().' = '.$valor;
                        }
                    }
                }
            }
        } finally {
            $libro->disconnectWorksheets();
        }

        $this->assertSame([], $errores, "Celdas con error de Excel:\n".implode("\n", $errores));
    }

    // --- preview y auth --------------------------------------------------------------

    public function test_preview_renders_without_generating_any_entrega(): void
    {
        $user = User::factory()->create();
        $reporte = $this->reporteConSecciones();

        $this->actingAs($user)->get(route('admin.reportes.preview', $reporte))->assertOk();

        $this->assertNull($reporte->refresh()->numero);
        $this->assertSame(0, ReporteEntrega::count());
        $this->assertSame(0, Archivo::count());
    }

    public function test_generation_requires_authentication(): void
    {
        $reporte = $this->reporteConSecciones();

        $this->post(route('admin.reportes.pdf', $reporte))->assertRedirect('/login');
        $this->post(route('admin.reportes.xlsx', $reporte))->assertRedirect('/login');

        $this->assertSame(0, ReporteEntrega::count());
        $this->assertNull($reporte->refresh()->numero);
    }

    // --- logotipo en la vista previa -----------------------------------------------

    public function test_preview_embeds_the_logo_as_a_base64_data_uri(): void
    {
        $reporte = $this->reporteConSecciones();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.reportes.preview', $reporte));

        $response->assertOk();
        $response->assertSee('data:image/png;base64,');
    }
}
