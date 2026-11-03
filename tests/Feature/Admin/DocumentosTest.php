<?php

namespace Tests\Feature\Admin;

use App\Models\Archivo;
use App\Models\Cliente;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Numeracion y validacion de los PDF de contrato/propuesta (DocumentosController).
 */
class DocumentosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function generarContrato(User $user, Cliente $cliente, Servicio $servicio)
    {
        return $this->actingAs($user)->post(route('admin.archivos.contratos.store'), [
            'cliente_id' => $cliente->id,
            'servicios' => [$servicio->id],
            'fecha_inicio' => '2026-01-01',
            'condiciones' => 'Condiciones de prueba',
        ]);
    }

    public function test_borrar_un_contrato_intermedio_no_reutiliza_el_numero_ni_sobrescribe_el_pdf(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id]);
        $anio = now()->format('Y');

        $this->withoutExceptionHandling();
        foreach (range(1, 3) as $_) {
            $this->generarContrato($user, $cliente, $servicio)->assertOk();
        }

        $segundo = Archivo::where('ruta_archivo', 'like', "%CONT-{$anio}-0002.pdf")->firstOrFail();
        Storage::disk('local')->delete($segundo->ruta_archivo);
        $segundo->delete();

        $this->generarContrato($user, $cliente, $servicio)->assertOk();

        Storage::disk('local')->assertExists("clientes/{$cliente->id}/contratos/CONT-{$anio}-0004.pdf");
        $this->assertSame(1, Archivo::where('ruta_archivo', 'like', "%CONT-{$anio}-0003.pdf")->count());
    }

    public function test_contrato_con_servicios_ajenos_al_cliente_responde_422(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $otro = Cliente::factory()->create();
        $ajeno = Servicio::factory()->create(['cliente_id' => $otro->id]);

        $this->generarContrato($user, $cliente, $ajeno)->assertSessionHasErrors('servicios');
        $this->assertSame(0, Archivo::count());
    }
}
