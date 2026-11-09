<?php

namespace Tests\Feature\Portal;

use App\Models\Permission;
use App\Models\PortalAcceso;
use App\Models\PortalVisita;
use App\Models\Reporte;
use App\Models\ReporteEnlace;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** Administración desde el Resumen de cliente (permiso `clientes`) y no regresión de lo ya publicado. */
class PortalAdminTest extends TestCase
{
    use PreparaPortal, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararPortal();
        RateLimiter::clear('portal|127.0.0.1');
    }

    protected function tearDown(): void
    {
        $this->cerrarPortal();
        parent::tearDown();
    }

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

    public function test_crear_devuelve_el_token_en_claro_una_sola_vez_y_guarda_solo_el_hash(): void
    {
        $cliente = $this->cliente();
        $usuario = User::factory()->create();

        $r = $this->actingAs($usuario)->postJson(route('admin.clientes.portal.store', $cliente), [
            'etiqueta' => 'Gerencia', 'correo' => 'gerente@hotel.test', 'vigencia_dias' => 90,
        ])->assertCreated();

        $url = $r->json('url');
        $this->assertMatchesRegularExpression('~/portal/[A-Za-z0-9]{48}$~', $url);
        $token = substr($url, strrpos($url, '/') + 1);

        $acceso = PortalAcceso::firstOrFail();
        $this->assertSame(hash('sha256', $token), $acceso->token_hash);
        $this->assertSame($cliente->id, $acceso->cliente_id);
        $this->assertSame($usuario->id, $acceso->creado_por);
        $this->assertSame('Gerencia', $acceso->etiqueta);
        $this->assertSame(now()->addDays(90)->toDateString(), $acceso->expira_en->toDateString());
        $this->assertStringNotContainsString($token, json_encode($r->json('acceso')));
        $this->assertStringNotContainsString($acceso->token_hash, $r->getContent());

        // El enlace creado funciona.
        $this->get($url)->assertOk();
    }

    public function test_la_vigencia_por_defecto_es_30_y_solo_se_admiten_7_30_y_90(): void
    {
        $cliente = $this->cliente();
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->postJson(route('admin.clientes.portal.store', $cliente))->assertCreated();
        $this->assertSame(now()->addDays(30)->toDateString(), PortalAcceso::firstOrFail()->expira_en->toDateString());

        foreach ([15, 0, 365, 'abc'] as $mala) {
            $this->actingAs($usuario)->postJson(route('admin.clientes.portal.store', $cliente), ['vigencia_dias' => $mala])->assertUnprocessable();
        }
        $this->actingAs($usuario)->postJson(route('admin.clientes.portal.store', $cliente), ['correo' => 'no-es-correo'])->assertUnprocessable();
        $this->assertSame(1, PortalAcceso::count());
    }

    public function test_revocar_corta_el_acceso_y_no_alcanza_a_los_de_otro_cliente(): void
    {
        $a = $this->cliente('Hotel Alfa');
        $b = $this->cliente('Hotel Beta');
        [$accesoA, $tokenA] = $this->enlace($a);
        [$accesoB, $tokenB] = $this->enlace($b);
        $usuario = User::factory()->create();

        // Con la URL del cliente A no se revoca el enlace de B.
        $this->actingAs($usuario)->deleteJson(route('admin.clientes.portal.destroy', ['cliente' => $a, 'acceso' => $accesoB]))->assertNotFound();
        $this->assertNull($accesoB->fresh()->revocado_en);

        $this->get($this->ruta('portal.inicio', $tokenA))->assertOk();
        $this->actingAs($usuario)->deleteJson(route('admin.clientes.portal.destroy', ['cliente' => $a, 'acceso' => $accesoA]))->assertOk()->assertJsonPath('estado', 'revocado');
        $this->get($this->ruta('portal.inicio', $tokenA))->assertStatus(410);
        $this->get($this->ruta('portal.inicio', $tokenB))->assertOk();
    }

    public function test_sin_el_permiso_clientes_es_403_y_con_el_permiso_pasa(): void
    {
        $cliente = $this->cliente();
        [$acceso] = $this->enlace($cliente);

        $sinPermiso = $this->usuarioConRol('operador', ['dashboard']);
        $this->actingAs($sinPermiso)->postJson(route('admin.clientes.portal.store', $cliente))->assertForbidden();
        $this->actingAs($sinPermiso)->deleteJson(route('admin.clientes.portal.destroy', ['cliente' => $cliente, 'acceso' => $acceso]))->assertForbidden();
        $this->assertNull($acceso->fresh()->revocado_en);
        $this->assertSame(1, PortalAcceso::count());

        $conPermiso = $this->usuarioConRol('gestor', ['clientes']);
        $this->actingAs($conPermiso)->postJson(route('admin.clientes.portal.store', $cliente))->assertCreated();
        $this->actingAs($conPermiso)->deleteJson(route('admin.clientes.portal.destroy', ['cliente' => $cliente, 'acceso' => $acceso]))->assertOk();
    }

    public function test_sin_sesion_no_se_puede_crear_ni_revocar(): void
    {
        $cliente = $this->cliente();
        [$acceso] = $this->enlace($cliente);

        $this->postJson(route('admin.clientes.portal.store', $cliente))->assertUnauthorized();
        $this->deleteJson(route('admin.clientes.portal.destroy', ['cliente' => $cliente, 'acceso' => $acceso]))->assertUnauthorized();
    }

    public function test_el_resumen_de_cliente_lista_los_enlaces_sin_mostrar_nunca_el_token(): void
    {
        $cliente = $this->cliente('Hotel Fratelli');
        $otro = $this->cliente('Hotel Ajeno');
        [$vigente, $tokenVigente] = $this->enlace($cliente);
        [$vencido] = $this->enlace($cliente);
        [$revocado] = $this->enlace($cliente);
        [, $tokenAjeno] = $this->enlace($otro);
        $vigente->forceFill(['etiqueta' => 'Gerencia', 'correo' => 'gerente@hotel.test'])->save();
        $vencido->forceFill(['expira_en' => now()->subDay()])->save();
        $revocado->forceFill(['revocado_en' => now()])->save();
        $this->get($this->ruta('portal.reportes', $tokenVigente))->assertOk();
        $this->get($this->ruta('portal.inicio', $tokenAjeno))->assertOk();

        $html = $this->actingAs(User::factory()->create())->get(route('admin.clientes.show', $cliente))->assertOk()->getContent();

        $this->assertStringContainsString('Portal del cliente', $html);
        $this->assertStringContainsString('data-portal-adm', $html);
        $this->assertStringContainsString('Gerencia', $html);
        $this->assertStringContainsString('gerente@hotel.test', $html);
        foreach (['vigente', 'vencido', 'revocado'] as $estado) {
            $this->assertStringContainsString('portal-adm__estado--'.$estado, $html);
        }
        $this->assertSame(1, substr_count($html, 'data-portal-revocar'), 'solo el vigente se puede revocar');
        $this->assertStringContainsString('Últimas visitas (1)', $html, 'las visitas del otro cliente no cuentan');
        $this->assertStringContainsString('/reportes', $html);
        foreach ([$tokenVigente, $tokenAjeno, $vigente->token_hash, $vencido->token_hash, hash('sha256', $tokenAjeno)] as $secreto) {
            $this->assertStringNotContainsString($secreto, $html);
        }
        $this->assertStringContainsString(route('admin.clientes.portal.store', $cliente), $html);
    }

    public function test_el_resumen_sin_enlaces_muestra_el_estado_vacio(): void
    {
        $cliente = $this->cliente();

        $html = $this->actingAs(User::factory()->create())->get(route('admin.clientes.show', $cliente))->assertOk()->getContent();

        $this->assertStringContainsString('Todavía no hay enlaces para este cliente.', $html);
        $this->assertStringContainsString('Nadie ha abierto el portal todavía.', $html);
    }

    // --- no regresión -----------------------------------------------------------

    public function test_el_enlace_publico_de_reportes_sigue_funcionando(): void
    {
        $cliente = $this->cliente();
        $reporte = $this->reporte($cliente, 'listo');
        $nuevo = ReporteEnlace::crearPara(Reporte::findOrFail($reporte->id), null);

        $this->get($nuevo['url'])->assertOk()->assertHeader('X-Robots-Tag');
        $this->get(route('reporte-publico.show', ['token' => str_repeat('a', 48)]))->assertNotFound();
        // El portal y los reportes compartidos no se cruzan: un token de reporte no abre el portal ni al revés.
        $this->get($this->ruta('portal.inicio', $nuevo['token']))->assertNotFound();
        [, $tokenPortal] = $this->enlace($cliente);
        $this->get(route('reporte-publico.show', ['token' => $tokenPortal]))->assertNotFound();
    }

    public function test_el_sitio_publico_no_se_ve_afectado(): void
    {
        $this->get('/')->assertOk();
        $this->get('/portal')->assertNotFound();
        $this->assertSame(0, PortalVisita::count());
    }
}
