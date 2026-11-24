<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\RedSocial;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Configuración > Redes sociales: iconos del pie y sameAs. */
class RedesSocialesTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(array $modulos = ['configuracion']): User
    {
        $rol = Role::query()->create(['name' => 'operador', 'label' => 'Operador']);
        foreach ($modulos as $modulo) {
            $rol->permissions()->attach(Permission::query()->firstOrCreate(['name' => $modulo], ['label' => $modulo, 'module' => $modulo])->id);
        }

        return User::factory()->create(['role_id' => $rol->id, 'is_active' => true]);
    }

    private function guardar(array $redes)
    {
        return $this->actingAs($this->usuario())->put(route('admin.configuracion.redes.update'), ['redes' => $redes]);
    }

    public function test_exige_sesion_y_permiso(): void
    {
        $this->get(route('admin.configuracion.redes.edit'))->assertRedirect(route('login'));
        $this->put(route('admin.configuracion.redes.update'), [])->assertRedirect(route('login'));

        config(['permisos.aplicar' => true]);
        $this->actingAs($this->usuario(['blog']))->get(route('admin.configuracion.redes.edit'))->assertForbidden();
    }

    public function test_la_pantalla_muestra_iconos_y_lo_guardado(): void
    {
        RedSocial::query()->create(['red' => 'facebook', 'url' => 'https://www.facebook.com/rankpro', 'orden' => 0]);

        $this->actingAs($this->usuario())->get(route('admin.configuracion.redes.edit'))->assertOk()
            ->assertSee('Instagram')
            ->assertSee('value="https://www.facebook.com/rankpro"', false);
    }

    public function test_guarda_en_orden_completa_https_e_ignora_filas_vacias(): void
    {
        $this->guardar([
            7 => ['red' => 'instagram', 'url' => 'instagram.com/rankpro', 'activa' => '1'],
            2 => ['red' => 'facebook', 'url' => 'https://www.facebook.com/rankpro', 'activa' => '1'],
            9 => ['url' => '  '],
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.configuracion.redes.edit'));

        $this->assertSame(
            ['https://instagram.com/rankpro', 'https://www.facebook.com/rankpro'],
            RedSocial::query()->orderBy('orden')->pluck('url')->all(),
        );
    }

    public function test_el_pie_muestra_solo_las_activas_y_se_actualiza_al_guardar(): void
    {
        $this->get('/nosotros')->assertDontSee('site-footer__redes', false);

        $this->guardar([
            ['red' => 'facebook', 'url' => 'https://www.facebook.com/rankpro', 'activa' => '1'],
            ['red' => 'tiktok', 'url' => 'https://www.tiktok.com/@rankpro'],
        ]);

        $this->get('/nosotros')
            ->assertSee('href="https://www.facebook.com/rankpro" target="_blank" rel="noopener me"', false)
            ->assertSee('RankPro en Facebook')
            ->assertDontSee('tiktok.com/@rankpro', false);

        $this->guardarDeNuevo([]);
        $this->get('/nosotros')->assertDontSee('site-footer__redes', false);
    }

    private function guardarDeNuevo(array $redes): void
    {
        $this->put(route('admin.configuracion.redes.update'), ['redes' => $redes])->assertSessionHasNoErrors();
    }

    public function test_el_same_as_lleva_perfiles_y_no_chats(): void
    {
        $this->guardar([
            ['red' => 'linkedin', 'url' => 'https://www.linkedin.com/company/rankpro', 'activa' => '1'],
            ['red' => 'whatsapp', 'url' => 'https://wa.me/525562230266', 'activa' => '1'],
        ]);

        $html = $this->get('/nosotros')->getContent();

        $this->assertStringContainsString('"sameAs":["https://www.linkedin.com/company/rankpro"]', $html);
        $this->assertStringContainsString('href="https://wa.me/525562230266" target="_blank" rel="noopener nofollow"', $html);
    }

    /** @dataProvider enlacesInvalidos */
    public function test_rechaza_enlaces_invalidos_sin_borrar_lo_guardado(array $fila, string $campo): void
    {
        RedSocial::query()->create(['red' => 'facebook', 'url' => 'https://www.facebook.com/rankpro', 'orden' => 0]);

        $this->guardar([$fila + ['activa' => '1']])->assertSessionHasErrors("redes.0.$campo");

        $this->assertSame(1, RedSocial::query()->count());
    }

    public static function enlacesInvalidos(): array
    {
        return [
            'javascript' => [['red' => 'enlace', 'nombre' => 'X', 'url' => 'javascript:alert(1)'], 'url'],
            'data' => [['red' => 'enlace', 'nombre' => 'X', 'url' => 'data:text/html,hola'], 'url'],
            'otra red' => [['red' => 'instagram', 'url' => 'https://www.facebook.com/rankpro'], 'url'],
            'dominio parecido' => [['red' => 'facebook', 'url' => 'https://facebook.com.malo.test/rankpro'], 'url'],
            'icono inexistente' => [['red' => 'myspace', 'url' => 'https://myspace.com/rankpro'], 'red'],
            'sin icono' => [['url' => 'https://www.facebook.com/rankpro'], 'red'],
            'otro enlace sin nombre' => [['red' => 'enlace', 'url' => 'https://ejemplo.com'], 'nombre'],
        ];
    }

    public function test_al_fallar_conserva_lo_escrito(): void
    {
        $this->actingAs($this->usuario())
            ->from(route('admin.configuracion.redes.edit'))
            ->followingRedirects()
            ->put(route('admin.configuracion.redes.update'), ['redes' => [['red' => 'instagram', 'url' => 'https://www.facebook.com/otra']]])
            ->assertSee('value="https://www.facebook.com/otra"', false)
            ->assertSee('no parece de Instagram');
    }
}
