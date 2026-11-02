<?php

namespace Tests\Feature\Admin;

use App\Enums\EstadoPropuesta;
use App\Models\Bug;
use App\Models\Cliente;
use App\Models\Propuesta;
use App\Models\Proyecto;
use App\Models\User;
use App\Support\Propuestas\Plantilla;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El cliente activo del menú (grupo Marca) acota las listas de Propuestas y de
 * Desarrollo: con uno elegido solo se ve lo suyo, y sin él se ve todo.
 */
class ClienteActivoListasTest extends TestCase
{
    use RefreshDatabase;

    private function propuesta(Cliente $cliente, string $titulo): Propuesta
    {
        return Propuesta::create(Plantilla::vacio() + [
            'cliente_id' => $cliente->id,
            'seo_campana_id' => null,
            'titulo' => $titulo,
            'estado' => EstadoPropuesta::Borrador->value,
        ]);
    }

    private function proyecto(Cliente $cliente, string $nombre): Proyecto
    {
        return Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => $nombre,
            'tipo' => 'web_nueva',
            'fase_actual' => 'planeacion',
            'porcentaje_avance' => 0,
            'presupuesto' => 10000,
            'anticipo' => 0,
            'pagos_recibidos' => 0,
            'estado' => 'activo',
        ]);
    }

    // --- Propuestas ---------------------------------------------------------

    public function test_propuestas_con_cliente_activo_solo_muestra_las_de_ese_cliente(): void
    {
        $a = Cliente::factory()->create(['nombre' => 'Cliente Alfa']);
        $b = Cliente::factory()->create(['nombre' => 'Cliente Beta']);
        $this->propuesta($a, 'Propuesta de Alfa');
        $this->propuesta($b, 'Propuesta de Beta');

        $respuesta = $this->actingAs(User::factory()->create())
            ->withSession(['cliente_activo_id' => $a->id])
            ->get(route('admin.propuestas.index'));

        $respuesta->assertOk()->assertSee('Propuesta de Alfa')->assertDontSee('Propuesta de Beta');
        $respuesta->assertSee('Mostrando solo a');
    }

    public function test_propuestas_sin_cliente_activo_muestra_todas_y_sin_aviso(): void
    {
        $a = Cliente::factory()->create();
        $b = Cliente::factory()->create();
        $this->propuesta($a, 'Propuesta de Alfa');
        $this->propuesta($b, 'Propuesta de Beta');

        $this->actingAs(User::factory()->create())
            ->get(route('admin.propuestas.index'))
            ->assertOk()
            ->assertSee('Propuesta de Alfa')
            ->assertSee('Propuesta de Beta')
            ->assertDontSee('Mostrando solo a');
    }

    public function test_propuestas_un_cliente_id_explicito_manda_sobre_el_cliente_activo(): void
    {
        $a = Cliente::factory()->create();
        $b = Cliente::factory()->create();
        $this->propuesta($a, 'Propuesta de Alfa');
        $this->propuesta($b, 'Propuesta de Beta');

        $this->actingAs(User::factory()->create())
            ->withSession(['cliente_activo_id' => $a->id])
            ->get(route('admin.propuestas.index', ['cliente_id' => $b->id]))
            ->assertOk()
            ->assertSee('Propuesta de Beta')
            ->assertDontSee('Propuesta de Alfa');
    }

    // --- Desarrollo ---------------------------------------------------------

    public function test_desarrollo_con_cliente_activo_solo_muestra_sus_proyectos_y_bugs(): void
    {
        $a = Cliente::factory()->create();
        $b = Cliente::factory()->create();
        $proyectoA = $this->proyecto($a, 'Sitio de Alfa');
        $proyectoB = $this->proyecto($b, 'Sitio de Beta');
        Bug::create(['proyecto_id' => $proyectoA->id, 'titulo' => 'Bug de Alfa', 'prioridad' => 'media', 'estado' => 'abierto']);
        Bug::create(['proyecto_id' => $proyectoB->id, 'titulo' => 'Bug de Beta', 'prioridad' => 'media', 'estado' => 'abierto']);

        $respuesta = $this->actingAs(User::factory()->create())
            ->withSession(['cliente_activo_id' => $a->id])
            ->get(route('admin.desarrollo.index'));

        $respuesta->assertOk()
            ->assertSee('Sitio de Alfa')->assertSee('Bug de Alfa')
            ->assertDontSee('Sitio de Beta')->assertDontSee('Bug de Beta')
            ->assertSee('Mostrando solo a');
    }

    public function test_desarrollo_sin_cliente_activo_muestra_todo(): void
    {
        $a = Cliente::factory()->create();
        $b = Cliente::factory()->create();
        $this->proyecto($a, 'Sitio de Alfa');
        $this->proyecto($b, 'Sitio de Beta');

        $this->actingAs(User::factory()->create())
            ->get(route('admin.desarrollo.index'))
            ->assertOk()
            ->assertSee('Sitio de Alfa')
            ->assertSee('Sitio de Beta')
            ->assertDontSee('Mostrando solo a');
    }

    public function test_el_boton_ver_todos_quita_el_cliente_activo(): void
    {
        $a = Cliente::factory()->create();
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->withSession(['cliente_activo_id' => $a->id])
            ->post(route('admin.cliente-activo'), ['cliente_id' => ''])
            ->assertRedirect();

        $this->assertNull(session('cliente_activo_id'));
    }
}
