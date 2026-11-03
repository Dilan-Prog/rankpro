<?php

namespace Tests\Feature\Seguridad;

use App\Models\Archivo;
use App\Models\Cliente;
use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Models\Proyecto;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regresiones de la auditoría: URLs con esquema peligroso (javascript:) que acaban en href,
 * y subida base64 de la API sin tope ni lista de tipos.
 */
class ValidacionEntradasTest extends TestCase
{
    use RefreshDatabase;

    private function proyectoEnFase(string $fase): Proyecto
    {
        return Proyecto::create([
            'cliente_id' => Cliente::factory()->create()->id, 'nombre' => 'P', 'tipo' => 'web_nueva',
            'fase_actual' => $fase, 'porcentaje_avance' => 0, 'presupuesto' => 1, 'anticipo' => 0,
            'pagos_recibidos' => 0, 'estado' => 'activo',
        ]);
    }

    // --- 6) URLs ---------------------------------------------------------------

    public function test_las_urls_de_organizacion_rechazan_javascript(): void
    {
        $proyecto = $this->proyectoEnFase('organizacion');
        $this->actingAs(User::factory()->create());

        foreach (['url_repositorio', 'url_staging'] as $campo) {
            $this->postJson("/admin/desarrollo/{$proyecto->id}/fase/guardar", [$campo => 'javascript:alert(1)'])
                ->assertStatus(422)->assertJsonValidationErrors($campo);
        }

        $this->postJson("/admin/desarrollo/{$proyecto->id}/fase/guardar", [
            'url_repositorio' => 'https://github.com/rankpro/sitio', 'url_staging' => null,
        ])->assertOk();
    }

    public function test_la_url_de_produccion_rechaza_javascript(): void
    {
        $proyecto = $this->proyectoEnFase('control');
        $this->actingAs(User::factory()->create());

        $this->postJson("/admin/desarrollo/{$proyecto->id}/fase/guardar", ['url_produccion' => 'javascript:alert(1)'])
            ->assertStatus(422)->assertJsonValidationErrors('url_produccion');
        $this->postJson("/admin/desarrollo/{$proyecto->id}/fase/guardar", ['url_produccion' => 'https://sitio.com'])->assertOk();
    }

    public function test_la_url_del_sitio_seo_rechaza_javascript_en_web_y_api(): void
    {
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'seo']);
        $payload = ['cliente_id' => $cliente->id, 'servicio_id' => $servicio->id, 'nombre' => 'C', 'url_sitio' => 'javascript:alert(1)'];

        $this->actingAs(User::factory()->create())->postJson('/admin/seo', $payload)
            ->assertStatus(422)->assertJsonValidationErrors('url_sitio');

        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $this->postJson('/api/v1/seo/campanas', $payload)->assertStatus(422);
    }

    public function test_la_url_asignada_de_una_keyword_rechaza_esquemas_peligrosos_pero_acepta_rutas(): void
    {
        $cliente = Cliente::factory()->create();
        $lista = KeywordLista::create(['cliente_id' => $cliente->id, 'nombre' => 'L', 'canal' => 'seo', 'estado' => 'en_uso']);
        $base = [
            'cliente_id' => $cliente->id, 'lista_id' => $lista->id, 'keyword' => 'dentista',
            'tipo' => 'principal', 'estado' => 'en_uso',
        ];
        $this->actingAs(User::factory()->create());

        $this->postJson('/admin/keywords', $base + ['url_asignada' => 'javascript:alert(1)'])
            ->assertStatus(422)->assertJsonValidationErrors('url_asignada');
        $this->postJson('/admin/keywords', $base + ['keyword' => 'otra', 'url_asignada' => '//evil.com'])->assertStatus(422);
        $this->postJson('/admin/keywords', $base + ['keyword' => 'ruta', 'url_asignada' => '/implantes-dentales'])->assertCreated();
        $this->postJson('/admin/keywords', $base + ['keyword' => 'abs', 'url_asignada' => 'https://sitio.com/x'])->assertCreated();
    }

    // --- 7) Archivos base64 ----------------------------------------------------

    private function postBase64(string $contenido, string $nombre): \Illuminate\Testing\TestResponse
    {
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);

        return $this->postJson('/api/v1/archivos', [
            'cliente_id' => Cliente::factory()->create()->id,
            'tipo' => 'otro',
            'nombre' => $nombre,
            'contenido_base64' => base64_encode($contenido),
        ]);
    }

    public function test_base64_rechaza_un_ejecutable_aunque_el_nombre_diga_pdf(): void
    {
        $this->postBase64("<?php echo 'pwn'; ?>\n", 'inocente.pdf')->assertStatus(422);

        $this->assertSame(0, Archivo::count());
    }

    public function test_base64_valido_guarda_la_extension_del_mime_y_no_la_del_nombre(): void
    {
        // Un PNG real con nombre «.pdf»: la extensión guardada sale del contenido.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');

        $this->postBase64($png, 'foto.pdf')->assertCreated();

        $this->assertSame('png', Archivo::first()->extension);
    }
}
