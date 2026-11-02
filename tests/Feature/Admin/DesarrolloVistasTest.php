<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\Proyecto;
use App\Models\ProyectoOrganizacion;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tarjetas (stack, repositorio, días, progreso por tareas) y vistas
 * adicionales (?vista=tabla|timeline) del índice de Desarrollo.
 */
class DesarrolloVistasTest extends TestCase
{
    use RefreshDatabase;

    private function proyecto(array $overrides = []): Proyecto
    {
        $cliente = Cliente::factory()->create();

        return Proyecto::create(array_merge([
            'cliente_id' => $cliente->id,
            'nombre' => 'Sitio de prueba',
            'tipo' => 'web_nueva',
            'fase_actual' => 'direccion',
            'porcentaje_avance' => 40,
            'presupuesto' => 1000,
            'anticipo' => 0,
            'pagos_recibidos' => 0,
            'estado' => 'activo',
        ], $overrides));
    }

    private function tarea(Proyecto $p, string $estado): void
    {
        Tarea::create([
            'proyecto_id' => $p->id,
            'titulo' => 'Tarea '.$estado,
            'prioridad' => 'media',
            'estado' => $estado,
        ]);
    }

    public function test_tarjeta_muestra_stack_repositorio_y_dias_restantes(): void
    {
        $p = $this->proyecto(['fecha_entrega_estimada' => now()->addDays(5)->toDateString()]);
        ProyectoOrganizacion::create([
            'proyecto_id' => $p->id,
            'stack_tecnologico' => 'Laravel, MySQL / Vue',
            'url_repositorio' => 'https://git.example.test/rankpro/sitio',
        ]);

        $res = $this->actingAs(User::factory()->create())->get(route('admin.desarrollo.index'));

        $res->assertOk()
            ->assertSee('proyecto-chip', false)
            ->assertSeeInOrder(['Laravel', 'MySQL', 'Vue'])
            ->assertSee('https://git.example.test/rankpro/sitio', false)
            ->assertSee('Entrega en 5 días');
    }

    public function test_entrega_vencida_se_marca_y_sin_fecha_no_muestra_dias(): void
    {
        $this->proyecto(['nombre' => 'Atrasado', 'fecha_entrega_estimada' => now()->subDays(3)->toDateString()]);
        $this->proyecto(['nombre' => 'Sin fecha', 'fecha_entrega_estimada' => null]);

        $res = $this->actingAs(User::factory()->create())->get(route('admin.desarrollo.index'));

        $res->assertSee('Entrega vencida (3d)');
        $res->assertDontSee('Entrega en ');
    }

    public function test_el_progreso_sale_de_las_tareas_cuando_existen(): void
    {
        $con = $this->proyecto(['nombre' => 'Con tareas', 'porcentaje_avance' => 10]);
        foreach (['completada', 'completada', 'pendiente', 'en_progreso'] as $estado) {
            $this->tarea($con, $estado);
        }
        $this->proyecto(['nombre' => 'Sin tareas', 'porcentaje_avance' => 70]);

        $res = $this->actingAs(User::factory()->create())->get(route('admin.desarrollo.index'));

        $res->assertSee('2/4 tareas');
        $res->assertSee('50%');
        // Sin tareas se conserva el avance manual de la fase.
        $res->assertSee('70%');
    }

    public function test_vista_tabla_se_muestra_activa_con_el_parametro(): void
    {
        $this->proyecto(['nombre' => 'Proyecto en tabla']);

        $res = $this->actingAs(User::factory()->create())->get(route('admin.desarrollo.index', ['vista' => 'tabla']));

        $res->assertOk()
            ->assertSee('data-proyectos-tabla', false)
            ->assertSee('data-ordenar="entrega"', false)
            ->assertSee('Proyecto en tabla');
        $html = $res->getContent();
        $this->assertMatchesRegularExpression('/data-view-panel="tabla"[^>]*>/', $html);
        $this->assertDoesNotMatchRegularExpression('/data-view-panel="tabla"[^>]*hidden/', $html);
        $this->assertMatchesRegularExpression('/data-view-panel="filas"[^>]*hidden/', $html);
    }

    public function test_vista_invalida_cae_en_filas(): void
    {
        $this->proyecto();

        $html = $this->actingAs(User::factory()->create())
            ->get(route('admin.desarrollo.index', ['vista' => 'inventada']))
            ->getContent();

        $this->assertDoesNotMatchRegularExpression('/data-view-panel="filas"[^>]*hidden/', $html);
        $this->assertMatchesRegularExpression('/data-view-panel="timeline"[^>]*hidden/', $html);
    }

    public function test_timeline_dibuja_barras_y_lista_aparte_los_proyectos_sin_fechas(): void
    {
        $this->proyecto([
            'nombre' => 'Con fechas',
            'fecha_inicio' => now()->subDays(10)->toDateString(),
            'fecha_entrega_estimada' => now()->addDays(20)->toDateString(),
        ]);
        $this->proyecto(['nombre' => 'Sin fechas', 'fecha_inicio' => null, 'fecha_entrega_estimada' => null]);

        $res = $this->actingAs(User::factory()->create())->get(route('admin.desarrollo.index', ['vista' => 'timeline']));

        $res->assertOk()
            ->assertSee('timeline__barra', false)
            ->assertSee('timeline__hoy', false)
            ->assertSee('Sin fechas para dibujar');
    }
}
