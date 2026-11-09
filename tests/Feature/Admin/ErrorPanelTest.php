<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\Proyecto;
use App\Models\User;
use App\Support\ClienteActivo;
use App\Support\ErrorPanel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Los 404/403 dentro de /admin usan la página de error del panel (no la del sitio público)
 * y explican el motivo real: eliminado, de otro cliente o simplemente inexistente.
 */
class ErrorPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function proyecto(Cliente $cliente): Proyecto
    {
        return Proyecto::create(['cliente_id' => $cliente->id, 'nombre' => 'Sitio QA', 'tipo' => 'web_nueva', 'fase_actual' => 'planeacion', 'estado' => 'activo']);
    }

    public function test_un_proyecto_inexistente_muestra_el_404_del_panel_y_no_el_publico(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/desarrollo/999999')
            ->assertNotFound()
            ->assertSee('data-error-panel', false)
            ->assertSee('app-shell', false)
            ->assertDontSee('page-hero', false)
            ->assertSee('No encontramos lo que buscas');
    }

    public function test_un_proyecto_eliminado_dice_que_fue_eliminado(): void
    {
        $proyecto = $this->proyecto(Cliente::factory()->create());
        $proyecto->delete();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.desarrollo.show', $proyecto->id))
            ->assertNotFound()
            ->assertSee('fue eliminado y ya no está disponible');
    }

    public function test_si_el_recurso_es_de_otro_cliente_lo_dice_y_ofrece_ver_todos(): void
    {
        $mio = Cliente::factory()->create();
        $ajeno = $this->proyecto(Cliente::factory()->create());

        // Ruta de prueba: el 404 con el aviso explícito, como lo declaran los módulos acotados por cliente.
        Route::middleware(['web', 'auth'])->get('/admin/_qa/otro-cliente', fn () => abort(404, ErrorPanel::MENSAJE_OTRO_CLIENTE));

        $this->actingAs(User::factory()->create())
            ->withSession([ClienteActivo::CLAVE => $mio->id])
            ->get('/admin/_qa/otro-cliente')
            ->assertNotFound()
            ->assertSee('data-error-otro-cliente', false)
            ->assertSee('Este recurso pertenece a otro cliente. Cambia el cliente activo para verlo.')
            ->assertSee('Ver todos los clientes');

        $this->assertNotNull($ajeno->id);
    }

    public function test_el_binding_de_un_registro_de_otro_cliente_detecta_el_motivo(): void
    {
        $mio = Cliente::factory()->create();
        $ajeno = $this->proyecto(Cliente::factory()->create());

        $contexto = ErrorPanel::contexto(
            new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('', (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel(Proyecto::class, [$ajeno->id])),
            404
        );

        // Sin cliente activo no hay "otro cliente"; con uno distinto, sí.
        $this->assertFalse($contexto['otro_cliente']);

        $this->actingAs(User::factory()->create())->withSession([ClienteActivo::CLAVE => $mio->id]);
        request()->setLaravelSession(app('session.store'));
        session([ClienteActivo::CLAVE => $mio->id]);
        $contexto = ErrorPanel::contexto(
            new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('', (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel(Proyecto::class, [$ajeno->id])),
            404
        );
        $this->assertTrue($contexto['otro_cliente']);
    }

    public function test_un_404_sin_motivo_conocido_con_cliente_activo_lo_sugiere_sin_afirmarlo(): void
    {
        $cliente = Cliente::factory()->create(['empresa' => 'Hotel QA']);

        $this->actingAs(User::factory()->create())
            ->withSession([ClienteActivo::CLAVE => $cliente->id])
            ->get('/admin/desarrollo/999999')
            ->assertNotFound()
            ->assertSee('Si pertenece a otro cliente')
            ->assertDontSee('data-error-otro-cliente', false);
    }

    public function test_un_403_dentro_del_panel_usa_la_vista_del_panel(): void
    {
        Route::middleware(['web', 'auth'])->get('/admin/_qa/prohibido', fn () => abort(403, 'Solo un administrador puede hacer esto.'));

        $this->actingAs(User::factory()->create())
            ->get('/admin/_qa/prohibido')
            ->assertForbidden()
            ->assertSee('data-error-panel', false)
            ->assertSee('Solo un administrador puede hacer esto.');
    }

    public function test_las_peticiones_json_y_el_sitio_publico_no_cambian(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/admin/desarrollo/999999')
            ->assertNotFound()
            ->assertDontSee('data-error-panel', false);

        $this->get('/pagina-que-no-existe-qa')->assertNotFound()->assertDontSee('data-error-panel', false);
    }
}
