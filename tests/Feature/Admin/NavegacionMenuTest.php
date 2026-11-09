<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\CommandPaletteIndex;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Menú por flujo (Hoy, Clientes, Cliente activo, Reportes, Agencia, Más): estructura,
 * permisos, Resumen del cliente activo, paleta Ctrl+K y estado abierto/cerrado del HTML.
 */
class NavegacionMenuTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Rutas que el menú anterior ofrecía: el reagrupado no puede haber cambiado ningún nombre.
     */
    private const RUTAS_PREVIAS = [
        'admin.inicio', 'admin.dashboard', 'admin.mi-espacio.index', 'admin.calendario.index', 'admin.clientes.index',
        'admin.servicios.index', 'admin.agenda.index', 'admin.propuestas.index', 'admin.reportes.index',
        'admin.conversiones.index', 'admin.automatizaciones.index', 'admin.blog.index', 'admin.captura.index',
        'admin.identidad.index', 'admin.audiencia.index', 'admin.seo-plan.index', 'admin.contenido.index',
        'admin.metricas.index', 'admin.seo.index', 'admin.keywords.index', 'admin.ads.index', 'admin.desarrollo.index',
        'admin.finanzas.index', 'admin.archivos.index', 'admin.correo.plantillas.index', 'admin.correo.envios.index',
        'admin.usuarios.index', 'admin.roles.index', 'admin.integraciones.index', 'admin.configuracion.smtp.edit',
        'admin.configuracion.medicion.edit', 'admin.clientes.show', 'admin.reportes.tablero.index',
        'admin.reportes.programados.index', 'admin.cliente-activo',
    ];

    private function usuarioConRol(string $nombreRol, array $modulos = []): User
    {
        config(['permisos.aplicar' => true]);
        $rol = Role::query()->create(['name' => $nombreRol, 'label' => ucfirst($nombreRol)]);

        foreach ($modulos as $modulo) {
            $permiso = Permission::query()->firstOrCreate(['name' => $modulo], ['label' => $modulo, 'module' => $modulo]);
            $rol->permissions()->attach($permiso->id);
        }

        return User::factory()->create(['role_id' => $rol->id, 'is_active' => true]);
    }

    private function etiquetas(array $grupos): array
    {
        return array_map(fn (array $enlaces): array => array_column($enlaces, 'label'), $grupos);
    }

    /**
     * Abre la etiqueta del grupo en el HTML del menú y devuelve sus atributos.
     */
    private function atributosDelGrupo(string $html, string $grupo): string
    {
        $this->assertSame(1, preg_match('/<div class="sidebar__group([^"]*)"\s+data-grupo="' . preg_quote($grupo, '/') . '"([^>]*)>/u', $html, $m), "No se pintó el grupo {$grupo}");

        return $m[1] . ' ' . $m[2];
    }

    public function test_los_seis_grupos_con_sus_items_exactos(): void
    {
        $this->actingAs($this->usuarioConRol('admin'));

        $this->assertSame([
            'Hoy' => ['Inicio', 'Calendario', 'Pendientes de captura'],
            'Clientes' => ['Lista', 'Servicios y contratos', 'Propuestas', 'Conversiones'],
            'Cliente activo' => ['Resumen', 'Identidad', 'Audiencia', 'SEO Plan', 'Contenido', 'SEO', 'Keywords', 'Ads', 'Métricas', 'Desarrollo', 'Reportes del cliente'],
            'Reportes' => ['Todos', 'Tablero', 'Programados'],
            'Agencia' => ['Dashboard', 'Finanzas', 'Agenda pública', 'Archivos', 'Plantillas de correo', 'Enviar correo', 'Usuarios', 'Roles', 'Integraciones', 'Configuración de correo', 'Medición y Google'],
            'Más' => ['Mi espacio', 'Automatizaciones', 'Blog'],
        ], $this->etiquetas(Navigation::groups()));
    }

    public function test_los_subgrupos_del_cliente_activo(): void
    {
        $secciones = collect(Navigation::todos()[Navigation::GRUPO_CLIENTE_ACTIVO])->groupBy(fn (array $e) => $e['seccion'] ?? '')->map(fn ($g) => $g->pluck('label')->all())->all();

        $this->assertSame(['Identidad', 'Audiencia'], $secciones['Marca']);
        $this->assertSame(['SEO Plan', 'Contenido'], $secciones['Estrategia']);
        $this->assertSame(['SEO', 'Keywords', 'Ads', 'Métricas'], $secciones['Seguimiento']);
        $this->assertSame(['Resumen', 'Desarrollo', 'Reportes del cliente'], $secciones['']);
    }

    public function test_ningun_nombre_de_ruta_cambio_y_todos_los_items_existen(): void
    {
        foreach (self::RUTAS_PREVIAS as $ruta) {
            $this->assertTrue(Route::has($ruta), "La ruta {$ruta} ya no existe");
        }

        foreach (Navigation::todos() as $grupo => $enlaces) {
            foreach ($enlaces as $enlace) {
                $this->assertTrue(Route::has($enlace['route']), "{$grupo} / {$enlace['label']}: la ruta {$enlace['route']} no existe");
            }
        }
    }

    public function test_cada_item_del_menu_resuelve_sin_404_ni_500(): void
    {
        $this->actingAs($this->usuarioConRol('admin'));

        $urls = collect(Navigation::groups())->flatten(1)->map(fn (array $e) => Navigation::url($e))->unique();
        $this->assertGreaterThan(25, $urls->count());

        foreach ($urls as $url) {
            $estado = $this->get($url)->getStatusCode();
            $this->assertLessThan(400, $estado, "{$url} respondió {$estado}");
        }
    }

    public function test_un_usuario_sin_permiso_no_ve_el_item_ni_el_grupo_vacio(): void
    {
        $this->actingAs($this->usuarioConRol('operador', ['dashboard', 'clientes']));

        $grupos = $this->etiquetas(Navigation::groups());

        // Con «clientes» ve la Lista y el Resumen; sin los demás permisos no queda nada más en esos grupos.
        $this->assertSame(['Clientes' => ['Lista'], 'Cliente activo' => ['Resumen'], 'Agencia' => ['Dashboard']], $grupos);
        $this->assertArrayNotHasKey('Hoy', $grupos);
        $this->assertArrayNotHasKey('Reportes', $grupos);
        $this->assertArrayNotHasKey('Más', $grupos);
    }

    public function test_sin_ningun_permiso_de_cliente_activo_no_hay_grupo_ni_selector(): void
    {
        $usuario = $this->usuarioConRol('solo-inicio', ['inicio']);

        $html = $this->actingAs($usuario)->get(route('admin.inicio'))->assertOk()->getContent();

        $this->assertArrayNotHasKey(Navigation::GRUPO_CLIENTE_ACTIVO, Navigation::groups());
        $this->assertStringNotContainsString('data-grupo="Cliente activo"', $html);
        $this->assertStringNotContainsString('sidebar__cliente-select', $html);
        $this->assertStringNotContainsString('topbar__cliente-select', $html);
    }

    public function test_el_selector_de_cliente_vive_dentro_del_grupo_cliente_activo(): void
    {
        $html = $this->actingAs($this->usuarioConRol('admin'))->get(route('admin.inicio'))->assertOk()->getContent();

        $inicio = strpos($html, 'data-grupo="Cliente activo"');
        $fin = strpos($html, 'data-grupo="Reportes"');
        $selector = strpos($html, 'sidebar__cliente-select');

        $this->assertNotFalse($inicio);
        $this->assertGreaterThan($inicio, $selector);
        $this->assertLessThan($fin, $selector);
        $this->assertStringContainsString('Elige un cliente arriba', $html);
    }

    public function test_resumen_sin_cliente_activo_lleva_a_la_lista_y_lo_avisa(): void
    {
        $this->actingAs($this->usuarioConRol('admin'));
        Cliente::factory()->create();

        $resumen = collect(Navigation::groups()[Navigation::GRUPO_CLIENTE_ACTIVO])->firstWhere('label', 'Resumen');
        $this->assertSame(route('admin.clientes.index'), Navigation::url($resumen));
        $this->assertTrue(Navigation::pideCliente($resumen));

        $html = $this->get(route('admin.inicio'))->getContent();
        $this->assertStringContainsString('Elige un cliente', $html);
        $this->assertStringContainsString('is-sin-cliente', $html);
    }

    public function test_resumen_con_cliente_activo_apunta_a_su_pantalla(): void
    {
        $cliente = Cliente::factory()->create();
        $this->actingAs($this->usuarioConRol('admin'));

        $html = $this->withSession(['cliente_activo_id' => $cliente->id])->get(route('admin.inicio'))->assertOk()->getContent();

        $this->assertStringContainsString('href="' . route('admin.clientes.show', $cliente->id) . '"', $html);
        $this->assertStringNotContainsString('Elige un cliente arriba', $html);
        $this->assertStringNotContainsString('is-sin-cliente', $html);
    }

    public function test_en_el_resumen_solo_se_resalta_el_resumen_y_en_reportes_solo_todos(): void
    {
        $cliente = Cliente::factory()->create();
        $this->actingAs($this->usuarioConRol('admin'));

        $html = $this->withSession(['cliente_activo_id' => $cliente->id])->get(route('admin.clientes.show', $cliente->id))->assertOk()->getContent();
        $this->assertSame(1, preg_match_all('/sidebar__link\s+is-active/', $html));
        $this->assertMatchesRegularExpression('/is-active[^>]*href="' . preg_quote(route('admin.clientes.show', $cliente->id), '/') . '"/s', $html);

        $html = $this->get(route('admin.reportes.index'))->assertOk()->getContent();
        $this->assertSame(1, preg_match_all('/sidebar__link\s+is-active/', $html));
    }

    public function test_la_paleta_lista_cada_destino_visible_una_sola_vez(): void
    {
        $this->actingAs($this->usuarioConRol('admin'));

        $urls = array_column(CommandPaletteIndex::build()['modulos'], 'url');
        $esperadas = collect(Navigation::groups())->flatten(1)->map(fn (array $e) => Navigation::url($e))->unique()->values()->all();

        $this->assertSame($urls, array_values(array_unique($urls)), 'La paleta repite destinos');
        $this->assertEqualsCanonicalizing($esperadas, $urls);
        // Sin cliente activo, «Resumen» no se ofrece aparte: sería la misma URL que la lista de clientes.
        $this->assertNotContains('Resumen del cliente activo', array_column(CommandPaletteIndex::build()['modulos'], 'label'));
    }

    public function test_la_paleta_incluye_el_resumen_con_cliente_activo(): void
    {
        $cliente = Cliente::factory()->create();
        $this->actingAs($this->usuarioConRol('admin'));
        $this->withSession(['cliente_activo_id' => $cliente->id]);
        // La paleta lee la sesión de la petición actual.
        session(['cliente_activo_id' => $cliente->id]);

        $modulos = collect(CommandPaletteIndex::build()['modulos']);

        $this->assertSame(route('admin.clientes.show', $cliente->id), $modulos->firstWhere('label', 'Resumen del cliente activo')['url'] ?? null);
        $this->assertSame($modulos->pluck('url')->unique()->count(), $modulos->count());
    }

    public function test_mas_nace_cerrado_y_el_resto_abierto(): void
    {
        $html = $this->actingAs($this->usuarioConRol('admin'))->get(route('admin.inicio'))->assertOk()->getContent();

        $mas = $this->atributosDelGrupo($html, 'Más');
        $this->assertStringContainsString('is-cerrado', $mas);
        $this->assertStringContainsString('data-cerrado-por-defecto', $mas);

        foreach (['Hoy', 'Clientes', 'Cliente activo', 'Reportes', 'Agencia'] as $grupo) {
            $this->assertStringNotContainsString('is-cerrado', $this->atributosDelGrupo($html, $grupo), $grupo);
        }
        $this->assertStringContainsString('data-tiene-activo', $this->atributosDelGrupo($html, 'Hoy'));
        $this->assertStringNotContainsString('data-tiene-activo', $this->atributosDelGrupo($html, 'Agencia'));
    }

    public function test_el_grupo_de_la_pantalla_activa_se_abre_solo_incluso_en_mas(): void
    {
        $this->actingAs($this->usuarioConRol('admin'));

        $html = $this->get(route('admin.mi-espacio.index'))->assertOk()->getContent();

        $mas = $this->atributosDelGrupo($html, 'Más');
        $this->assertStringContainsString('data-tiene-activo', $mas);
        $this->assertStringNotContainsString('is-cerrado', $mas);
    }

    public function test_el_script_descarta_la_clave_antigua_y_usa_el_estado_nuevo(): void
    {
        $html = $this->actingAs($this->usuarioConRol('admin'))->get(route('admin.inicio'))->getContent();

        $this->assertStringContainsString('localStorage.removeItem("agencyos-sidebar-groups")', $html);
        $this->assertStringContainsString('agencyos-sidebar-estado', $html);
    }
}
