<?php

namespace Tests\Feature\Admin;

use App\Enums\AreaReporte;
use App\Enums\EstadoReporte;
use App\Enums\TipoSeccion;
use App\Models\Cliente;
use App\Models\Reporte;
use App\Models\ReporteSeccion;
use App\Models\User;
use App\Support\Reportes\EsquemaSeccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Editor de secciones: autosave, validación por tipo, reordenación y pegado
 * masivo desde hoja de cálculo (ReporteSeccionController).
 *
 * Las rutas de sección cuelgan del reporte y van con scopeBindings(), así que
 * todas llevan DOS parámetros: {reporte} y {seccion}. Ese anidamiento es la
 * comprobación de pertenencia (ver los tests de 404 al final).
 *
 * B6 no tiene test: es un fallo del editor en el navegador (la interacción de
 * la rejilla), no del backend. No hay nada que un test de Feature pueda
 * observar aquí; se verifica en el propio editor.
 */
class ReporteSeccionesTest extends TestCase
{
    use RefreshDatabase;

    /** No hay ReporteFactory ni ReporteSeccionFactory: helpers a mano. */
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

    private function seccion(TipoSeccion $tipo, array $overrides = []): ReporteSeccion
    {
        $reporte = $overrides['reporte'] ?? $this->reporte();
        unset($overrides['reporte']);

        return ReporteSeccion::create(array_merge([
            'reporte_id' => $reporte->id,
            'tipo' => $tipo->value,
            'titulo' => 'Sección '.$tipo->label(),
            'orden' => 1,
            'contenido' => EsquemaSeccion::vacio($tipo),
            'visible' => true,
        ], $overrides));
    }

    /**
     * URL de una ruta de sección. El reporte se toma del propio registro: es
     * el par que el scoped binding espera y el que la vista genera.
     */
    private function ruta(string $nombre, ReporteSeccion $seccion): string
    {
        return route($nombre, [$seccion->reporte_id, $seccion->id]);
    }

    /** Fila de tabla en la forma nueva: valores anidados, metadatos en la raíz. */
    private function fila(array $valores, bool $destacada = false, string $motivo = ''): array
    {
        return ['valores' => $valores, 'destacada' => $destacada, 'motivo' => $motivo];
    }

    /** Tabla con las mismas columnas que siembra la plantilla SEO de "Consultas". */
    private function tablaConsultas(array $overrides = []): ReporteSeccion
    {
        $contenido = EsquemaSeccion::vacio(TipoSeccion::Tabla);
        $contenido['columnas'] = [
            ['clave' => 'consulta', 'titulo' => 'Consulta', 'tipo' => 'texto', 'alineacion' => 'izquierda'],
            ['clave' => 'clics', 'titulo' => 'Clics', 'tipo' => 'numero', 'alineacion' => 'derecha'],
            ['clave' => 'impresiones', 'titulo' => 'Impresiones', 'tipo' => 'numero', 'alineacion' => 'derecha'],
            ['clave' => 'ctr', 'titulo' => 'CTR', 'tipo' => 'porcentaje', 'alineacion' => 'derecha'],
            ['clave' => 'posicion', 'titulo' => 'Posición media', 'tipo' => 'decimal', 'alineacion' => 'derecha'],
        ];
        $contenido['totales'] = ['clics' => 'suma', 'impresiones' => 'suma', 'ctr' => 'ctr'];

        return $this->seccion(TipoSeccion::Tabla, array_merge(['contenido' => $contenido], $overrides));
    }

    // --- store ------------------------------------------------------------

