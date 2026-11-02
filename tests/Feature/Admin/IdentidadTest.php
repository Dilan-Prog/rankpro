<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\IdentidadMarca;
use App\Models\User;
use App\Support\ClienteActivo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Módulo Identidad de marca: ficha única por cliente activo, paleta con
 * validación de hex, logo en el disco privado y estado vacío sin cliente.
 */
class IdentidadTest extends TestCase
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

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get(route('admin.identidad.index'))->assertRedirect(route('login'));
        $this->put(route('admin.identidad.guardar'), [])->assertRedirect(route('login'));
        $this->get(route('admin.identidad.logo'))->assertRedirect(route('login'));
    }

    public function test_sin_cliente_activo_muestra_estado_vacio(): void
    {
        $this->comoUsuarioCon(null)->get(route('admin.identidad.index'))
            ->assertOk()
            ->assertSee('Elige un cliente en el selector');
    }

    public function test_sin_cliente_activo_no_se_puede_guardar(): void
    {
        $this->comoUsuarioCon(null)->put(route('admin.identidad.guardar'), ['slogan' => 'x'])
            ->assertRedirect(route('admin.identidad.index'))
            ->assertSessionHasErrors('cliente');

        $this->assertSame(0, IdentidadMarca::count());
    }

    public function test_cliente_activo_sin_ficha_muestra_formulario_vacio(): void
    {
        $cliente = Cliente::factory()->create();

        $this->comoUsuarioCon($cliente)->get(route('admin.identidad.index'))
            ->assertOk()
            ->assertSee('Guardar identidad')
            ->assertSee('Sin logo');
    }

    public function test_guarda_ficha_completa_y_la_crea_si_no_existe(): void
    {
        $cliente = Cliente::factory()->create();

        $this->comoUsuarioCon($cliente)->put(route('admin.identidad.guardar'), [
            'slogan' => 'Lo hacemos simple',
            'colores' => [
                ['nombre' => 'Principal', 'hex' => '#0f9d6e'],
                ['nombre' => '', 'hex' => '#112233'],
            ],
            'fuente_titulos' => 'Poppins',
            'fuente_texto' => 'Inter',
            'voz' => 'Hablamos de tú.',
            'tonos' => ['cercana', 'directa', 'cercana'],
            'mision' => 'Misión',
            'vision' => 'Visión',
            'valores' => "Honestidad\nClaridad",
        ])->assertRedirect(route('admin.identidad.index'))->assertSessionHas('status');

        $identidad = $cliente->fresh()->identidad;
        $this->assertNotNull($identidad);
        $this->assertSame('Lo hacemos simple', $identidad->slogan);
        // assertEquals: la columna JSON de MySQL reordena las claves de cada objeto.
        $this->assertEquals([['nombre' => 'Principal', 'hex' => '#0F9D6E'], ['nombre' => 'Color', 'hex' => '#112233']], $identidad->paleta);
        $this->assertSame('Poppins', $identidad->fuente_titulos->value);
        $this->assertSame(['cercana', 'directa'], $identidad->tonos);

        $this->comoUsuarioCon($cliente)->get(route('admin.identidad.index'))
            ->assertOk()
            ->assertSee('Lo hacemos simple')
            ->assertSee('#0F9D6E');
    }

    public function test_guardar_dos_veces_actualiza_la_misma_ficha(): void
    {
        $cliente = Cliente::factory()->create();

        $this->comoUsuarioCon($cliente)->put(route('admin.identidad.guardar'), ['slogan' => 'Uno']);
        $this->comoUsuarioCon($cliente)->put(route('admin.identidad.guardar'), ['slogan' => 'Dos']);

        $this->assertSame(1, IdentidadMarca::where('cliente_id', $cliente->id)->count());
        $this->assertSame('Dos', $cliente->fresh()->identidad->slogan);
    }

    /** @dataProvider hexInvalidos */
    public function test_rechaza_hex_invalidos(string $hex): void
    {
        $cliente = Cliente::factory()->create();

        $this->comoUsuarioCon($cliente)->put(route('admin.identidad.guardar'), [
            'colores' => [['nombre' => 'Malo', 'hex' => $hex]],
        ])->assertSessionHasErrors('colores.0.hex');

        $this->assertSame(0, IdentidadMarca::count());
    }

    public static function hexInvalidos(): array
    {
        return [['0F9D6E'], ['#0F9D6'], ['#GGGGGG'], ['#0F9D6E1'], ['rojo'], ['']];
    }

    public function test_rechaza_fuente_fuera_de_la_lista_y_tono_desconocido(): void
    {
        $cliente = Cliente::factory()->create();

        $this->comoUsuarioCon($cliente)->put(route('admin.identidad.guardar'), [
            'fuente_titulos' => 'Comic Sans',
            'tonos' => ['sarcastica'],
        ])->assertSessionHasErrors(['fuente_titulos', 'tonos.0']);
    }

    public function test_unicidad_por_cliente_a_nivel_de_base(): void
    {
        $cliente = Cliente::factory()->create();
        IdentidadMarca::create(['cliente_id' => $cliente->id]);

        $this->expectException(QueryException::class);
        IdentidadMarca::create(['cliente_id' => $cliente->id]);
    }

    public function test_cada_cliente_tiene_su_ficha(): void
    {
        $a = Cliente::factory()->create();
        $b = Cliente::factory()->create();

        $this->comoUsuarioCon($a)->put(route('admin.identidad.guardar'), ['slogan' => 'De A']);
        $this->comoUsuarioCon($b)->put(route('admin.identidad.guardar'), ['slogan' => 'De B']);

        $this->assertSame('De A', $a->fresh()->identidad->slogan);
        $this->assertSame('De B', $b->fresh()->identidad->slogan);
    }

    public function test_sube_logo_al_disco_privado_y_lo_sirve(): void
    {
        Storage::fake('local');
        $cliente = Cliente::factory()->create();

        $this->comoUsuarioCon($cliente)->put(route('admin.identidad.guardar'), [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 100),
        ])->assertSessionHasNoErrors();

        $ruta = $cliente->fresh()->identidad->logo_ruta;
        $this->assertStringStartsWith("clientes/{$cliente->id}/identidad/", $ruta);
        Storage::disk('local')->assertExists($ruta);

        $this->comoUsuarioCon($cliente)->get(route('admin.identidad.logo'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_reemplazar_logo_borra_el_anterior_y_quitarlo_lo_elimina(): void
    {
        Storage::fake('local');
        $cliente = Cliente::factory()->create();

        $this->comoUsuarioCon($cliente)->put(route('admin.identidad.guardar'), ['logo' => UploadedFile::fake()->image('uno.png')]);
        $primera = $cliente->fresh()->identidad->logo_ruta;

        $this->comoUsuarioCon($cliente)->put(route('admin.identidad.guardar'), ['logo' => UploadedFile::fake()->image('dos.jpg')]);
        $segunda = $cliente->fresh()->identidad->logo_ruta;

        $this->assertNotSame($primera, $segunda);
        Storage::disk('local')->assertMissing($primera);
        Storage::disk('local')->assertExists($segunda);

        // Guardar sin archivo conserva el logo vigente.
        $this->comoUsuarioCon($cliente)->put(route('admin.identidad.guardar'), ['slogan' => 'x']);
        $this->assertSame($segunda, $cliente->fresh()->identidad->logo_ruta);

        $this->comoUsuarioCon($cliente)->put(route('admin.identidad.guardar'), ['quitar_logo' => '1']);
        $this->assertNull($cliente->fresh()->identidad->logo_ruta);
        Storage::disk('local')->assertMissing($segunda);

        $this->comoUsuarioCon($cliente)->get(route('admin.identidad.logo'))->assertNotFound();
    }

    public function test_rechaza_logo_que_no_es_imagen_o_pesa_demasiado(): void
    {
        Storage::fake('local');
        $cliente = Cliente::factory()->create();

        $this->comoUsuarioCon($cliente)->put(route('admin.identidad.guardar'), [
            'logo' => UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('logo');

        $this->comoUsuarioCon($cliente)->put(route('admin.identidad.guardar'), [
            'logo' => UploadedFile::fake()->image('grande.png')->size(3000),
        ])->assertSessionHasErrors('logo');

        $this->assertSame(0, IdentidadMarca::count());
    }

    public function test_cliente_borrado_con_soft_delete_no_rompe_la_pantalla(): void
    {
        $cliente = Cliente::factory()->create();
        IdentidadMarca::create(['cliente_id' => $cliente->id, 'slogan' => 'Hola']);
        $id = $cliente->id;
        $cliente->delete();

        // El cliente activo quedó apuntando a un borrado: se trata como «ninguno».
        $this->actingAs(User::factory()->create())
            ->withSession([ClienteActivo::CLAVE => $id])
            ->get(route('admin.identidad.index'))
            ->assertOk()
            ->assertSee('Elige un cliente en el selector');
    }
}
