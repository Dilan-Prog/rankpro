<?php

namespace Tests\Feature\Admin;

use App\Enums\TipoElementoContenido;
use App\Models\Cliente;
use App\Models\ElementoContenido;
use App\Models\User;
use App\Support\ClienteActivo;
use App\Support\Contenido\TablaContenido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Módulo «Contenido»: árbol por cliente, subida de archivos, documentos
 * Markdown y tablas editables con import/export CSV.
 */
class ContenidoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->usuario = User::factory()->create();
        $this->cliente = Cliente::factory()->create();
    }

    /** Caso autenticado con el cliente activo en sesión (o sin cliente si se pasa false). */
    private function como(Cliente|false|null $cliente = null)
    {
        $caso = $this->actingAs($this->usuario);
        $cliente = $cliente === null ? $this->cliente : $cliente;

        return $cliente ? $caso->withSession([ClienteActivo::CLAVE => $cliente->id]) : $caso;
    }

    private function elemento(TipoElementoContenido $tipo, array $extra = [], ?Cliente $cliente = null): ElementoContenido
    {
        return ElementoContenido::create($extra + [
            'cliente_id' => ($cliente ?? $this->cliente)->id,
            'tipo' => $tipo->value,
            'nombre' => 'Elemento '.$tipo->value,
        ]);
    }

    private function tabla(array $datos = [], ?Cliente $cliente = null): ElementoContenido
    {
        return $this->elemento(TipoElementoContenido::Tabla, [
            'contenido' => json_encode($datos ?: TablaContenido::vacia()),
        ], $cliente);
    }

    private function tablaBasica(): array
    {
        return [
            'columnas' => [
                ['id' => 'c1', 'nombre' => 'Título', 'tipo' => 'texto', 'opciones' => []],
                ['id' => 'c2', 'nombre' => 'Monto', 'tipo' => 'numero', 'opciones' => []],
                ['id' => 'c3', 'nombre' => 'Fecha', 'tipo' => 'fecha', 'opciones' => []],
                ['id' => 'c4', 'nombre' => 'Estado', 'tipo' => 'seleccion', 'opciones' => ['Idea', 'Listo']],
            ],
            'filas' => [
                ['id' => 'f1', 'celdas' => ['c1' => 'Post uno', 'c2' => '10', 'c3' => '2026-11-05', 'c4' => 'Idea']],
            ],
            'vista' => 'tabla',
            'columna_kanban' => 'c4',
        ];
    }

    // ---------- Cliente activo y acceso ----------

    public function test_invitados_no_entran(): void
    {
        $this->get(route('admin.contenido.index'))->assertRedirect();
    }

    public function test_sin_cliente_activo_muestra_estado_vacio(): void
    {
        $this->como(false)->get(route('admin.contenido.index'))
            ->assertOk()
            ->assertSee('Elige un cliente');
    }

    public function test_sin_cliente_activo_no_se_puede_crear(): void
    {
        $this->como(false)
            ->postJson(route('admin.contenido.store'), ['tipo' => 'carpeta', 'nombre' => 'X'])
            ->assertStatus(422);

        $this->assertDatabaseCount('cm_items', 0);
    }

    public function test_el_listado_solo_muestra_el_cliente_activo(): void
    {
        $otro = Cliente::factory()->create();
        $this->elemento(TipoElementoContenido::Carpeta, ['nombre' => 'Carpeta propia']);
        $this->elemento(TipoElementoContenido::Carpeta, ['nombre' => 'Carpeta ajena'], $otro);

        $this->como()->get(route('admin.contenido.index'))
            ->assertOk()
            ->assertSee('Carpeta propia')
            ->assertDontSee('Carpeta ajena');
    }

    public function test_elementos_de_otro_cliente_responden_404(): void
    {
        $otro = Cliente::factory()->create();
        $ajeno = $this->elemento(TipoElementoContenido::Documento, ['contenido' => 'secreto'], $otro);
        $tablaAjena = $this->tabla([], $otro);

        $this->como()->get(route('admin.contenido.show', $ajeno))->assertNotFound();
        $this->como()->patchJson(route('admin.contenido.actualizar', $ajeno), ['nombre' => 'x'])->assertNotFound();
        $this->como()->deleteJson(route('admin.contenido.destroy', $ajeno))->assertNotFound();
        $this->como()->putJson(route('admin.contenido.documento.guardar', $ajeno), ['contenido' => 'x'])->assertNotFound();
        $this->como()->putJson(route('admin.contenido.tabla.guardar', $tablaAjena), TablaContenido::vacia())->assertNotFound();
        $this->como()->get(route('admin.contenido.tabla.exportar', $tablaAjena))->assertNotFound();

        $this->assertNotNull($ajeno->fresh());
    }

    // ---------- Árbol ----------

    public function test_crea_carpeta_documento_y_tabla(): void
    {
        $this->como()->postJson(route('admin.contenido.store'), ['tipo' => 'carpeta', 'nombre' => 'Marca'])->assertCreated();
        $carpeta = ElementoContenido::where('tipo', 'carpeta')->firstOrFail();

        $this->como()->postJson(route('admin.contenido.store'), ['tipo' => 'documento', 'nombre' => 'Guía', 'parent_id' => $carpeta->id])
            ->assertCreated()->assertJsonPath('tipo', 'documento');
        $this->como()->postJson(route('admin.contenido.store'), ['tipo' => 'tabla', 'nombre' => 'Datos', 'parent_id' => $carpeta->id])->assertCreated();

        $this->assertSame(2, $carpeta->hijos()->count());
        $this->assertSame($this->cliente->id, $carpeta->hijos()->first()->cliente_id);
    }

    public function test_rechaza_tipos_invalidos_al_crear(): void
    {
        $this->como()->postJson(route('admin.contenido.store'), ['tipo' => 'archivo', 'nombre' => 'X'])->assertStatus(422);
        $this->como()->postJson(route('admin.contenido.store'), ['tipo' => 'carpeta', 'nombre' => ''])->assertStatus(422);
    }

    public function test_no_se_crea_dentro_de_un_padre_de_otro_cliente_ni_que_no_sea_carpeta(): void
    {
        $otro = Cliente::factory()->create();
        $ajena = $this->elemento(TipoElementoContenido::Carpeta, [], $otro);
        $documento = $this->elemento(TipoElementoContenido::Documento);

        $this->como()->postJson(route('admin.contenido.store'), ['tipo' => 'carpeta', 'nombre' => 'X', 'parent_id' => $ajena->id])->assertStatus(422);
        $this->como()->postJson(route('admin.contenido.store'), ['tipo' => 'carpeta', 'nombre' => 'X', 'parent_id' => $documento->id])->assertStatus(422);
        $this->como()->postJson(route('admin.contenido.store'), ['tipo' => 'carpeta', 'nombre' => 'X', 'parent_id' => 99999])->assertStatus(422);

        $this->assertSame(0, ElementoContenido::where('nombre', 'X')->count());
    }

    public function test_renombra_y_mueve(): void
    {
        $destino = $this->elemento(TipoElementoContenido::Carpeta, ['nombre' => 'Destino']);
        $doc = $this->elemento(TipoElementoContenido::Documento);

        $this->como()->patchJson(route('admin.contenido.actualizar', $doc), ['nombre' => 'Nuevo nombre', 'parent_id' => $destino->id])->assertOk();

        $this->assertSame('Nuevo nombre', $doc->fresh()->nombre);
        $this->assertSame($destino->id, $doc->fresh()->parent_id);

        // Volver a la raíz.
        $this->como()->patchJson(route('admin.contenido.actualizar', $doc), ['parent_id' => null])->assertOk();
        $this->assertNull($doc->fresh()->parent_id);
    }

    public function test_una_carpeta_no_se_mueve_dentro_de_si_misma_ni_de_un_descendiente(): void
    {
        $a = $this->elemento(TipoElementoContenido::Carpeta, ['nombre' => 'A']);
        $b = $this->elemento(TipoElementoContenido::Carpeta, ['nombre' => 'B', 'parent_id' => $a->id]);
        $c = $this->elemento(TipoElementoContenido::Carpeta, ['nombre' => 'C', 'parent_id' => $b->id]);

        $this->como()->patchJson(route('admin.contenido.actualizar', $a), ['parent_id' => $a->id])->assertStatus(422);
        $this->como()->patchJson(route('admin.contenido.actualizar', $a), ['parent_id' => $c->id])->assertStatus(422);

        $this->assertNull($a->fresh()->parent_id);
    }

    public function test_no_se_mueve_a_la_carpeta_de_otro_cliente(): void
    {
        $otro = Cliente::factory()->create();
        $ajena = $this->elemento(TipoElementoContenido::Carpeta, [], $otro);
        $doc = $this->elemento(TipoElementoContenido::Documento);

        $this->como()->patchJson(route('admin.contenido.actualizar', $doc), ['parent_id' => $ajena->id])->assertStatus(422);

        $this->assertNull($doc->fresh()->parent_id);
    }

    public function test_borrar_una_carpeta_elimina_su_contenido_y_los_archivos_fisicos(): void
    {
        $carpeta = $this->elemento(TipoElementoContenido::Carpeta);
        $sub = $this->elemento(TipoElementoContenido::Carpeta, ['parent_id' => $carpeta->id]);
        $doc = $this->elemento(TipoElementoContenido::Documento, ['parent_id' => $sub->id]);
        Storage::disk('local')->put('clientes/1/contenido/a.pdf', 'x');
        $archivo = $this->elemento(TipoElementoContenido::Archivo, ['parent_id' => $sub->id, 'archivo_path' => 'clientes/1/contenido/a.pdf']);
        $fuera = $this->elemento(TipoElementoContenido::Documento);

        $this->como()->deleteJson(route('admin.contenido.destroy', $carpeta))->assertOk()->assertJsonPath('cantidad', 4);

        foreach ([$carpeta, $sub, $doc, $archivo] as $e) {
            $this->assertSoftDeleted('cm_items', ['id' => $e->id]);
        }
        $this->assertNotSoftDeleted('cm_items', ['id' => $fuera->id]);
        Storage::disk('local')->assertMissing('clientes/1/contenido/a.pdf');
    }

    public function test_navegar_a_una_carpeta_muestra_migas_y_su_contenido(): void
    {
        $a = $this->elemento(TipoElementoContenido::Carpeta, ['nombre' => 'Nivel A']);
        $b = $this->elemento(TipoElementoContenido::Carpeta, ['nombre' => 'Nivel B', 'parent_id' => $a->id]);
        $this->elemento(TipoElementoContenido::Documento, ['nombre' => 'Dentro de B', 'parent_id' => $b->id]);

        $this->como()->get(route('admin.contenido.index', ['carpeta' => $b->id]))
            ->assertOk()
            ->assertSee('Nivel A')
            ->assertSee('Dentro de B');

        // Una carpeta de otro cliente no se puede abrir.
        $ajena = $this->elemento(TipoElementoContenido::Carpeta, [], Cliente::factory()->create());
        $this->como()->get(route('admin.contenido.index', ['carpeta' => $ajena->id]))->assertNotFound();
    }

    public function test_busca_por_nombre_en_todo_el_arbol_y_escapa_el_termino(): void
    {
        $a = $this->elemento(TipoElementoContenido::Carpeta, ['nombre' => 'Campañas']);
        $this->elemento(TipoElementoContenido::Documento, ['nombre' => 'Brief de verano', 'parent_id' => $a->id]);
        $this->elemento(TipoElementoContenido::Documento, ['nombre' => 'Otro']);
        $this->elemento(TipoElementoContenido::Documento, ['nombre' => 'Brief ajeno'], Cliente::factory()->create());

        $this->como()->get(route('admin.contenido.index', ['q' => 'brief']))
            ->assertOk()
            ->assertSee('Brief de verano')
            ->assertSee('En Campañas')
            ->assertDontSee('Brief ajeno')
            ->assertDontSee('>Otro<', false);

        // «%» es texto literal, no comodín: no debe devolver todo.
        $this->como()->get(route('admin.contenido.index', ['q' => '%']))->assertOk()->assertDontSee('Brief de verano');
    }

    // ---------- Archivos ----------

    public function test_sube_un_archivo_y_lo_descarga(): void
    {
        $carpeta = $this->elemento(TipoElementoContenido::Carpeta);

        $respuesta = $this->como()->postJson(route('admin.contenido.subir'), [
            'archivo' => UploadedFile::fake()->create('brief.pdf', 100, 'application/pdf'),
            'parent_id' => $carpeta->id,
        ])->assertCreated();

        $elemento = ElementoContenido::findOrFail($respuesta->json('id'));
        $this->assertSame('archivo', $elemento->tipo->value);
        $this->assertSame('brief.pdf', $elemento->nombre);
        $this->assertSame($carpeta->id, $elemento->parent_id);
        $this->assertStringStartsWith("clientes/{$this->cliente->id}/contenido/", $elemento->archivo_path);
        Storage::disk('local')->assertExists($elemento->archivo_path);

        $this->como()->get(route('admin.contenido.descargar', $elemento))->assertOk()->assertDownload('brief.pdf');
        // Abrir un archivo desde el explorador redirige a la descarga.
        $this->como()->get(route('admin.contenido.show', $elemento))->assertRedirect(route('admin.contenido.descargar', $elemento));
    }

    public function test_la_subida_valida_tipo_y_tamano(): void
    {
        $this->como()->postJson(route('admin.contenido.subir'), ['archivo' => UploadedFile::fake()->create('virus.exe', 10)])->assertStatus(422);
        $this->como()->postJson(route('admin.contenido.subir'), ['archivo' => UploadedFile::fake()->create('script.php', 10)])->assertStatus(422);
        $this->como()->postJson(route('admin.contenido.subir'), ['archivo' => UploadedFile::fake()->create('enorme.pdf', 51201, 'application/pdf')])->assertStatus(422);
        $this->como()->postJson(route('admin.contenido.subir'), [])->assertStatus(422);

        $this->assertDatabaseCount('cm_items', 0);
    }

    public function test_la_subida_valida_el_padre(): void
    {
        $ajena = $this->elemento(TipoElementoContenido::Carpeta, [], Cliente::factory()->create());

        $this->como()->postJson(route('admin.contenido.subir'), [
            'archivo' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
            'parent_id' => $ajena->id,
        ])->assertStatus(422);

        $this->assertDatabaseCount('cm_items', 1); // solo la carpeta ajena de la preparación
    }

    public function test_descargar_algo_que_no_es_archivo_da_404(): void
    {
        $doc = $this->elemento(TipoElementoContenido::Documento);

        $this->como()->get(route('admin.contenido.descargar', $doc))->assertNotFound();
    }

    // ---------- Documentos ----------

    public function test_guarda_un_documento_y_el_editor_lo_muestra_escapado(): void
    {
        $doc = $this->elemento(TipoElementoContenido::Documento, ['nombre' => 'Guía']);
        $peligroso = "# Hola\n<script>alert(1)</script>";

        $this->como()->putJson(route('admin.contenido.documento.guardar', $doc), ['contenido' => $peligroso])->assertOk()->assertJsonStructure(['guardado']);
        $this->assertSame($peligroso, $doc->fresh()->contenido);

        $this->como()->get(route('admin.contenido.show', $doc))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_el_nombre_se_escapa_en_el_listado(): void
    {
        $this->elemento(TipoElementoContenido::Documento, ['nombre' => '<img src=x onerror=alert(1)>']);

        $this->como()->get(route('admin.contenido.index'))
            ->assertOk()
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_guardar_documento_valida_el_tamano(): void
    {
        $doc = $this->elemento(TipoElementoContenido::Documento);

        $this->como()->putJson(route('admin.contenido.documento.guardar', $doc), ['contenido' => str_repeat('a', 200001)])->assertStatus(422);
    }

    public function test_guardar_documento_solo_aplica_a_documentos(): void
    {
        $carpeta = $this->elemento(TipoElementoContenido::Carpeta);

        $this->como()->putJson(route('admin.contenido.documento.guardar', $carpeta), ['contenido' => 'x'])->assertNotFound();
    }

    public function test_la_vista_previa_usa_el_renderizador_seguro(): void
    {
        $res = $this->como()->postJson(route('admin.contenido.previsualizar'), [
            'contenido' => "## Título\n\n<script>alert(1)</script>\n\n[x](javascript:alert(1))",
        ])->assertOk();

        $html = $res->json('html');
        $this->assertStringContainsString('<h2', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    // ---------- Tablas ----------

    public function test_abre_el_editor_de_tabla(): void
    {
        $tabla = $this->tabla($this->tablaBasica());

        $this->como()->get(route('admin.contenido.show', $tabla))->assertOk()->assertSee('Post uno');
    }

    public function test_guarda_la_tabla_completa(): void
    {
        $tabla = $this->tabla();
        $datos = $this->tablaBasica();
        $datos['vista'] = 'kanban';

        $this->como()->putJson(route('admin.contenido.tabla.guardar', $tabla), $datos)
            ->assertOk()
            ->assertJsonPath('datos.filas.0.celdas.c1', 'Post uno');

        $guardado = json_decode($tabla->fresh()->contenido, true);
        $this->assertSame('kanban', $guardado['vista']);
        $this->assertCount(4, $guardado['columnas']);
        $this->assertSame('Post uno', $guardado['filas'][0]['celdas']['c1']);
    }

    public function test_una_celda_vacia_se_guarda_como_texto_vacio(): void
    {
        $tabla = $this->tabla();
        $datos = $this->tablaBasica();
        // El middleware de la app convierte "" en null: debe seguir siendo válido.
        $datos['filas'][0]['celdas']['c2'] = '';

        $this->como()->putJson(route('admin.contenido.tabla.guardar', $tabla), $datos)->assertOk();
        $this->assertSame('', json_decode($tabla->fresh()->contenido, true)['filas'][0]['celdas']['c2']);
    }

    /** @dataProvider estructurasInvalidas */
    public function test_rechaza_estructuras_invalidas(callable $mutar): void
    {
        $tabla = $this->tabla();
        $datos = $this->tablaBasica();
        $mutar($datos);

        $this->como()->putJson(route('admin.contenido.tabla.guardar', $tabla), $datos)
            ->assertStatus(422)
            ->assertJsonStructure(['message']);

        $this->assertSame(TablaContenido::vacia(), json_decode($tabla->fresh()->contenido, true));
    }

    public static function estructurasInvalidas(): array
    {
        return [
            'tipo de columna desconocido' => [function (&$d) { $d['columnas'][0]['tipo'] = 'formula'; }],
            'columna sin nombre' => [function (&$d) { $d['columnas'][0]['nombre'] = '  '; }],
            'id de columna repetido' => [function (&$d) { $d['columnas'][1]['id'] = 'c1'; }],
            'id con caracteres raros' => [function (&$d) { $d['columnas'][0]['id'] = 'c1"><script>'; }],
            'celda de columna inexistente' => [function (&$d) { $d['filas'][0]['celdas']['zz'] = 'x'; }],
            'número inválido' => [function (&$d) { $d['filas'][0]['celdas']['c2'] = 'abc'; }],
            'fecha inválida' => [function (&$d) { $d['filas'][0]['celdas']['c3'] = '2026-13-45'; }],
            'opción que no existe' => [function (&$d) { $d['filas'][0]['celdas']['c4'] = 'Inventada'; }],
            'id de fila repetido' => [function (&$d) { $d['filas'][] = $d['filas'][0]; }],
            'vista inválida' => [function (&$d) { $d['vista'] = 'mosaico'; }],
            'demasiadas columnas' => [function (&$d) {
                $d['columnas'] = array_map(fn ($i) => ['id' => "k$i", 'nombre' => "C$i", 'tipo' => 'texto', 'opciones' => []], range(1, 31));
                $d['filas'] = [];
            }],
            'demasiadas filas' => [function (&$d) {
                $d['filas'] = array_map(fn ($i) => ['id' => "r$i", 'celdas' => []], range(1, 2001));
            }],
            'texto de celda demasiado largo, como objeto' => [function (&$d) { $d['filas'][0]['celdas']['c1'] = ['a' => 'b']; }],
        ];
    }

    public function test_un_texto_largo_se_recorta_y_no_rompe(): void
    {
        $r = TablaContenido::normalizar([
            'columnas' => [['id' => 'c1', 'nombre' => 'T', 'tipo' => 'texto']],
            'filas' => [['id' => 'f1', 'celdas' => ['c1' => str_repeat('x', 5000)]]],
        ]);

        $this->assertSame(TablaContenido::MAX_TEXTO, mb_strlen($r['filas'][0]['celdas']['c1']));
    }

    public function test_una_tabla_demasiado_grande_se_rechaza(): void
    {
        $tabla = $this->tabla();
        $texto = str_repeat('x', 2000);
        $armar = fn (int $filas) => [
            'columnas' => [['id' => 'c1', 'nombre' => 'T', 'tipo' => 'texto', 'opciones' => []]],
            'filas' => array_map(fn ($i) => ['id' => "f$i", 'celdas' => ['c1' => $texto]], range(1, $filas)),
            'vista' => 'tabla',
            'columna_kanban' => null,
        ];

        // ~2 MB: cabe en el cuerpo pero supera el tope del JSON normalizado.
        $this->como()->putJson(route('admin.contenido.tabla.guardar', $tabla), $armar(1000))->assertStatus(422);
        // ~4 MB: se corta antes de decodificar.
        $this->como()->putJson(route('admin.contenido.tabla.guardar', $tabla), $armar(2000))->assertStatus(413);

        $this->assertSame(TablaContenido::vacia(), json_decode($tabla->fresh()->contenido, true));
    }

    public function test_la_columna_kanban_invalida_se_descarta_sin_rechazar(): void
    {
        $datos = $this->tablaBasica();
        $datos['columna_kanban'] = 'c1'; // texto, no selección
        $datos['vista'] = 'kanban';

        $r = TablaContenido::normalizar($datos);

        $this->assertNull($r['columna_kanban']);
        $this->assertSame('tabla', $r['vista']);
    }

    public function test_guardar_tabla_solo_aplica_a_tablas(): void
    {
        $doc = $this->elemento(TipoElementoContenido::Documento);

        $this->como()->putJson(route('admin.contenido.tabla.guardar', $doc), TablaContenido::vacia())->assertNotFound();
    }

    public function test_la_plantilla_calendario_crea_la_tabla_preconfigurada(): void
    {
        $id = $this->como()->postJson(route('admin.contenido.store'), [
            'tipo' => 'tabla', 'nombre' => 'Calendario', 'plantilla' => 'calendario',
        ])->assertCreated()->json('id');

        $datos = json_decode(ElementoContenido::findOrFail($id)->contenido, true);

        $this->assertSame(['Título', 'Red', 'Fecha', 'Estado', 'Responsable'], array_column($datos['columnas'], 'nombre'));
        $this->assertSame(['texto', 'seleccion', 'fecha', 'seleccion', 'texto'], array_column($datos['columnas'], 'tipo'));
        $this->assertSame([], $datos['filas']);
        // La plantilla debe pasar su propia validación.
        $this->assertSame($datos, TablaContenido::normalizar($datos));
    }

    // ---------- CSV ----------

    public function test_exporta_csv_y_protege_contra_formulas(): void
    {
        $datos = $this->tablaBasica();
        $datos['columnas'][] = ['id' => 'c5', 'nombre' => '=Nota', 'tipo' => 'texto', 'opciones' => []];
        $datos['filas'] = [
            ['id' => 'f1', 'celdas' => ['c1' => '=HYPERLINK("http://malo")', 'c2' => '-5', 'c3' => '2026-11-05', 'c4' => 'Idea', 'c5' => '@cmd']],
            ['id' => 'f2', 'celdas' => ['c1' => '+1+1', 'c5' => '-2+3']],
            ['id' => 'f3', 'celdas' => ['c1' => 'Normal, con coma', 'c5' => "linea\ndos"]],
        ];
        $tabla = $this->tabla($datos);

        $res = $this->como()->get(route('admin.contenido.tabla.exportar', $tabla));
        $res->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));
        $csv = $res->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=Nota", $csv);                  // encabezados también
        $this->assertStringContainsString("\"'=HYPERLINK(\"\"http://malo\"\")\"", $csv);
        $this->assertStringContainsString("'+1+1", $csv);
        $this->assertStringContainsString("'@cmd", $csv);
        $this->assertStringContainsString("'-2+3", $csv);
        $this->assertStringContainsString(',-5,', $csv);                   // un número válido no se altera
        $this->assertStringContainsString('"Normal, con coma"', $csv);

        // Ninguna celda del CSV empieza con un carácter de fórmula.
        $filas = TablaContenido::filasCsv(TablaContenido::normalizar($datos));
        foreach ($filas as $i => $fila) {
            foreach ($fila as $j => $celda) {
                if ($i > 0 && $j === 1 && $celda !== '') {
                    continue; // columna numérica validada
                }
                $this->assertDoesNotMatchRegularExpression('/^[=+\-@\t\r]/', $celda, "fila $i col $j");
            }
        }
    }

    public function test_importa_csv_reemplazando_la_tabla_e_infiriendo_tipos(): void
    {
        $tabla = $this->tabla($this->tablaBasica());
        $csv = "Nombre,Cantidad,Día\nAna,3,2026-01-02\nLuis,4.5,2026-02-03\n";

        $res = $this->como()->postJson(route('admin.contenido.tabla.importar', $tabla), [
            'archivo' => UploadedFile::fake()->createWithContent('datos.csv', $csv),
            'modo' => 'reemplazar',
        ])->assertOk();

        $this->assertSame(2, $res->json('importadas'));
        $datos = json_decode($tabla->fresh()->contenido, true);
        $this->assertSame(['Nombre', 'Cantidad', 'Día'], array_column($datos['columnas'], 'nombre'));
        $this->assertSame(['texto', 'numero', 'fecha'], array_column($datos['columnas'], 'tipo'));
        $this->assertCount(2, $datos['filas']);
        $this->assertSame('Ana', $datos['filas'][0]['celdas']['c1']);
    }

    public function test_importa_csv_agregando_filas_por_nombre_de_columna(): void
    {
        $tabla = $this->tabla($this->tablaBasica());
        $csv = "estado;TÍTULO;Extra\nListo;Post dos;x\nNuevo;Post tres;y\n"; // punto y coma, otro orden, mayúsculas

        $res = $this->como()->postJson(route('admin.contenido.tabla.importar', $tabla), [
            'archivo' => UploadedFile::fake()->createWithContent('mas.csv', $csv),
            'modo' => 'agregar',
        ])->assertOk();

        $this->assertSame(2, $res->json('importadas'));
        $this->assertSame(1, $res->json('ignoradas'));

        $datos = json_decode($tabla->fresh()->contenido, true);
        $this->assertCount(3, $datos['filas']);
        $this->assertSame('Post dos', $datos['filas'][1]['celdas']['c1']);
        $this->assertSame('Listo', $datos['filas'][1]['celdas']['c4']);
        // La opción nueva se agrega a la selección para que la fila sea válida.
        $this->assertContains('Nuevo', $datos['columnas'][3]['opciones']);
        $this->assertSame('Nuevo', $datos['filas'][2]['celdas']['c4']);
        $this->assertSame('Post uno', $datos['filas'][0]['celdas']['c1']);
        $this->assertCount(4, $datos['columnas']);
    }

    public function test_la_importacion_no_deja_pasar_formulas_ni_valores_incoherentes(): void
    {
        $tabla = $this->tabla($this->tablaBasica());
        $csv = "Título,Monto,Fecha\n\"'=SUM(1)\",no-es-numero,31/12/2026\n";

        $this->como()->postJson(route('admin.contenido.tabla.importar', $tabla), [
            'archivo' => UploadedFile::fake()->createWithContent('x.csv', $csv),
            'modo' => 'agregar',
        ])->assertOk();

        $fila = json_decode($tabla->fresh()->contenido, true)['filas'][1]['celdas'];
        $this->assertSame('=SUM(1)', $fila['c1']);   // se quita solo el apóstrofo de nuestra exportación
        $this->assertSame('', $fila['c2']);          // texto en columna numérica
        $this->assertSame('', $fila['c3']);          // fecha en formato no ISO

        // Y al exportarla otra vez se vuelve a proteger.
        $csvSalida = $this->como()->get(route('admin.contenido.tabla.exportar', $tabla))->streamedContent();
        $this->assertStringContainsString("'=SUM(1)", $csvSalida);
    }

    public function test_la_importacion_rechaza_archivos_invalidos(): void
    {
        $tabla = $this->tabla($this->tablaBasica());
        $url = route('admin.contenido.tabla.importar', $tabla);

        $this->como()->postJson($url, ['archivo' => UploadedFile::fake()->create('a.exe', 5), 'modo' => 'agregar'])->assertStatus(422);
        $this->como()->postJson($url, ['archivo' => UploadedFile::fake()->create('grande.csv', 3000, 'text/csv'), 'modo' => 'agregar'])->assertStatus(422);
        $this->como()->postJson($url, ['archivo' => UploadedFile::fake()->createWithContent('v.csv', ''), 'modo' => 'agregar'])->assertStatus(422);
        $this->como()->postJson($url, ['archivo' => UploadedFile::fake()->createWithContent('v.csv', "Otra\nx\n"), 'modo' => 'agregar'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ninguna columna del CSV coincide con las de la tabla.');
        $this->como()->postJson($url, ['archivo' => UploadedFile::fake()->createWithContent('v.csv', "a\nb\n"), 'modo' => 'borrar'])->assertStatus(422);

        // Nada de lo anterior tocó la tabla.
        $this->assertCount(1, json_decode($tabla->fresh()->contenido, true)['filas']);
    }

    public function test_importar_respeta_el_tope_de_filas(): void
    {
        $tabla = $this->tabla();
        $csv = "A\n".str_repeat("x\n", TablaContenido::MAX_FILAS + 1);

        $this->como()->postJson(route('admin.contenido.tabla.importar', $tabla), [
            'archivo' => UploadedFile::fake()->createWithContent('g.csv', $csv),
            'modo' => 'reemplazar',
        ])->assertStatus(422);
    }

    public function test_exportar_e_importar_conserva_los_datos(): void
    {
        $original = $this->tablaBasica();
        $original['filas'][] = ['id' => 'f2', 'celdas' => ['c1' => '=peligro', 'c2' => '-3.5', 'c3' => '2026-12-01', 'c4' => 'Listo']];
        $tabla = $this->tabla($original);

        $csv = $this->como()->get(route('admin.contenido.tabla.exportar', $tabla))->streamedContent();
        $otra = $this->tabla();

        $this->como()->postJson(route('admin.contenido.tabla.importar', $otra), [
            'archivo' => UploadedFile::fake()->createWithContent('ida-vuelta.csv', $csv),
            'modo' => 'reemplazar',
        ])->assertOk();

        $datos = json_decode($otra->fresh()->contenido, true);
        $this->assertSame('=peligro', $datos['filas'][1]['celdas']['c1']);
        $this->assertSame('-3.5', $datos['filas'][1]['celdas']['c2']);
        $this->assertSame(['Título', 'Monto', 'Fecha', 'Estado'], array_column($datos['columnas'], 'nombre'));
    }
}
