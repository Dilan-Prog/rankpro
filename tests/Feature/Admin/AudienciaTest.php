<?php

namespace Tests\Feature\Admin;

use App\Models\BuyerPersona;
use App\Models\Cliente;
use App\Models\User;
use App\Support\ClienteActivo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Módulo Audiencia: CRUD manual de buyer personas del cliente activo,
 * orden con flechas y aislamiento entre clientes.
 */
class AudienciaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // El manifest de Vite versionado no conoce los assets nuevos hasta compilar.
        $this->withoutVite();
    }

    private function comoUsuarioCon(?Cliente $cliente)
    {
        $caso = $this->actingAs(User::factory()->create());

        return $cliente ? $caso->withSession([ClienteActivo::CLAVE => $cliente->id]) : $caso;
    }

    private function persona(Cliente $cliente, string $nombre, int $orden): BuyerPersona
    {
        return BuyerPersona::create(['cliente_id' => $cliente->id, 'nombre' => $nombre, 'orden' => $orden]);
    }

    private function nombresEnOrden(Cliente $cliente): array
    {
        return $cliente->buyerPersonas()->pluck('nombre')->all();
    }

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get(route('admin.audiencia.index'))->assertRedirect(route('login'));
        $this->post(route('admin.audiencia.store'), [])->assertRedirect(route('login'));
    }

    public function test_sin_cliente_activo_muestra_estado_vacio_y_no_deja_crear(): void
    {
        $this->comoUsuarioCon(null)->get(route('admin.audiencia.index'))
            ->assertOk()
            ->assertSee('Elige un cliente en el selector');

        $this->comoUsuarioCon(null)->post(route('admin.audiencia.store'), ['nombre' => 'X'])
            ->assertSessionHasErrors('cliente');

        $this->assertSame(0, BuyerPersona::count());
    }

    public function test_cliente_sin_personas_muestra_invitacion_a_agregar(): void
    {
        $cliente = Cliente::factory()->create();

        $this->comoUsuarioCon($cliente)->get(route('admin.audiencia.index'))
            ->assertOk()
            ->assertSee('todavía no tiene buyer personas');
    }

    public function test_crear_persona_asigna_el_siguiente_orden(): void
    {
        $cliente = Cliente::factory()->create();

        foreach (['Laura', 'Marco'] as $nombre) {
            $this->comoUsuarioCon($cliente)->post(route('admin.audiencia.store'), [
                'nombre' => $nombre,
                'perfil' => 'Dueña de negocio',
                'dolores' => "Poco tiempo\nPrecios altos",
                'mensaje' => 'Resultados claros',
                'canales' => 'Instagram',
            ])->assertRedirect(route('admin.audiencia.index'))->assertSessionHas('status');
        }

        $this->assertSame(['Laura', 'Marco'], $this->nombresEnOrden($cliente));
        $this->assertSame(['Poco tiempo', 'Precios altos'], $cliente->buyerPersonas->first()->doloresLista());

        $this->comoUsuarioCon($cliente)->get(route('admin.audiencia.index'))
            ->assertOk()->assertSee('Laura')->assertSee('Marco')->assertSee('Precios altos');
    }

    public function test_nombre_es_obligatorio(): void
    {
        $cliente = Cliente::factory()->create();

        $this->comoUsuarioCon($cliente)->post(route('admin.audiencia.store'), ['nombre' => ''])
            ->assertSessionHasErrors('nombre');

        $this->assertSame(0, BuyerPersona::count());
    }

    public function test_editar_y_eliminar(): void
    {
        $cliente = Cliente::factory()->create();
        $persona = $this->persona($cliente, 'Laura', 0);

        $this->comoUsuarioCon($cliente)->put(route('admin.audiencia.update', $persona), ['nombre' => 'Laura M.', 'canales' => 'Google'])
            ->assertRedirect(route('admin.audiencia.index'));
        $this->assertSame('Laura M.', $persona->fresh()->nombre);
        $this->assertSame('Google', $persona->fresh()->canales);

        $this->comoUsuarioCon($cliente)->delete(route('admin.audiencia.destroy', $persona))
            ->assertRedirect(route('admin.audiencia.index'));
        $this->assertSame(0, BuyerPersona::count());
    }

    public function test_mover_arriba_y_abajo_reordena_y_respeta_los_extremos(): void
    {
        $cliente = Cliente::factory()->create();
        $a = $this->persona($cliente, 'A', 0);
        $b = $this->persona($cliente, 'B', 1);
        $c = $this->persona($cliente, 'C', 2);

        $this->comoUsuarioCon($cliente)->post(route('admin.audiencia.mover', $c), ['direccion' => 'arriba']);
        $this->assertSame(['A', 'C', 'B'], $this->nombresEnOrden($cliente));

        $this->comoUsuarioCon($cliente)->post(route('admin.audiencia.mover', $a), ['direccion' => 'abajo']);
        $this->assertSame(['C', 'A', 'B'], $this->nombresEnOrden($cliente));

        // Extremos: no pasa nada y no falla.
        $this->comoUsuarioCon($cliente)->post(route('admin.audiencia.mover', $c), ['direccion' => 'arriba'])->assertRedirect();
        $this->comoUsuarioCon($cliente)->post(route('admin.audiencia.mover', $b), ['direccion' => 'abajo'])->assertRedirect();
        $this->assertSame(['C', 'A', 'B'], $this->nombresEnOrden($cliente));

        $this->comoUsuarioCon($cliente)->post(route('admin.audiencia.mover', $a), ['direccion' => 'diagonal'])
            ->assertSessionHasErrors('direccion');
    }

    public function test_mover_corrige_ordenes_repetidos(): void
    {
        $cliente = Cliente::factory()->create();
        $this->persona($cliente, 'A', 0);
        $b = $this->persona($cliente, 'B', 0);

        $this->comoUsuarioCon($cliente)->post(route('admin.audiencia.mover', $b), ['direccion' => 'arriba']);

        $this->assertSame(['B', 'A'], $this->nombresEnOrden($cliente));
        $this->assertSame([0, 1], $cliente->buyerPersonas()->pluck('orden')->all());
    }

    public function test_no_se_puede_tocar_una_persona_de_otro_cliente(): void
    {
        $mio = Cliente::factory()->create();
        $ajeno = Cliente::factory()->create();
        $persona = $this->persona($ajeno, 'Ajena', 0);

        $this->comoUsuarioCon($mio)->put(route('admin.audiencia.update', $persona), ['nombre' => 'Hack'])->assertNotFound();
        $this->comoUsuarioCon($mio)->delete(route('admin.audiencia.destroy', $persona))->assertNotFound();
        $this->comoUsuarioCon($mio)->post(route('admin.audiencia.mover', $persona), ['direccion' => 'abajo'])->assertNotFound();
        $this->comoUsuarioCon(null)->delete(route('admin.audiencia.destroy', $persona))->assertNotFound();

        $this->assertSame('Ajena', $persona->fresh()->nombre);
    }

    public function test_las_personas_se_listan_solo_del_cliente_activo(): void
    {
        $a = Cliente::factory()->create();
        $b = Cliente::factory()->create();
        $this->persona($a, 'PersonaDeA', 0);
        $this->persona($b, 'PersonaDeB', 0);

        $this->comoUsuarioCon($a)->get(route('admin.audiencia.index'))
            ->assertSee('PersonaDeA')->assertDontSee('PersonaDeB');
    }

    public function test_cliente_borrado_con_soft_delete_no_rompe_la_pantalla(): void
    {
        $cliente = Cliente::factory()->create();
        $this->persona($cliente, 'Laura', 0);
        $id = $cliente->id;
        $cliente->delete();

        $this->actingAs(User::factory()->create())
            ->withSession([ClienteActivo::CLAVE => $id])
            ->get(route('admin.audiencia.index'))
            ->assertOk()
            ->assertSee('Elige un cliente en el selector');
    }
}
