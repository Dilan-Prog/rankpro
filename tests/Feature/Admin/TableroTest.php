<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\Keyword;
use App\Models\KeywordMedicion;
use App\Models\User;
use App\Support\ClienteActivo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pantalla «Tablero por cliente»: ruta, selección de cliente y estados vacíos. */
class TableroTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_ruta_no_choca_con_el_show_de_reportes(): void
    {
        $this->assertSame('/admin/reportes/tablero', route('admin.reportes.tablero.index', [], false));

        $this->actingAs(User::factory()->create())
            ->get(route('admin.reportes.tablero.index'))
            ->assertOk()
            ->assertSee('Selecciona un cliente');
    }

    public function test_requiere_sesion(): void
    {
        $this->get(route('admin.reportes.tablero.index'))->assertRedirect();
    }

    public function test_usa_el_cliente_pedido_y_cae_al_activo(): void
    {
        $a = Cliente::factory()->create(['empresa' => 'Empresa Alfa']);
        $b = Cliente::factory()->create(['empresa' => 'Empresa Beta']);
        $user = User::factory()->create();

        $this->actingAs($user)->withSession([ClienteActivo::CLAVE => $a->id])
            ->get(route('admin.reportes.tablero.index'))
            ->assertOk()->assertSee('lectura de lo ya capturado')->assertSee('Empresa Alfa');

        $this->actingAs($user)->withSession([ClienteActivo::CLAVE => $a->id])
            ->get(route('admin.reportes.tablero.index', ['cliente' => $b->id]))
            ->assertOk()->assertSee('Empresa Beta · lectura');
    }

    public function test_cliente_sin_keywords_muestra_estado_vacio(): void
    {
        $c = Cliente::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.reportes.tablero.index', ['cliente' => $c->id]))
            ->assertOk()
            ->assertSee('no tiene keywords en seguimiento');
    }

    public function test_muestra_distribucion_y_serie_con_mediciones(): void
    {
        $c = Cliente::factory()->create();
        $kw = Keyword::create(['cliente_id' => $c->id, 'keyword' => 'posicionamiento web', 'estado' => 'seguimiento']);
        KeywordMedicion::create(['keyword_id' => $kw->id, 'fecha' => '2026-09-01', 'posicion' => 15]);
        KeywordMedicion::create(['keyword_id' => $kw->id, 'fecha' => '2026-10-01', 'posicion' => 5]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.reportes.tablero.index', ['cliente' => $c->id]))
            ->assertOk()
            ->assertSee('data-tablero-distribucion', false)
            ->assertSee('data-tablero-serie', false)
            ->assertSee('posicionamiento web');
    }

    public function test_reportes_index_enlaza_al_tablero(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.reportes.index'))
            ->assertOk()
            ->assertSee(route('admin.reportes.tablero.index'), false);
    }
}