    public function test_store_appends_a_new_empty_section_at_the_end(): void
    {
        $user = User::factory()->create();
        $reporte = $this->reporte();
        $this->seccion(TipoSeccion::Texto, ['reporte' => $reporte, 'orden' => 1]);
        $this->seccion(TipoSeccion::Kpis, ['reporte' => $reporte, 'orden' => 2]);

        $response = $this->actingAs($user)->postJson(route('admin.reportes.secciones.store', $reporte), [
            'tipo' => 'tabla',
            'titulo' => 'Tabla nueva',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('tipo', 'tabla');
        $response->assertJsonPath('titulo', 'Tabla nueva');
        $response->assertJsonPath('orden', 3);

        $seccion = ReporteSeccion::where('titulo', 'Tabla nueva')->firstOrFail();
        $this->assertTrue($seccion->visible);
        $this->assertEquals(EsquemaSeccion::vacio(TipoSeccion::Tabla), $seccion->contenido);
    }

    /**
     * Regresión: `ReporteSeccion::toRow()` devolvía `'visible' => $this->visible`,
     * y `visible` es también el nombre de la propiedad protegida
     * `HidesAttributes::$visible` (la lista blanca de atributos serializables de
     * Eloquent, inicializada a `[]`). Dentro de la clase, `$this->visible`
     * resolvía a ESA propiedad en vez de pasar por el cast `boolean`, así que la
     * respuesta AJAX traía siempre `"visible":[]` —falsy en JS— sin importar lo
     * que dijera la columna. Se lee con `getAttribute('visible')`.
     */
    public function test_seccion_row_reports_visible_as_a_boolean(): void
    {
        $user = User::factory()->create();
        $seccion = $this->seccion(TipoSeccion::Texto, ['visible' => true]);

        $this->actingAs($user)
            ->putJson($this->ruta('admin.reportes.secciones.update', $seccion), [
                'titulo' => 'Alcance del reporte',
                'contenido' => ['formato' => 'pares', 'bloques' => []],
            ])
            ->assertOk()
            ->assertJsonPath('visible', true);
    }

    // --- update (autosave) -------------------------------------------------

    public function test_autosave_persists_full_tabla_contenido(): void
    {
        $user = User::factory()->create();
        $seccion = $this->tablaConsultas();

        $contenido = $seccion->contenido;
        $contenido['filas'] = [
            $this->fila(
                ['consulta' => 'dentista cdmx', 'clics' => 120, 'impresiones' => 3000, 'ctr' => 0.04, 'posicion' => 4.2],
                destacada: true,
                motivo: 'Principal consulta transaccional',
            ),
            $this->fila(['consulta' => 'ortodoncia', 'clics' => 30, 'impresiones' => 900, 'ctr' => 0.0333, 'posicion' => 11.5]),
        ];
        // Una fila excluida no lleva `destacada`: el esquema no declara regla
        // para ese campo aquí (destacar algo que se dejó fuera no significa
        // nada), así que `validate()` lo descartaría y el contenido guardado no
        // coincidiría con el enviado.
        $contenido['filas_excluidas'] = [
            [
                'valores' => ['consulta' => 'marca propia', 'clics' => 900, 'impresiones' => 1000, 'ctr' => 0.9, 'posicion' => 1.1],
                'motivo' => 'Tráfico de marca',
            ],
        ];
        $contenido['nota_excluidas'] = 'Se excluyen las consultas de marca.';
        $contenido['nota'] = 'Datos de Search Console.';

        $response = $this->actingAs($user)->putJson($this->ruta('admin.reportes.secciones.update', $seccion), [
            'titulo' => 'Consultas',
            'contenido' => $contenido,
        ]);

        $response->assertOk();
        $response->assertJsonPath('titulo', 'Consultas');

        $seccion->refresh();
        $this->assertSame('Consultas', $seccion->titulo);
        $this->assertEquals($contenido, $seccion->contenido);
        $this->assertCount(2, $seccion->contenido['filas']);
        $this->assertSame(120, $seccion->contenido['filas'][0]['valores']['clics']);
        $this->assertTrue($seccion->contenido['filas'][0]['destacada']);
        $this->assertSame('Principal consulta transaccional', $seccion->contenido['filas'][0]['motivo']);
        $this->assertCount(1, $seccion->contenido['filas_excluidas']);
        $this->assertSame('Tráfico de marca', $seccion->contenido['filas_excluidas'][0]['motivo']);
        $this->assertSame('Se excluyen las consultas de marca.', $seccion->contenido['nota_excluidas']);
    }

    public function test_autosave_persists_full_plan_contenido(): void
    {
        $user = User::factory()->create();
        $seccion = $this->seccion(TipoSeccion::Plan);

        $contenido = [
            'acciones' => [
                [
                    'prioridad' => 'p0',
                    'accion' => 'Corregir canonical de las fichas de producto',
                    'evidencia' => '312 URLs con canonical cruzado',
                    'area' => 'Técnico',
                    'impacto' => 5,
                    'esfuerzo' => 2,
                    'kpi' => 'Páginas indexadas',
                ],
                [
                    'prioridad' => 'p2',
                    'accion' => 'Reescribir metas de la categoría blog',
                    'evidencia' => 'CTR por debajo de la media',
                    'area' => 'Contenido',
                    'impacto' => 3,
                    'esfuerzo' => 3,
                    'kpi' => 'CTR orgánico',
                ],
            ],
            'leyenda' => 'Impacto y esfuerzo de 1 a 5.',
        ];

        $response = $this->actingAs($user)->putJson($this->ruta('admin.reportes.secciones.update', $seccion), [
            'titulo' => 'Plan de acción',
            'contenido' => $contenido,
        ]);

        $response->assertOk();

        $seccion->refresh();
        $this->assertEquals($contenido, $seccion->contenido);
        $this->assertSame('p0', $seccion->contenido['acciones'][0]['prioridad']);
        $this->assertSame(5, $seccion->contenido['acciones'][0]['impacto']);
    }

    /** La ficha guarda cada valor con su estado de semáforo y su dimensión. */
    public function test_autosave_persists_ficha_valores_with_their_estado(): void
    {
        $user = User::factory()->create();
        $seccion = $this->seccion(TipoSeccion::Ficha);

        $contenido = [
            'dimensiones' => [['clave' => 'tecnico', 'titulo' => 'Técnico']],
            'campos' => [
                ['clave' => 'indexable', 'titulo' => 'Indexable', 'dimension' => 'tecnico'],
                ['clave' => 'canonical', 'titulo' => 'Canonical', 'dimension' => 'tecnico'],
            ],
            'registros' => [[
                'titulo' => 'Home',
                'valores' => [
                    'indexable' => ['valor' => 'Sí', 'estado' => 'ok'],
                    'canonical' => ['valor' => 'Cruzado', 'estado' => 'critico'],
                ],
                'veredicto' => 'Corregir canonical',
                'estado' => 'critico',
            ]],
            'nota' => '',
        ];

        $this->actingAs($user)
            ->putJson($this->ruta('admin.reportes.secciones.update', $seccion), [
                'titulo' => 'Auditoría de plantillas',
                'contenido' => $contenido,
            ])
            ->assertOk();

        $seccion->refresh();
        $this->assertEquals($contenido, $seccion->contenido);
        $this->assertSame('critico', $seccion->contenido['registros'][0]['valores']['canonical']['estado']);
        $this->assertSame('tecnico', $seccion->contenido['campos'][0]['dimension']);
    }

    /** La serie guarda el eje y el color de cada línea. */
    public function test_autosave_persists_serie_eje_and_color(): void
    {
        $user = User::factory()->create();
        $seccion = $this->seccion(TipoSeccion::Serie);

        $contenido = [
            'etiqueta_x' => 'Fecha',
            'series' => [
                ['clave' => 'clics', 'titulo' => 'Clics', 'eje' => 'izq', 'color' => '#1f2937'],
                ['clave' => 'impresiones', 'titulo' => 'Impresiones', 'eje' => 'der', 'color' => '#9ca3af'],
            ],
            'filas' => [['x' => '2026-08-01', 'valores' => ['clics' => 10, 'impresiones' => 300]]],
            'lectura' => 'Las impresiones crecen antes que los clics.',
        ];

        $this->actingAs($user)
            ->putJson($this->ruta('admin.reportes.secciones.update', $seccion), [
                'titulo' => 'Tráfico diario',
                'contenido' => $contenido,
            ])
            ->assertOk();

        $seccion->refresh();
        $this->assertSame('der', $seccion->contenido['series'][1]['eje']);
        $this->assertSame('#9ca3af', $seccion->contenido['series'][1]['color']);
    }

    public function test_autosave_rejects_an_unknown_eje_for_a_serie(): void
    {
        $user = User::factory()->create();
        $seccion = $this->seccion(TipoSeccion::Serie);

        $this->actingAs($user)
            ->putJson($this->ruta('admin.reportes.secciones.update', $seccion), [
                'titulo' => 'Tráfico diario',
                'contenido' => [
                    'series' => [['clave' => 'clics', 'titulo' => 'Clics', 'eje' => 'centro']],
                    'filas' => [],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('contenido.series.0.eje');
    }

    /**
     * Regresión: el autosave del editor manda título y contenido, pero no
     * `visible` (ese conmutador es otro control). Un guardado de contenido no
     * puede ocultar del entregable una sección que estaba visible.
     */
    public function test_autosave_without_visible_key_keeps_current_visible_value(): void
    {
        $user = User::factory()->create();
        $visible = $this->seccion(TipoSeccion::Texto, ['visible' => true]);
        $oculta = $this->seccion(TipoSeccion::Texto, ['visible' => false]);

        $payload = [
            'titulo' => 'Alcance del reporte',
            'contenido' => ['formato' => 'pares', 'bloques' => [['titulo' => 'Sitio', 'cuerpo' => 'ejemplo.com']]],
        ];

        $this->actingAs($user)
            ->putJson($this->ruta('admin.reportes.secciones.update', $visible), $payload)
            ->assertOk();

        $this->actingAs($user)
            ->putJson($this->ruta('admin.reportes.secciones.update', $oculta), $payload)
            ->assertOk();

        $this->assertTrue($visible->refresh()->visible);
        $this->assertDatabaseHas('reporte_secciones', ['id' => $visible->id, 'visible' => 1]);
        $this->assertFalse($oculta->refresh()->visible);
        $this->assertDatabaseHas('reporte_secciones', ['id' => $oculta->id, 'visible' => 0]);
    }

    public function test_autosave_with_visible_key_does_change_visibility(): void
    {
        $user = User::factory()->create();
        $seccion = $this->seccion(TipoSeccion::Texto, ['visible' => true]);

        $this->actingAs($user)->putJson($this->ruta('admin.reportes.secciones.update', $seccion), [
            'titulo' => 'Alcance del reporte',
            'visible' => false,
            'contenido' => ['formato' => 'pares', 'bloques' => []],
        ])->assertOk();

        $this->assertFalse($seccion->refresh()->visible);
        $this->assertDatabaseHas('reporte_secciones', ['id' => $seccion->id, 'visible' => 0]);
    }

    // --- update: rótulo y aviso (B26 / B31) --------------------------------

    /**
     * B26 · El rótulo («SECCIÓN 2», «ANEXO A») y el aviso de dato
     * desactualizado son elementos de primera clase del diseño: el editor los
     * manda en el mismo autosave y tienen que persistir.
     */
    public function test_autosave_persists_rotulo_and_aviso(): void
    {
        $user = User::factory()->create();
        $seccion = $this->seccion(TipoSeccion::Texto);

        $this->actingAs($user)->putJson($this->ruta('admin.reportes.secciones.update', $seccion), [
            'titulo' => 'Alcance del reporte',
            'rotulo' => 'SECCIÓN 2',
            'aviso' => ['texto' => 'Search Console no había consolidado agosto', 'fecha' => '18 ago 2026'],
            'contenido' => ['formato' => 'pares', 'bloques' => []],
        ])->assertOk()
            ->assertJsonPath('rotulo', 'SECCIÓN 2')
            ->assertJsonPath('aviso.texto', 'Search Console no había consolidado agosto');

        $seccion->refresh();
        $this->assertSame('SECCIÓN 2', $seccion->rotulo);
        // assertEquals y no assertSame: MySQL normaliza el orden de las claves
        // de una columna JSON al almacenarla.
        $this->assertEquals([
            'texto' => 'Search Console no había consolidado agosto',
            'fecha' => '18 ago 2026',
        ], $seccion->aviso);
    }

    /**
     * B31 · Un aviso sin texto no es un aviso: se guarda null para que los
     * renderizadores no tengan que distinguir entre ausente y vacío (un aviso
     * `{texto:'', fecha:''}` pintaba el bloque rojo del diseño en blanco).
     */
    public function test_autosave_stores_an_aviso_without_texto_as_null(): void
    {
        $user = User::factory()->create();
        $seccion = $this->seccion(TipoSeccion::Texto, [
            'aviso' => ['texto' => 'Aviso previo', 'fecha' => '01 ago 2026'],
        ]);

        $this->actingAs($user)->putJson($this->ruta('admin.reportes.secciones.update', $seccion), [
            'titulo' => 'Alcance del reporte',
            'aviso' => ['texto' => '', 'fecha' => '18 ago 2026'],
            'contenido' => ['formato' => 'pares', 'bloques' => []],
        ])->assertOk()
            ->assertJsonPath('aviso', null);

        $this->assertNull($seccion->refresh()->aviso);
        $this->assertDatabaseHas('reporte_secciones', ['id' => $seccion->id, 'aviso' => null]);
    }

    /** Sin `rotulo` en el payload, la columna queda a null, no a cadena vacía. */
    public function test_autosave_clears_rotulo_when_the_editor_stops_sending_it(): void
    {
        $user = User::factory()->create();
        $seccion = $this->seccion(TipoSeccion::Texto, ['rotulo' => 'SECCIÓN 2']);

        $this->actingAs($user)->putJson($this->ruta('admin.reportes.secciones.update', $seccion), [
            'titulo' => 'Alcance del reporte',
            'contenido' => ['formato' => 'pares', 'bloques' => []],
        ])->assertOk();

        $this->assertNull($seccion->refresh()->rotulo);
    }

    // --- update: validación por tipo --------------------------------------

    public function test_autosave_rejects_plan_with_impacto_out_of_range(): void
    {
        $user = User::factory()->create();
        $seccion = $this->seccion(TipoSeccion::Plan);

        $response = $this->actingAs($user)->putJson($this->ruta('admin.reportes.secciones.update', $seccion), [
            'titulo' => 'Plan de acción',
            'contenido' => [
                'acciones' => [[
                    'prioridad' => 'p1',
                    'accion' => 'Acción con impacto imposible',
                    'impacto' => 9,
                    'esfuerzo' => 2,
                ]],
                'leyenda' => '',
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('contenido.acciones.0.impacto');

        $this->assertSame([], $seccion->refresh()->contenido['acciones']);
    }

    public function test_autosave_rejects_hallazgos_with_invalid_severidad(): void
    {
        $user = User::factory()->create();
        $seccion = $this->seccion(TipoSeccion::Hallazgos);

        $response = $this->actingAs($user)->putJson($this->ruta('admin.reportes.secciones.update', $seccion), [
            'titulo' => 'Hallazgos críticos',
            'contenido' => [
                'items' => [[
                    'titulo' => 'Hallazgo con severidad inventada',
                    'severidad' => 'catastrofico',
                ]],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('contenido.items.0.severidad');

        $this->assertSame([], $seccion->refresh()->contenido['items']);
    }

    /**
     * B2 · Dos columnas con la misma clave. Sin la regla `distinct`, renombrar
     * una clave a otra ya existente pisaba los datos de la otra columna en
     * TODAS las filas —el mapa de valores solo admite una entrada por clave— y
     * el backend lo aceptaba con un 200.
     */
    public function test_autosave_rejects_two_tabla_columns_sharing_the_same_clave(): void
    {
        $user = User::factory()->create();
        $seccion = $this->tablaConsultas();

        $contenido = $seccion->contenido;
        $contenido['columnas'] = [
            ['clave' => 'clics', 'titulo' => 'Clics', 'tipo' => 'numero'],
            ['clave' => 'clics', 'titulo' => 'Clics del periodo anterior', 'tipo' => 'numero'],
        ];

        $response = $this->actingAs($user)->putJson($this->ruta('admin.reportes.secciones.update', $seccion), [
            'titulo' => 'Consultas',
            'contenido' => $contenido,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('contenido.columnas.1.clave');

        // Nada se ha guardado: la sección conserva sus cinco columnas.
        $this->assertCount(5, $seccion->refresh()->contenido['columnas']);
    }

    /** B3 · Una clave vacía tampoco vale: sin clave no hay dónde guardar la celda. */
    public function test_autosave_rejects_a_tabla_column_with_an_empty_clave(): void
    {
        $user = User::factory()->create();
        $seccion = $this->tablaConsultas();

        $contenido = $seccion->contenido;
        $contenido['columnas'] = [
            ['clave' => 'consulta', 'titulo' => 'Consulta', 'tipo' => 'texto'],
            ['clave' => '', 'titulo' => 'Columna sin clave', 'tipo' => 'numero'],
        ];

        $response = $this->actingAs($user)->putJson($this->ruta('admin.reportes.secciones.update', $seccion), [
            'titulo' => 'Consultas',
            'contenido' => $contenido,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('contenido.columnas.1.clave');

        $this->assertCount(5, $seccion->refresh()->contenido['columnas']);
    }

    /** La misma regla `distinct` protege las series de una gráfica. */
    public function test_autosave_rejects_two_series_sharing_the_same_clave(): void
    {
        $user = User::factory()->create();
        $seccion = $this->seccion(TipoSeccion::Serie);

        $response = $this->actingAs($user)->putJson($this->ruta('admin.reportes.secciones.update', $seccion), [
            'titulo' => 'Tráfico diario',
            'contenido' => [
                'series' => [
                    ['clave' => 'clics', 'titulo' => 'Clics'],
                    ['clave' => 'clics', 'titulo' => 'Clics del año pasado'],
                ],
                'filas' => [],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('contenido.series.1.clave');

        $this->assertSame([], $seccion->refresh()->contenido['series']);
    }

    /** Y los campos de una ficha. */
    public function test_autosave_rejects_two_ficha_campos_sharing_the_same_clave(): void
    {
        $user = User::factory()->create();
        $seccion = $this->seccion(TipoSeccion::Ficha);

        $response = $this->actingAs($user)->putJson($this->ruta('admin.reportes.secciones.update', $seccion), [
            'titulo' => 'Auditoría',
            'contenido' => [
                'campos' => [
                    ['clave' => 'title', 'titulo' => 'Title'],
                    ['clave' => 'title', 'titulo' => 'Title duplicado'],
                ],
                'registros' => [],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('contenido.campos.1.clave');

        $this->assertSame([], $seccion->refresh()->contenido['campos']);
    }

    // --- update: normalización de lo tecleado (B11) ------------------------

    /**
     * B11 · Hay DOS puertas de entrada al mismo dato —el autosave del editor y
     * el pegado masivo— y antes solo la segunda normalizaba: un `1,5` pegado se
     * guardaba como 1.5 y el mismo `1,5` tecleado se quedaba como la cadena
     * "1,5", que el Armador acababa leyendo como 15. El valor almacenado no
     * puede depender de por dónde entró.
     */
    public function test_a_typed_value_and_the_same_pasted_value_are_stored_identically(): void
    {
        $user = User::factory()->create();

        $tecleada = $this->tablaConsultas();
        $contenido = $tecleada->contenido;
        $contenido['filas'] = [
            $this->fila(['consulta' => 'dentista cdmx', 'clics' => '1.068', 'impresiones' => '34.500', 'ctr' => '3,09%', 'posicion' => '1,5']),
        ];

        $this->actingAs($user)->putJson($this->ruta('admin.reportes.secciones.update', $tecleada), [
            'titulo' => 'Consultas',
            'contenido' => $contenido,
        ])->assertOk();

        $pegada = $this->tablaConsultas();
        $this->actingAs($user)->postJson($this->ruta('admin.reportes.secciones.pegar', $pegada), [
            'texto' => "dentista cdmx\t1.068\t34.500\t3,09%\t1,5",
        ])->assertCreated();

        $porAutosave = $tecleada->refresh()->contenido['filas'][0]['valores'];
        $porPegado = $pegada->refresh()->contenido['filas'][0]['valores'];

        $this->assertSame(1.5, $porAutosave['posicion']);
        $this->assertSame($porPegado['clics'], $porAutosave['clics']);
        $this->assertSame($porPegado['impresiones'], $porAutosave['impresiones']);
        $this->assertSame($porPegado['ctr'], $porAutosave['ctr']);
        $this->assertSame($porPegado['posicion'], $porAutosave['posicion']);
    }

    /**
     * La normalización del autosave respeta las columnas de texto: ahí «1,5 s»
     * es un dato legítimo y "limpiarlo" lo destruiría.
     */
    public function test_autosave_leaves_text_columns_untouched(): void
    {
        $user = User::factory()->create();
        $seccion = $this->tablaConsultas();

        $contenido = $seccion->contenido;
        $contenido['filas'] = [$this->fila(['consulta' => '1,5 s de espera', 'clics' => 3])];

        $this->actingAs($user)->putJson($this->ruta('admin.reportes.secciones.update', $seccion), [
            'titulo' => 'Consultas',
            'contenido' => $contenido,
        ])->assertOk();

        $this->assertSame('1,5 s de espera', $seccion->refresh()->contenido['filas'][0]['valores']['consulta']);
    }

    // --- destroy -----------------------------------------------------------

    public function test_destroy_removes_the_section(): void
    {
        $user = User::factory()->create();
        $seccion = $this->seccion(TipoSeccion::Texto);

        $this->actingAs($user)
            ->deleteJson($this->ruta('admin.reportes.secciones.destroy', $seccion))
            ->assertOk()
            ->assertJson(['deleted' => true]);

        $this->assertDatabaseMissing('reporte_secciones', ['id' => $seccion->id]);
    }

    // --- reordenar ---------------------------------------------------------

    public function test_reordenar_rewrites_orden_from_1_to_n(): void
    {
        $user = User::factory()->create();
        $reporte = $this->reporte();
        $a = $this->seccion(TipoSeccion::Texto, ['reporte' => $reporte, 'orden' => 1]);
        $b = $this->seccion(TipoSeccion::Kpis, ['reporte' => $reporte, 'orden' => 2]);
        $c = $this->seccion(TipoSeccion::Plan, ['reporte' => $reporte, 'orden' => 3]);

        $response = $this->actingAs($user)->postJson(route('admin.reportes.secciones.reordenar', $reporte), [
            'orden' => [$c->id, $a->id, $b->id],
        ]);

        $response->assertOk();
        $response->assertJson(['orden' => [$c->id, $a->id, $b->id]]);

        $this->assertSame(1, $c->refresh()->orden);
        $this->assertSame(2, $a->refresh()->orden);
        $this->assertSame(3, $b->refresh()->orden);
    }

    public function test_reordenar_rejects_a_section_from_another_reporte_and_changes_nothing(): void
    {
        $user = User::factory()->create();
        $reporte = $this->reporte();
        $otroReporte = $this->reporte(['titulo' => 'Otro reporte']);

        $a = $this->seccion(TipoSeccion::Texto, ['reporte' => $reporte, 'orden' => 1]);
        $b = $this->seccion(TipoSeccion::Kpis, ['reporte' => $reporte, 'orden' => 2]);
        $ajena = $this->seccion(TipoSeccion::Texto, ['reporte' => $otroReporte, 'orden' => 1]);

        $response = $this->actingAs($user)->postJson(route('admin.reportes.secciones.reordenar', $reporte), [
            'orden' => [$b->id, $ajena->id, $a->id],
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message']);

        $this->assertSame(1, $a->refresh()->orden);
        $this->assertSame(2, $b->refresh()->orden);
        $this->assertSame(1, $ajena->refresh()->orden);
    }

    // --- pertenencia: sección de otro reporte (B17) ------------------------

    /**
     * B17 · Las rutas de sección cuelgan del reporte y el grupo va con
     * scopeBindings(): Laravel resuelve {seccion} DENTRO del {reporte} de la
     * URL (Reporte::resolveChildRouteBinding) y devuelve 404 si no le
     * pertenece. Colgando de la raíz, el binding global dejaba que cualquier
     * usuario autenticado reescribiera o borrara la sección del reporte de otro
     * cliente con solo saber el id.
     */
    public function test_update_of_a_section_belonging_to_another_reporte_returns_404(): void
    {
        $user = User::factory()->create();
        $otroReporte = $this->reporte(['titulo' => 'Reporte de otro cliente']);
        $ajena = $this->seccion(TipoSeccion::Texto, ['titulo' => 'Sección ajena']);

        $this->actingAs($user)
            ->putJson(route('admin.reportes.secciones.update', [$otroReporte->id, $ajena->id]), [
                'titulo' => 'Título inyectado',
                'contenido' => ['formato' => 'pares', 'bloques' => []],
            ])
            ->assertNotFound();

        $this->assertSame('Sección ajena', $ajena->refresh()->titulo);
    }

    public function test_destroy_of_a_section_belonging_to_another_reporte_returns_404(): void
    {
        $user = User::factory()->create();
        $otroReporte = $this->reporte(['titulo' => 'Reporte de otro cliente']);
        $ajena = $this->seccion(TipoSeccion::Texto, ['titulo' => 'Sección ajena']);

        $this->actingAs($user)
            ->deleteJson(route('admin.reportes.secciones.destroy', [$otroReporte->id, $ajena->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('reporte_secciones', ['id' => $ajena->id]);
    }

    public function test_pegar_into_a_section_belonging_to_another_reporte_returns_404(): void
    {
        $user = User::factory()->create();
        $otroReporte = $this->reporte(['titulo' => 'Reporte de otro cliente']);
        $ajena = $this->tablaConsultas();

        $this->actingAs($user)
            ->postJson(route('admin.reportes.secciones.pegar', [$otroReporte->id, $ajena->id]), [
                'texto' => "consulta inyectada\t10\t100\t10,00%\t3,5",
            ])
            ->assertNotFound();

        $this->assertSame([], $ajena->refresh()->contenido['filas']);
    }

    // --- pegar -------------------------------------------------------------

    public function test_pegar_tab_separated_rows_into_a_tabla_creates_the_rows(): void
    {
        $user = User::factory()->create();
        $seccion = $this->tablaConsultas();

        $texto = implode("\n", [
            "dentista cdmx\t120\t3000\t4,00%\t4,2",
            "ortodoncia invisible\t30\t900\t3,33%\t11,5",
        ]);

        $response = $this->actingAs($user)->postJson($this->ruta('admin.reportes.secciones.pegar', $seccion), [
            'texto' => $texto,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('creadas', 2);
        $response->assertJson(['errores' => []]);

        $filas = $seccion->refresh()->contenido['filas'];
        $this->assertCount(2, $filas);
        // Forma nueva: los valores por columna van anidados bajo `valores`.
        $this->assertSame(['valores'], array_keys($filas[0]));
        $this->assertSame('dentista cdmx', $filas[0]['valores']['consulta']);
        $this->assertSame(120, $filas[0]['valores']['clics']);
        $this->assertSame(3000, $filas[0]['valores']['impresiones']);
        $this->assertSame(0.04, $filas[0]['valores']['ctr']);
        $this->assertSame(4.2, $filas[0]['valores']['posicion']);
        $this->assertSame('ortodoncia invisible', $filas[1]['valores']['consulta']);
    }

    /** El pegado de una ficha deja cada valor listo para que el equipo le ponga estado. */
    public function test_pegar_into_a_ficha_wraps_each_value_with_a_null_estado(): void
    {
        $user = User::factory()->create();
        $seccion = $this->seccion(TipoSeccion::Ficha, ['contenido' => [
            'dimensiones' => [],
            'campos' => [
                ['clave' => 'indexable', 'titulo' => 'Indexable'],
                ['clave' => 'canonical', 'titulo' => 'Canonical'],
            ],
            'registros' => [],
            'nota' => '',
        ]]);

        $this->actingAs($user)->postJson($this->ruta('admin.reportes.secciones.pegar', $seccion), [
            'texto' => "Home\tSí\tPropio\tTodo correcto",
        ])->assertCreated();

        $registro = $seccion->refresh()->contenido['registros'][0];

        $this->assertSame('Home', $registro['titulo']);
        $this->assertSame(['valor' => 'Sí', 'estado' => null], $registro['valores']['indexable']);
        $this->assertSame('Todo correcto', $registro['veredicto']);
    }

    /**
     * El pegado de una `tabla` no puede tener filas malas —sus filas son mapas
     * libres, sin reglas por campo—, así que el caso mixto se prueba sobre un
     * `plan`, cuyo esquema sí valida prioridad, impacto y esfuerzo.
     */
    public function test_pegar_mixed_rows_into_a_plan_creates_only_the_valid_ones(): void
    {
        $user = User::factory()->create();
        $seccion = $this->seccion(TipoSeccion::Plan);

        $filaMala = "p9\tAcción con prioridad inventada\tEvidencia\tTécnico\t4\t2\tKPI";
        $texto = implode("\n", [
            "p0\tCorregir canonical\tEvidencia uno\tTécnico\t5\t2\tIndexación",
            $filaMala,
            "p1\tReescribir metas\tEvidencia dos\tContenido\t4\t3\tCTR",
        ]);

        $response = $this->actingAs($user)->postJson($this->ruta('admin.reportes.secciones.pegar', $seccion), [
            'texto' => $texto,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('creadas', 2);
        $response->assertJsonCount(1, 'errores');
        $response->assertJsonPath('errores.0.fila', 2);
        $response->assertJsonPath('errores.0.texto', $filaMala);
        $this->assertNotEmpty($response->json('errores.0.errores'));

        $acciones = $seccion->refresh()->contenido['acciones'];
        $this->assertCount(2, $acciones);
        $this->assertSame('Corregir canonical', $acciones[0]['accion']);
        $this->assertSame('Reescribir metas', $acciones[1]['accion']);
        $this->assertSame(5, $acciones[0]['impacto']);
    }

    /**
     * B4 · El flujo real tras un pegado parcial: se corrigen en la hoja SOLO
     * las líneas que fallaron y se vuelven a pegar. Pegar siempre SUMA lo que
     * se le manda —nunca reemplaza lo capturado a mano ni lo ya pegado—, así
     * que reimportar únicamente las corregidas no puede duplicar las buenas.
     */
    public function test_repasting_only_the_corrected_rows_does_not_duplicate_the_good_ones(): void
    {
        $user = User::factory()->create();
        $seccion = $this->seccion(TipoSeccion::Plan);

        $mala = "p9\tAcción con prioridad inventada\tEvidencia\tTécnico\t4\t2\tKPI";

        $this->actingAs($user)->postJson($this->ruta('admin.reportes.secciones.pegar', $seccion), [
            'texto' => implode("\n", [
                "p0\tCorregir canonical\tEvidencia uno\tTécnico\t5\t2\tIndexación",
                $mala,
                "p1\tReescribir metas\tEvidencia dos\tContenido\t4\t3\tCTR",
            ]),
        ])->assertCreated()->assertJsonPath('creadas', 2);

        // Segundo pegado: solo la línea corregida, como haría el equipo.
        $this->actingAs($user)->postJson($this->ruta('admin.reportes.secciones.pegar', $seccion), [
            'texto' => "p2\tAcción con prioridad corregida\tEvidencia\tTécnico\t4\t2\tKPI",
        ])->assertCreated()->assertJsonPath('creadas', 1);

        $acciones = $seccion->refresh()->contenido['acciones'];

        $this->assertCount(3, $acciones);
        $this->assertSame([
            'Corregir canonical',
            'Reescribir metas',
            'Acción con prioridad corregida',
        ], array_column($acciones, 'accion'));
    }

    public function test_pegar_with_no_valid_rows_returns_422_and_creates_nothing(): void
    {
        $user = User::factory()->create();
        $seccion = $this->seccion(TipoSeccion::Plan);

        $texto = implode("\n", [
            "p9\tAcción uno\tEvidencia\tTécnico\t4\t2\tKPI",
            "px\tAcción dos\tEvidencia\tTécnico\t9\t0\tKPI",
        ]);

        $response = $this->actingAs($user)->postJson($this->ruta('admin.reportes.secciones.pegar', $seccion), [
            'texto' => $texto,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('creadas', 0);
        $response->assertJsonCount(2, 'errores');

        $this->assertSame([], $seccion->refresh()->contenido['acciones']);
    }

    public function test_pegar_into_a_texto_section_returns_422(): void
    {
        $user = User::factory()->create();
        $seccion = $this->seccion(TipoSeccion::Texto);

        $response = $this->actingAs($user)->postJson($this->ruta('admin.reportes.secciones.pegar', $seccion), [
            'texto' => "algo\tque pegar",
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message']);
        $response->assertJsonMissing(['creadas']);

        $this->assertEquals(EsquemaSeccion::vacio(TipoSeccion::Texto), $seccion->refresh()->contenido);
    }

    public function test_pegar_into_a_tabla_without_columns_returns_422(): void
    {
        $user = User::factory()->create();
        $seccion = $this->seccion(TipoSeccion::Tabla);

        $response = $this->actingAs($user)->postJson($this->ruta('admin.reportes.secciones.pegar', $seccion), [
            'texto' => "algo\t100\t200",
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message']);
        $response->assertJsonMissing(['creadas']);

        $this->assertSame([], $seccion->refresh()->contenido['filas']);
    }

    /**
     * B20 · `columnasPegado` devuelve [] cuando la sección no tiene todavía sus
     * columnas configuradas. Para una serie eso son las series: sin ellas el
     * mapeo posicional no sabe qué es cada valor y antes se descartaba todo en
     * silencio, dejando filas con solo el eje X y ningún dato.
     */
    public function test_pegar_into_a_serie_without_configured_series_returns_422_and_creates_no_rows(): void
    {
        $user = User::factory()->create();
        $seccion = $this->seccion(TipoSeccion::Serie);

        $response = $this->actingAs($user)->postJson($this->ruta('admin.reportes.secciones.pegar', $seccion), [
            'texto' => implode("\n", ["2026-08-01\t10\t300", "2026-08-02\t45\t120"]),
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message']);
        $response->assertJsonMissing(['creadas']);

        $this->assertSame([], $seccion->refresh()->contenido['filas']);
    }

    /** Con las series configuradas, el mismo pegado sí entra. */
    public function test_pegar_into_a_serie_with_configured_series_creates_the_rows(): void
    {
        $user = User::factory()->create();
        $seccion = $this->seccion(TipoSeccion::Serie, ['contenido' => [
            'etiqueta_x' => 'Fecha',
            'series' => [
                ['clave' => 'clics', 'titulo' => 'Clics', 'eje' => 'izq'],
                ['clave' => 'impresiones', 'titulo' => 'Impresiones', 'eje' => 'der'],
            ],
            'filas' => [],
            'lectura' => '',
        ]]);

        $this->actingAs($user)->postJson($this->ruta('admin.reportes.secciones.pegar', $seccion), [
            'texto' => implode("\n", ["2026-08-01\t10\t300", "2026-08-02\t45\t120"]),
        ])->assertCreated()->assertJsonPath('creadas', 2);

        $filas = $seccion->refresh()->contenido['filas'];

        $this->assertCount(2, $filas);
        // El `x` queda plano en la raíz; los valores por serie, anidados.
        $this->assertSame('2026-08-01', $filas[0]['x']);
        // El JSON de MySQL guarda 10.0 como 10, así que se compara el valor y
        // no el tipo exacto de PHP tras el round-trip.
        $this->assertEquals(10, $filas[0]['valores']['clics']);
        $this->assertEquals(300, $filas[0]['valores']['impresiones']);
    }

    /** Una ficha sin campos configurados es el mismo caso que la serie sin series. */
    public function test_pegar_into_a_ficha_without_configured_campos_returns_422(): void
    {
        $user = User::factory()->create();
        $seccion = $this->seccion(TipoSeccion::Ficha);

        $this->actingAs($user)->postJson($this->ruta('admin.reportes.secciones.pegar', $seccion), [
            'texto' => "Home\tSí\tPropio",
        ])->assertStatus(422)->assertJsonMissing(['creadas']);

        $this->assertSame([], $seccion->refresh()->contenido['registros']);
    }

    public function test_pegar_appends_to_existing_rows_instead_of_replacing_them(): void
    {
        $user = User::factory()->create();
        $seccion = $this->tablaConsultas();

        $contenido = $seccion->contenido;
        $contenido['filas'] = [
            $this->fila(['consulta' => 'capturada a mano', 'clics' => 5, 'impresiones' => 50, 'ctr' => 0.1, 'posicion' => 9.0]),
        ];
        $seccion->update(['contenido' => $contenido]);

        $this->actingAs($user)->postJson($this->ruta('admin.reportes.secciones.pegar', $seccion), [
            'texto' => "pegada uno\t10\t100\t10,00%\t3,5",
        ])->assertCreated();

        $filas = $seccion->refresh()->contenido['filas'];
        $this->assertCount(2, $filas);
        $this->assertSame('capturada a mano', $filas[0]['valores']['consulta']);
        $this->assertSame(5, $filas[0]['valores']['clics']);
        $this->assertSame('pegada uno', $filas[1]['valores']['consulta']);
    }

    /**
     * Normalización de celdas pegadas: separador de millares ambiguo en columna
     * entera, porcentaje en escala 0-100 y celda numérica en blanco.
     */
    public function test_pegar_normalizes_cells_by_column_type(): void
    {
        $user = User::factory()->create();
        $seccion = $this->tablaConsultas();

        // clics: '1.068' es mil sesenta y ocho, no 1,068 (columna `numero`).
        // ctr: '3,09%' se guarda como fracción.
        // posicion: celda vacía en columna numérica es null, no cero.
        $this->actingAs($user)->postJson($this->ruta('admin.reportes.secciones.pegar', $seccion), [
            'texto' => "consulta normalizada\t1.068\t34.500\t3,09%\t",
        ])->assertCreated();

        $fila = $seccion->refresh()->contenido['filas'][0]['valores'];

        $this->assertSame(1068, $fila['clics']);
        $this->assertSame(34500, $fila['impresiones']);
        $this->assertSame(0.0309, $fila['ctr']);
        $this->assertNull($fila['posicion']);
        $this->assertSame('consulta normalizada', $fila['consulta']);
    }

    public function test_pegar_requires_authentication(): void
    {
        $seccion = $this->tablaConsultas();

        $this->postJson($this->ruta('admin.reportes.secciones.pegar', $seccion), [
            'texto' => "algo\t1\t2\t3\t4",
        ])->assertUnauthorized();

        $this->assertSame([], $seccion->refresh()->contenido['filas']);
    }
}
