<?php

namespace Tests\Feature\Admin;

use App\Enums\TipoElementoContenido;
use App\Models\AdsCampana;
use App\Models\Cliente;
use App\Models\ElementoContenido;
use App\Models\Finanza;
use App\Models\Keyword;
use App\Models\Permission;
use App\Models\Proyecto;
use App\Models\Role;
use App\Models\Tarea;
use App\Models\User;
use App\Support\ClienteActivo;
use App\Support\Contenido\FuentesTabla;
use App\Support\Contenido\TablaContenido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Tabla editable de «Contenido», etapa 2: vistas calendario y galería, orden
 * persistente de filas, cambio de tipo de columna y «Llenar desde...».
 */
class ContenidoTablaMejorasTest extends TestCase
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

    private function como(?User $usuario = null, ?Cliente $cliente = null)
    {
        return $this->actingAs($usuario ?? $this->usuario)
            ->withSession([ClienteActivo::CLAVE => ($cliente ?? $this->cliente)->id]);
    }

    private function tabla(array $datos, ?Cliente $cliente = null): ElementoContenido
    {
        return ElementoContenido::create([
            'cliente_id' => ($cliente ?? $this->cliente)->id,
            'tipo' => TipoElementoContenido::Tabla->value,
            'nombre' => 'Mi tabla',
            'contenido' => json_encode($datos),
        ]);
    }

    private function base(): array
    {
        return [
            'columnas' => [
                ['id' => 'c1', 'nombre' => 'Título', 'tipo' => 'texto', 'opciones' => []],
                ['id' => 'c2', 'nombre' => 'Fecha', 'tipo' => 'fecha', 'opciones' => []],
                ['id' => 'c3', 'nombre' => 'Estado', 'tipo' => 'seleccion', 'opciones' => ['Idea', 'Listo']],
            ],
            'filas' => [
                ['id' => 'f1', 'celdas' => ['c1' => 'Uno', 'c2' => '2026-11-05', 'c3' => 'Idea']],
                ['id' => 'f2', 'celdas' => ['c1' => 'Dos', 'c2' => '2026-11-06', 'c3' => 'Listo']],
                ['id' => 'f3', 'celdas' => ['c1' => 'Tres', 'c2' => '', 'c3' => '']],
            ],
            'vista' => 'tabla',
            'columna_kanban' => 'c3',
        ];
    }

    /** Usuario con rol propio y solo los permisos pedidos (enciende el control por módulo). */
    private function usuarioConPermisos(array $modulos): User
    {
        config(['permisos.aplicar' => true]);
        $rol = Role::query()->create(['name' => 'rol-'.uniqid(), 'label' => 'Rol de prueba']);

        foreach ($modulos as $modulo) {
            $permiso = Permission::query()->firstOrCreate(['name' => $modulo], ['label' => $modulo, 'module' => $modulo]);
            $rol->permissions()->attach($permiso->id);
        }

        return User::factory()->create(['role_id' => $rol->id, 'is_active' => true]);
    }

    // ---------- Calendario y galería ----------

    public function test_guarda_las_vistas_calendario_y_galeria_con_sus_columnas(): void
    {
        $tabla = $this->tabla($this->base());

        $payload = $this->base() + [];
        $payload['vista'] = 'calendario';
        $payload['columna_fecha'] = 'c2';
        $payload['columna_titulo'] = 'c1';
        $this->como()->putJson(route('admin.contenido.tabla.guardar', $tabla), $payload)->assertOk();

        $guardado = json_decode($tabla->fresh()->contenido, true);
        $this->assertSame('calendario', $guardado['vista']);
        $this->assertSame('c2', $guardado['columna_fecha']);
        $this->assertSame('c1', $guardado['columna_titulo']);

        $payload['vista'] = 'galeria';
        $payload['columnas_visibles'] = ['c1', 'c3', 'c3', 'fantasma'];
        $this->como()->putJson(route('admin.contenido.tabla.guardar', $tabla), $payload)->assertOk();

        $guardado = json_decode($tabla->fresh()->contenido, true);
        $this->assertSame('galeria', $guardado['vista']);
        $this->assertSame(['c1', 'c3'], $guardado['columnas_visibles']);
    }

    public function test_el_calendario_exige_una_columna_de_fecha_valida(): void
    {
        $datos = $this->base();
        $datos['vista'] = 'calendario';
        $datos['columna_fecha'] = 'c1'; // es texto, no fecha

        $r = TablaContenido::normalizar($datos);
        $this->assertNull($r['columna_fecha']);
        $this->assertSame('tabla', $r['vista']);

        $datos['columna_fecha'] = 'c2';
        $datos['columna_titulo'] = 'noexiste';
        $r = TablaContenido::normalizar($datos);
        $this->assertSame('calendario', $r['vista']);
        $this->assertNull($r['columna_titulo']);
    }

    public function test_la_pagina_de_la_tabla_ofrece_calendario_y_galeria(): void
    {
        $tabla = $this->tabla($this->base());

        $this->como()->get(route('admin.contenido.show', $tabla))
            ->assertOk()
            ->assertSee('data-cm-vista="calendario"', false)
            ->assertSee('data-cm-vista="galeria"', false)
            ->assertSee('data-cm-panel-calendario', false)
            ->assertSee('data-cm-panel-galeria', false);
    }

    // ---------- Reordenar filas ----------

    public function test_el_orden_de_las_filas_se_conserva_al_guardar(): void
    {
        $tabla = $this->tabla($this->base());

        $payload = $this->base();
        $payload['filas'] = [$payload['filas'][2], $payload['filas'][0], $payload['filas'][1]];
        $this->como()->putJson(route('admin.contenido.tabla.guardar', $tabla), $payload)->assertOk();

        $guardado = json_decode($tabla->fresh()->contenido, true);
        $this->assertSame(['f3', 'f1', 'f2'], array_column($guardado['filas'], 'id'));

        $csv = $this->como()->get(route('admin.contenido.tabla.exportar', $tabla))->streamedContent();
        $this->assertLessThan(strpos($csv, 'Uno'), strpos($csv, 'Tres'));
    }

    public function test_la_pagina_incluye_el_asa_de_reordenar_en_el_js(): void
    {
        $this->assertStringContainsString('cm-asa', (string) file_get_contents(resource_path('js/contenido.js')));
    }

    // ---------- Cambiar el tipo de una columna ----------

    public function test_texto_a_numero_convierte_si_todo_es_numerico(): void
    {
        $datos = $this->base();
        $datos['filas'][0]['celdas']['c1'] = '12';
        $datos['filas'][1]['celdas']['c1'] = '3.5';
        $datos['filas'][2]['celdas']['c1'] = '';
        $tabla = $this->tabla($datos);

        $r = $this->como()->postJson(route('admin.contenido.tabla.convertir-columna', $tabla), $datos + ['columna' => 'c1', 'tipo' => 'numero'])
            ->assertOk();

        $this->assertSame('numero', $r->json('datos.columnas.0.tipo'));
        $this->assertSame('12', $r->json('datos.filas.0.celdas.c1'));
    }

    public function test_texto_a_numero_se_rechaza_si_hay_celdas_no_numericas(): void
    {
        $datos = $this->base();
        $datos['filas'][0]['celdas']['c1'] = '12';
        $datos['filas'][1]['celdas']['c1'] = 'abc';
        $tabla = $this->tabla($datos);

        $r = $this->como()->postJson(route('admin.contenido.tabla.convertir-columna', $tabla), $datos + ['columna' => 'c1', 'tipo' => 'numero'])
            ->assertStatus(422);

        $this->assertStringContainsString('abc', $r->json('message'));
        // No se persistió nada.
        $this->assertSame('texto', json_decode($tabla->fresh()->contenido, true)['columnas'][0]['tipo']);
    }

    public function test_seleccion_a_texto_siempre_es_posible_y_conserva_los_valores(): void
    {
        $datos = $this->base();
        $datos['vista'] = 'kanban';

        $r = TablaContenido::cambiarTipoColumna($datos, 'c3', 'texto');

        $this->assertSame('texto', $r['columnas'][2]['tipo']);
        $this->assertSame([], $r['columnas'][2]['opciones']);
        $this->assertSame('Idea', $r['filas'][0]['celdas']['c3']);
        // La columna de kanban ya no es una selección: se descarta sin romper nada.
        $this->assertNull($r['columna_kanban']);
        $this->assertSame('tabla', $r['vista']);
    }

    public function test_texto_a_seleccion_arma_las_opciones_con_los_valores_distintos(): void
    {
        $datos = $this->base();
        $datos['filas'][0]['celdas']['c1'] = 'A';
        $datos['filas'][1]['celdas']['c1'] = 'B';
        $datos['filas'][2]['celdas']['c1'] = 'A';

        $r = TablaContenido::cambiarTipoColumna($datos, 'c1', 'seleccion');

        $this->assertSame(['A', 'B'], $r['columnas'][0]['opciones']);
    }

    public function test_texto_a_fecha_valida_las_celdas(): void
    {
        $datos = $this->base();
        $datos['filas'][0]['celdas']['c1'] = '2026-12-01';
        $datos['filas'][1]['celdas']['c1'] = '';
        $datos['filas'][2]['celdas']['c1'] = '';
        $this->assertSame('fecha', TablaContenido::cambiarTipoColumna($datos, 'c1', 'fecha')['columnas'][0]['tipo']);

        $datos['filas'][1]['celdas']['c1'] = '01/12/2026';
        $this->expectException(InvalidArgumentException::class);
        TablaContenido::cambiarTipoColumna($datos, 'c1', 'fecha');
    }

    public function test_cambiar_tipo_rechaza_tipos_y_columnas_inexistentes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TablaContenido::cambiarTipoColumna($this->base(), 'zzz', 'texto');
    }

    public function test_cambiar_tipo_rechaza_un_tipo_invalido_por_http(): void
    {
        $tabla = $this->tabla($this->base());

        $this->como()->postJson(route('admin.contenido.tabla.convertir-columna', $tabla), $this->base() + ['columna' => 'c1', 'tipo' => 'magia'])
            ->assertStatus(422);
    }

    public function test_cambiar_tipo_de_la_tabla_de_otro_cliente_es_404(): void
    {
        $otro = Cliente::factory()->create();
        $ajena = $this->tabla($this->base(), $otro);

        $this->como()->postJson(route('admin.contenido.tabla.convertir-columna', $ajena), $this->base() + ['columna' => 'c1', 'tipo' => 'texto'])
            ->assertNotFound();
    }

    // ---------- Llenar desde... ----------

    private function datosReales(): void
    {
        $proyecto = Proyecto::create(['cliente_id' => $this->cliente->id, 'nombre' => 'Sitio', 'tipo' => 'web_nueva', 'fase_actual' => 'planeacion', 'estado' => 'activo', 'fecha_entrega_estimada' => '2026-12-15']);
        Tarea::create(['proyecto_id' => $proyecto->id, 'titulo' => 'Maquetar', 'prioridad' => 'alta', 'estado' => 'en_progreso', 'fecha_limite' => '2026-11-20']);
        // Tarea personal de «Mi espacio»: sin proyecto, no es del cliente.
        Tarea::create(['user_id' => $this->usuario->id, 'cliente_id' => $this->cliente->id, 'titulo' => 'Personal', 'prioridad' => 'media', 'estado' => 'pendiente']);
        AdsCampana::factory()->create(['cliente_id' => $this->cliente->id, 'nombre' => 'Campaña verano', 'plataforma' => 'meta_ads', 'estado' => 'pausada', 'presupuesto_mensual' => 1500]);
        Finanza::create(['cliente_id' => $this->cliente->id, 'concepto' => 'Minuta noviembre', 'tipo' => 'ingreso', 'monto' => 2500.5, 'estado' => 'pendiente', 'fecha_vencimiento' => '2026-11-25', 'mes' => 11, 'anio' => 2026]);
        Keyword::create(['cliente_id' => $this->cliente->id, 'keyword' => 'agencia seo', 'tipo' => 'principal', 'estado' => 'en_uso', 'posicion_actual' => 7]);

        // Datos de OTRO cliente: jamás deben aparecer.
        $otro = Cliente::factory()->create();
        AdsCampana::factory()->create(['cliente_id' => $otro->id, 'nombre' => 'Campaña ajena']);
        Finanza::create(['cliente_id' => $otro->id, 'concepto' => 'Cobro ajeno', 'tipo' => 'ingreso', 'monto' => 1, 'estado' => 'pagado', 'mes' => 1, 'anio' => 2026]);
        Keyword::create(['cliente_id' => $otro->id, 'keyword' => 'palabra ajena', 'tipo' => 'principal', 'estado' => 'en_uso']);
        $proyectoAjeno = Proyecto::create(['cliente_id' => $otro->id, 'nombre' => 'Proyecto ajeno', 'tipo' => 'web_nueva', 'fase_actual' => 'planeacion', 'estado' => 'activo']);
        Tarea::create(['proyecto_id' => $proyectoAjeno->id, 'titulo' => 'Tarea ajena', 'prioridad' => 'baja', 'estado' => 'pendiente']);
    }

    public function test_llena_la_tabla_desde_cada_fuente_con_columnas_tipadas(): void
    {
        $this->datosReales();

        $esperado = [
            'ads_campanas' => [['Campaña', 'Plataforma', 'Estado', 'Presupuesto mensual'], ['texto', 'seleccion', 'seleccion', 'numero'], ['Campaña verano', 'Meta Ads', 'Pausada', '1500.00']],
            'desarrollo_tareas' => [['Título', 'Proyecto', 'Estado', 'Fecha límite'], ['texto', 'texto', 'seleccion', 'fecha'], ['Maquetar', 'Sitio', 'En progreso', '2026-11-20']],
            'desarrollo_proyectos' => [['Título', 'Estado', 'Fecha límite'], ['texto', 'seleccion', 'fecha'], ['Sitio', 'Activo', '2026-12-15']],
            'finanzas' => [['Concepto', 'Monto', 'Estado', 'Vencimiento'], ['texto', 'numero', 'seleccion', 'fecha'], ['Minuta noviembre', '2500.50', 'Pendiente', '2026-11-25']],
            'keywords' => [['Palabra', 'Posición actual'], ['texto', 'numero'], ['agencia seo', '7']],
        ];

        foreach ($esperado as $fuente => [$nombres, $tipos, $primera]) {
            $tabla = $this->tabla(TablaContenido::vacia());

            $r = $this->como()->postJson(route('admin.contenido.tabla.llenar', $tabla), ['fuente' => $fuente, 'modo' => 'reemplazar'])
                ->assertOk();

            $datos = json_decode($tabla->fresh()->contenido, true);
            $this->assertSame($nombres, array_column($datos['columnas'], 'nombre'), $fuente);
            $this->assertSame($tipos, array_column($datos['columnas'], 'tipo'), $fuente);
            $this->assertCount(1, $datos['filas'], "$fuente: solo filas del cliente de la tabla");
            $this->assertSame($primera, array_values($datos['filas'][0]['celdas']), $fuente);
            $this->assertSame($fuente, $datos['origen']['clave']);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $datos['origen']['fecha']);
            $this->assertSame(1, $r->json('importadas'));
        }
    }

    public function test_llenar_reemplazando_borra_lo_anterior_y_agregando_lo_conserva(): void
    {
        $this->datosReales();
        $tabla = $this->tabla($this->base());

        // Agregar: lo propio se conserva y las columnas de la fuente que no existían se crean.
        $this->como()->postJson(route('admin.contenido.tabla.llenar', $tabla), ['fuente' => 'keywords', 'modo' => 'agregar'])->assertOk();
        $datos = json_decode($tabla->fresh()->contenido, true);
        $this->assertSame(['Título', 'Fecha', 'Estado', 'Palabra', 'Posición actual'], array_column($datos['columnas'], 'nombre'));
        $this->assertCount(4, $datos['filas']);
        $this->assertSame('Uno', $datos['filas'][0]['celdas']['c1']);

        // Reemplazar: queda solo la fuente.
        $this->como()->postJson(route('admin.contenido.tabla.llenar', $tabla), ['fuente' => 'keywords', 'modo' => 'reemplazar'])->assertOk();
        $datos = json_decode($tabla->fresh()->contenido, true);
        $this->assertSame(['Palabra', 'Posición actual'], array_column($datos['columnas'], 'nombre'));
        $this->assertCount(1, $datos['filas']);
    }

    public function test_agregar_empareja_columnas_por_nombre_y_suma_filas(): void
    {
        $this->datosReales();
        $tabla = $this->tabla([
            'columnas' => [
                ['id' => 'c1', 'nombre' => 'palabra', 'tipo' => 'texto', 'opciones' => []],
                ['id' => 'c2', 'nombre' => 'Posición actual', 'tipo' => 'numero', 'opciones' => []],
            ],
            'filas' => [['id' => 'f1', 'celdas' => ['c1' => 'previa', 'c2' => '3']]],
            'vista' => 'tabla',
            'columna_kanban' => null,
        ]);

        $this->como()->postJson(route('admin.contenido.tabla.llenar', $tabla), ['fuente' => 'keywords', 'modo' => 'agregar'])->assertOk();

        $datos = json_decode($tabla->fresh()->contenido, true);
        $this->assertCount(2, $datos['columnas']);
        $this->assertSame(['previa', 'agencia seo'], array_column(array_column($datos['filas'], 'celdas'), 'c1'));
        $this->assertSame('7', $datos['filas'][1]['celdas']['c2']);
    }

    public function test_llenar_respeta_el_tope_de_filas_e_informa_las_omitidas(): void
    {
        $filas = [];
        for ($i = 1; $i <= TablaContenido::MAX_FILAS + 5; $i++) {
            $filas[] = ['cliente_id' => $this->cliente->id, 'keyword' => "kw $i", 'tipo' => 'secundaria', 'estado' => 'seguimiento', 'created_at' => now(), 'updated_at' => now()];
        }
        foreach (array_chunk($filas, 500) as $lote) {
            Keyword::insert($lote);
        }
        $tabla = $this->tabla(TablaContenido::vacia());

        $r = $this->como()->postJson(route('admin.contenido.tabla.llenar', $tabla), ['fuente' => 'keywords', 'modo' => 'reemplazar'])->assertOk();

        $this->assertSame(TablaContenido::MAX_FILAS, $r->json('importadas'));
        $this->assertSame(5, $r->json('omitidas'));
        $this->assertCount(TablaContenido::MAX_FILAS, json_decode($tabla->fresh()->contenido, true)['filas']);
    }

    public function test_llenar_rechaza_fuentes_y_modos_invalidos(): void
    {
        $tabla = $this->tabla(TablaContenido::vacia());

        $this->como()->postJson(route('admin.contenido.tabla.llenar', $tabla), ['fuente' => 'usuarios', 'modo' => 'reemplazar'])->assertStatus(422);
        $this->como()->postJson(route('admin.contenido.tabla.llenar', $tabla), ['fuente' => 'keywords', 'modo' => 'borrar'])->assertStatus(422);
    }

    public function test_llenar_una_tabla_de_otro_cliente_es_404(): void
    {
        $otro = Cliente::factory()->create();
        $ajena = $this->tabla(TablaContenido::vacia(), $otro);

        $this->como()->postJson(route('admin.contenido.tabla.llenar', $ajena), ['fuente' => 'keywords', 'modo' => 'reemplazar'])->assertNotFound();
    }

    public function test_la_pagina_muestra_el_menu_y_la_leyenda_de_la_foto(): void
    {
        $tabla = $this->tabla(TablaContenido::vacia());

        $this->como()->get(route('admin.contenido.show', $tabla))
            ->assertOk()
            ->assertSee('Llenar desde...')
            ->assertSee('No se sincroniza');

        $conOrigen = TablaContenido::vacia();
        $conOrigen['origen'] = ['clave' => 'keywords', 'fecha' => '2026-11-01 14:30'];
        $otra = $this->tabla($conOrigen);
        $this->assertSame(['clave' => 'keywords', 'fecha' => '2026-11-01 14:30'], TablaContenido::decodificar($otra->fresh()->contenido)['origen']);
    }

    public function test_el_origen_con_forma_invalida_se_descarta(): void
    {
        $datos = TablaContenido::vacia();
        $datos['origen'] = ['clave' => '<script>', 'fecha' => 'ayer'];

        $this->assertNull(TablaContenido::normalizar($datos)['origen']);
    }

    public function test_los_valores_de_una_fuente_con_formula_se_exportan_protegidos(): void
    {
        Keyword::create(['cliente_id' => $this->cliente->id, 'keyword' => '=HYPERLINK("http://x")', 'tipo' => 'principal', 'estado' => 'en_uso', 'posicion_actual' => 2]);
        $tabla = $this->tabla(TablaContenido::vacia());

        $this->como()->postJson(route('admin.contenido.tabla.llenar', $tabla), ['fuente' => 'keywords', 'modo' => 'reemplazar'])->assertOk();

        $csv = $this->como()->get(route('admin.contenido.tabla.exportar', $tabla))->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    // ---------- Permisos por fuente ----------

    public function test_cada_fuente_solo_se_ofrece_con_el_permiso_de_su_modulo(): void
    {
        $solo = $this->usuarioConPermisos(['contenido', 'keywords']);
        $this->assertSame(['keywords'], array_column(FuentesTabla::disponibles($solo), 'clave'));

        $varios = $this->usuarioConPermisos(['contenido', 'ads', 'desarrollo', 'finanzas']);
        $this->assertEqualsCanonicalizing(
            ['ads_campanas', 'desarrollo_tareas', 'desarrollo_proyectos', 'finanzas'],
            array_column(FuentesTabla::disponibles($varios), 'clave')
        );

        $ninguno = $this->usuarioConPermisos(['contenido']);
        $this->assertSame([], FuentesTabla::disponibles($ninguno));
    }

    public function test_sin_el_permiso_de_la_fuente_llenar_responde_403_y_no_toca_la_tabla(): void
    {
        $this->datosReales();
        $usuario = $this->usuarioConPermisos(['contenido', 'keywords']);
        $tabla = $this->tabla(TablaContenido::vacia());

        foreach (['ads_campanas', 'desarrollo_tareas', 'desarrollo_proyectos', 'finanzas'] as $fuente) {
            $this->como($usuario)->postJson(route('admin.contenido.tabla.llenar', $tabla), ['fuente' => $fuente, 'modo' => 'reemplazar'])
                ->assertForbidden();
        }
        $this->assertSame(TablaContenido::vacia(), json_decode($tabla->fresh()->contenido, true));

        // La fuente de un módulo que sí tiene funciona.
        $this->como($usuario)->postJson(route('admin.contenido.tabla.llenar', $tabla), ['fuente' => 'keywords', 'modo' => 'reemplazar'])->assertOk();
    }

    public function test_la_pagina_oculta_el_menu_y_las_fuentes_sin_permiso(): void
    {
        $tabla = $this->tabla(TablaContenido::vacia());

        $sinFuentes = $this->usuarioConPermisos(['contenido']);
        $this->como($sinFuentes)->get(route('admin.contenido.show', $tabla))
            ->assertOk()
            ->assertDontSee('data-cm-llenar-abrir', false);

        $conAds = $this->usuarioConPermisos(['contenido', 'ads']);
        $respuesta = $this->como($conAds)->get(route('admin.contenido.show', $tabla))->assertOk();
        $respuesta->assertSee('data-cm-llenar-abrir', false);
        $html = $respuesta->getContent();
        $this->assertStringContainsString('ads_campanas', $html);
        $this->assertStringNotContainsString('&quot;clave&quot;:&quot;finanzas&quot;', $html);
        $this->assertStringNotContainsString('&quot;clave&quot;:&quot;keywords&quot;', $html);
    }
}
