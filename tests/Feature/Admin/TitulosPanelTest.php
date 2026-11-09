<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * El título de la pestaña sale de `$pageTitle` (layouts/admin.blade.php): sin él
 * queda «AgencyOS Admin» a secas y todas las pestañas del panel se ven iguales.
 * Este test recorre las rutas GET del panel sin parámetros y falla si alguna
 * página HTML del panel olvidó pasar el título.
 */
class TitulosPanelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Rutas que no pintan una pantalla del panel con el layout (descargas,
     * endpoints de datos) y por tanto no tienen pestaña que titular.
     */
    private const SIN_PANTALLA = [
        'admin.dashboard.exportar',
        'admin.finanzas.exportar',
        'admin.usuarios.exportar',
    ];

    public function test_ninguna_pantalla_del_panel_queda_con_el_titulo_generico(): void
    {
        // Ninguna pantalla debe salir a internet para pintarse.
        Http::preventStrayRequests();
        Cliente::factory()->create();
        $this->actingAs(User::factory()->create());

        $genericas = [];
        $revisadas = 0;

        foreach (Route::getRoutes() as $ruta) {
            $nombre = $ruta->getName();

            if (! $nombre || ! str_starts_with($nombre, 'admin.')
                || ! in_array('GET', $ruta->methods(), true)
                || str_contains($ruta->uri(), '{')
                || in_array($nombre, self::SIN_PANTALLA, true)) {
                continue;
            }

            $respuesta = $this->get('/'.ltrim($ruta->uri(), '/'));

            // Solo cuentan las pantallas HTML que sí pintaron el layout del panel.
            if ($respuesta->getStatusCode() !== 200 || ! str_contains((string) $respuesta->headers->get('Content-Type'), 'text/html')) {
                continue;
            }

            $html = (string) $respuesta->getContent();
            if (! str_contains($html, 'app-shell__main')) {
                continue;
            }

            $revisadas++;
            if (preg_match('#<title>\s*AgencyOS Admin\s*</title>#', $html)) {
                $genericas[] = $nombre;
            }
        }

        $this->assertGreaterThan(20, $revisadas, 'El recorrido casi no encontró pantallas: revisa el filtro.');
        $this->assertSame([], $genericas, 'Pantallas sin $pageTitle: '.implode(', ', $genericas));
    }
}
