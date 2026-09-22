<?php

namespace Tests\Unit\Support;

use App\Support\Correo\Bloques;
use App\Support\Correo\RenderizadorCorreo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * RenderizadorCorreo es estático y no toca la BD, pero necesita el contenedor
 * (route(), URL::signedRoute()), así que extiende TestCase sin RefreshDatabase.
 */
class RenderizadorCorreoTest extends TestCase
{
    // --- helpers -------------------------------------------------------------

    /** Un bloque de cada tipo del catálogo, con contenido reconocible. */
    private function todosLosBloques(): array
    {
        return [
            ['tipo' => 'heading', 'texto' => 'Encabezado de prueba', 'alineacion' => 'centro'],
            ['tipo' => 'text', 'texto' => "Primer párrafo\nSegundo párrafo"],
            ['tipo' => 'list', 'items' => ['Punto uno', 'Punto dos']],
            ['tipo' => 'kpi', 'items' => [['label' => 'Sesiones', 'valor' => '12,480'], ['label' => 'ROAS', 'valor' => '5.4x']]],
            ['tipo' => 'button', 'texto' => 'Ver reporte', 'url' => 'https://ejemplo.com/reporte'],
            ['tipo' => 'image', 'url' => 'https://ejemplo.com/grafica.png', 'alt' => 'Gráfica del mes'],
            ['tipo' => 'divider'],
            ['tipo' => 'footer', 'texto' => "RankPro Solutions\nAguascalientes"],
        ];
    }

    private function render(array $bloques, array $variables = [], array $opciones = []): string
    {
        return RenderizadorCorreo::render($bloques, Bloques::marcaPorDefecto(), $variables, $opciones);
    }

    // --- bloques -------------------------------------------------------------

    public function test_render_produces_the_eight_block_types(): void
    {
        $html = $this->render($this->todosLosBloques());

        // heading
        $this->assertStringContainsString('<h1 style=', $html);
        $this->assertStringContainsString('Encabezado de prueba</h1>', $html);
        $this->assertStringContainsString('text-align:center', $html);
        // text: un <p> por línea
        $this->assertStringContainsString('Primer párrafo</p>', $html);
        $this->assertStringContainsString('Segundo párrafo</p>', $html);
        // list
        $this->assertStringContainsString('&bull;', $html);
        $this->assertStringContainsString('Punto uno</td>', $html);
        $this->assertStringContainsString('Punto dos</td>', $html);
        // kpi
        $this->assertStringContainsString('12,480</div>', $html);
        $this->assertStringContainsString('ROAS</div>', $html);
        // button
        $this->assertStringContainsString('href="https://ejemplo.com/reporte"', $html);
        $this->assertStringContainsString('Ver reporte</a>', $html);
        // image
        $this->assertStringContainsString('src="https://ejemplo.com/grafica.png"', $html);
        $this->assertStringContainsString('alt="Gráfica del mes"', $html);
        // divider
        $this->assertStringContainsString('background:#E2E8F0', $html);
        // footer con salto de línea respetado
        $this->assertStringContainsString("RankPro Solutions<br>\nAguascalientes", $html);
    }

    public function test_render_uses_the_heading_as_document_title(): void
    {
        $html = $this->render($this->todosLosBloques());

        $this->assertStringContainsString('<title>Encabezado de prueba</title>', $html);
    }

    public function test_render_ignores_unknown_block_types(): void
    {
        $html = $this->render([['tipo' => 'inventado', 'texto' => 'NO DEBE SALIR']]);

        $this->assertStringNotContainsString('NO DEBE SALIR', $html);
    }

    // --- variables -----------------------------------------------------------

    public function test_render_substitutes_variables_and_blanks_missing_ones(): void
    {
        $html = $this->render([
            ['tipo' => 'text', 'texto' => 'Hola {{contacto}}, tu reporte de {{mes}} de {{servicio}}.'],
        ], ['contacto' => 'María', 'mes' => 'agosto 2026']);

        $this->assertStringContainsString('Hola María, tu reporte de agosto 2026 de .', $html);
        $this->assertStringNotContainsString('{{', $html);
    }

    public function test_render_substitutes_variables_inside_urls(): void
    {
        $html = $this->render([
            ['tipo' => 'button', 'texto' => 'Ver', 'url' => '{{enlace_reporte}}'],
        ], ['enlace_reporte' => 'https://ejemplo.com/r/1']);

        $this->assertStringContainsString('href="https://ejemplo.com/r/1"', $html);
    }

    public function test_variables_ejemplo_covers_the_whole_catalog(): void
    {
        $ejemplo = RenderizadorCorreo::variablesEjemplo();

        $this->assertSame(\App\Support\Correo\Variables::claves(), array_keys($ejemplo));
        $this->assertSame('María', $ejemplo['contacto']);
    }

    // --- estilos inline ------------------------------------------------------

    /**
     * Los clientes de correo no pintan flex/grid ni respetan hojas de estilo
     * con clases: todo lo visible va inline. El único <style> que el
     * renderizador emite es el de las media queries móviles, que un cliente
     * que no lo soporte ignora sin romper nada (ver docblock de la clase).
     */
    public function test_render_styles_everything_inline_without_flex_or_grid(): void
    {
        $html = $this->render($this->todosLosBloques());

        $this->assertStringNotContainsString('display:flex', $html);
        $this->assertStringNotContainsString('display:grid', $html);
        $this->assertStringNotContainsString('<link', $html);

        // Un solo <style>, y solo con selectores de respaldo móvil (.rp-*).
        $this->assertSame(1, preg_match_all('/<style\b/i', $html));
        preg_match('/<style[^>]*>(.*?)<\/style>/is', $html, $m);
        preg_match_all('/(?<![\w-])\.([a-z0-9_-]+)/i', $m[1], $selectores);
        $this->assertNotEmpty($selectores[1]);
        foreach ($selectores[1] as $selector) {
            $this->assertStringStartsWith('rp-', $selector, "Selector fuera del respaldo móvil: .{$selector}");
        }

        // Fuera del <style>, cada clase que aparece es de las de respaldo móvil:
        // ningún elemento depende de una clase para verse.
        $cuerpo = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        preg_match_all('/class="([^"]+)"/', $cuerpo, $clases);
        foreach ($clases[1] as $clase) {
            $this->assertMatchesRegularExpression('/^rp-[a-z]+$/', $clase);
        }

        // Y lo que se ve lleva su estilo inline.
        $this->assertGreaterThan(20, substr_count($cuerpo, 'style="'));
        $this->assertMatchesRegularExpression('/<h1 style="[^"]+">/', $cuerpo);
        $this->assertMatchesRegularExpression('/<p style="[^"]+">/', $cuerpo);
        $this->assertMatchesRegularExpression('/<a href="[^"]+" style="[^"]+">/', $cuerpo);
    }

    // --- token / enlaces firmados -------------------------------------------

    public function test_render_with_token_rewrites_hrefs_to_signed_clic_routes(): void
    {
        $html = $this->render([
            ['tipo' => 'button', 'texto' => 'Ver reporte', 'url' => 'https://ejemplo.com/x?a=1&b=2'],
        ], [], ['token' => 'tok-de-prueba']);

        $this->assertStringNotContainsString('href="https://ejemplo.com/x', $html);

        preg_match_all('/href="([^"]+)"/', $html, $m);
        $hrefs = array_map(fn ($h) => html_entity_decode($h, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $m[1]);

        // El botón y las redes de la marca por defecto: todos los http(s) pasan por correo.clic.
        $this->assertNotEmpty($hrefs);
        foreach ($hrefs as $href) {
            $this->assertStringStartsWith(route('correo.clic', ['token' => 'tok-de-prueba']).'?', $href);
            $this->assertStringContainsString('signature=', $href);
            $this->assertTrue(URL::hasValidSignature(Request::create($href)), "Firma inválida en {$href}");
        }

        $boton = array_filter($hrefs, fn ($h) => str_contains($h, urlencode('https://ejemplo.com/x?a=1&b=2')));
        $this->assertCount(1, $boton);
    }

    public function test_render_without_token_leaves_hrefs_untouched(): void
    {
        $html = $this->render([
            ['tipo' => 'button', 'texto' => 'Ver reporte', 'url' => 'https://ejemplo.com/x'],
        ]);

        $this->assertStringContainsString('href="https://ejemplo.com/x"', $html);
        $this->assertStringNotContainsString('signature=', $html);
    }

    // --- píxel ---------------------------------------------------------------

    public function test_render_with_pixel_url_injects_the_pixel_before_body_end(): void
    {
        $pixel = RenderizadorCorreo::pixelUrl('abc');
        $html = $this->render([['tipo' => 'text', 'texto' => 'Hola']], [], ['pixel_url' => $pixel]);

        $this->assertSame(route('correo.abierto', ['token' => 'abc']), $pixel);
        $this->assertStringContainsString('<img src="'.e($pixel).'" width="1" height="1"', $html);
        $this->assertLessThan(stripos($html, '</body>'), strpos($html, e($pixel)));
    }

    public function test_render_without_pixel_url_has_no_pixel(): void
    {
        $html = $this->render([['tipo' => 'text', 'texto' => 'Hola']]);

        $this->assertStringNotContainsString('width="1" height="1"', $html);
        $this->assertStringNotContainsString('/correo/a/', $html);
    }

    // --- html libre ----------------------------------------------------------

    public function test_html_libre_wins_over_blocks_and_gets_variables_pixel_and_links(): void
    {
        $html = $this->render(
            [['tipo' => 'heading', 'texto' => 'NO DEBE SALIR']],
            ['contacto' => 'María'],
            [
                'html_libre' => '<p>Hola {{contacto}} <a href="https://ejemplo.com/x">ir</a></p>',
                'pixel_url' => 'https://pix.test/p.gif',
                'token' => 'tok',
            ]
        );

        $this->assertStringNotContainsString('NO DEBE SALIR', $html);
        $this->assertStringNotContainsString('<!DOCTYPE', $html);
        $this->assertStringStartsWith('<p>Hola María ', $html);
        $this->assertStringNotContainsString('href="https://ejemplo.com/x"', $html);
        $this->assertStringContainsString('/correo/c/tok?', $html);
        // Sin </body>, el píxel va al final.
        $this->assertStringEndsWith('style="display:block;width:1px;height:1px;border:0">', $html);
        $this->assertStringContainsString('src="https://pix.test/p.gif"', $html);
    }

    public function test_blank_html_libre_falls_back_to_blocks(): void
    {
        $html = $this->render([['tipo' => 'heading', 'texto' => 'Desde bloques']], [], ['html_libre' => "  \n "]);

        $this->assertStringContainsString('Desde bloques</h1>', $html);
    }

    // --- escape --------------------------------------------------------------

    public function test_text_with_html_tags_is_escaped(): void
    {
        $html = $this->render([['tipo' => 'text', 'texto' => 'Hola <b>mundo</b> & co']]);

        $this->assertStringContainsString('Hola &lt;b&gt;mundo&lt;/b&gt; &amp; co', $html);
        $this->assertStringNotContainsString('<b>mundo</b>', $html);
    }

    public function test_variable_values_are_escaped_too(): void
    {
        $html = $this->render([['tipo' => 'heading', 'texto' => 'Hola {{contacto}}']], ['contacto' => '<script>x</script>']);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;x&lt;/script&gt;', $html);
    }

    public function test_urls_with_unsafe_schemes_are_dropped(): void
    {
        $html = $this->render([
            ['tipo' => 'button', 'texto' => 'Clic', 'url' => 'javascript:alert(1)'],
        ]);

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('href="#"', $html);
    }

    // --- editor visual ---------------------------------------------------------

    /** Sin `editor: true` (o sin la clave), el HTML no lleva ningún marcador nuevo. */
    public function test_without_editor_option_html_has_no_rp_markers(): void
    {
        $bloques = [
            ['tipo' => 'heading', 'texto' => 'Título', 'alineacion' => 'centro'],
            ['tipo' => 'text', 'texto' => 'Cuerpo del mensaje'],
            ['tipo' => 'button', 'texto' => 'Ver más', 'url' => 'https://ejemplo.com'],
            ['tipo' => 'footer', 'texto' => 'Pie de página'],
        ];

        $sinClave = $this->render($bloques);
        $conFalse = $this->render($bloques, [], ['editor' => false]);

        $this->assertSame($sinClave, $conFalse);
        $this->assertStringNotContainsString('data-rp-', $sinClave);
    }

    public function test_editor_option_wraps_each_non_empty_block_with_its_index_and_type(): void
    {
        $bloques = [
            ['tipo' => 'heading', 'texto' => 'Título'],
            ['tipo' => 'text', 'texto' => 'Cuerpo'],
            ['tipo' => 'text', 'texto' => ''], // vacío: no debe envolverse
            ['tipo' => 'button', 'texto' => 'Ver más', 'url' => 'https://ejemplo.com'],
        ];

        $html = $this->render($bloques, [], ['editor' => true]);

        $this->assertStringContainsString('<div data-rp-bloque="0" data-rp-tipo="heading">', $html);
        $this->assertStringContainsString('<div data-rp-bloque="1" data-rp-tipo="text">', $html);
        $this->assertStringNotContainsString('data-rp-bloque="2"', $html);
        $this->assertStringContainsString('<div data-rp-bloque="3" data-rp-tipo="button">', $html);

        // El wrapper es puramente estructural: sin atributo style.
        $this->assertMatchesRegularExpression('/<div data-rp-bloque="0" data-rp-tipo="heading">/', $html);
        $this->assertDoesNotMatchRegularExpression('/<div data-rp-bloque="0"[^>]*style=/', $html);
    }

    public function test_editor_option_groups_text_block_content_in_a_single_rp_texto_div(): void
    {
        $html = $this->render([
            ['tipo' => 'text', 'texto' => "Primer párrafo\nSegundo párrafo"],
        ], [], ['editor' => true]);

        $this->assertSame(1, preg_match_all('/data-rp-texto/', $html));
        preg_match('/<div data-rp-texto>(.*?)<\/div>/s', $html, $m);
        $this->assertNotEmpty($m);
        $this->assertStringContainsString('Primer párrafo</p>', $m[1]);
        $this->assertStringContainsString('Segundo párrafo</p>', $m[1]);
    }

    public function test_editor_option_groups_footer_block_content_in_a_single_rp_texto_div(): void
    {
        $html = $this->render([
            ['tipo' => 'footer', 'texto' => "RankPro Solutions\nAguascalientes"],
        ], [], ['editor' => true]);

        $this->assertSame(1, preg_match_all('/data-rp-texto/', $html));
        $this->assertStringContainsString("RankPro Solutions<br>\nAguascalientes", $html);
    }

    /**
     * Si el modo editor sustituyera las variables, la primera vez que alguien
     * editara el texto de un bloque se "hornearía" el valor de ejemplo sobre
     * el marcador real, perdiéndolo para siempre. El editor visual nunca debe
     * sustituir: el marcador se queda literal en heading/text/list/kpi/footer.
     */
    public function test_editor_option_leaves_variable_markers_untouched_instead_of_substituting(): void
    {
        $html = $this->render([
            ['tipo' => 'heading', 'texto' => 'Hola {{contacto}}'],
            ['tipo' => 'text', 'texto' => 'De {{cliente}}'],
            ['tipo' => 'list', 'items' => ['{{servicio}}']],
            ['tipo' => 'kpi', 'items' => [['label' => '{{mes}}', 'valor' => '{{monto}}'], ['label' => 'B', 'valor' => '2']]],
            ['tipo' => 'footer', 'texto' => 'Firma {{responsable}}'],
        ], ['contacto' => 'María', 'cliente' => 'Hotel Fratelli', 'servicio' => 'SEO', 'mes' => 'agosto', 'monto' => '$1', 'responsable' => 'Dilan'], ['editor' => true]);

        foreach (['{{contacto}}', '{{servicio}}', '{{mes}}', '{{monto}}', '{{responsable}}'] as $marcador) {
            $this->assertStringContainsString($marcador, $html, "El marcador {$marcador} no debería sustituirse en modo editor.");
        }
        // Dos excepciones a propósito, ninguna es texto editable en la previa:
        // el <title> del documento (no es visible) y el aviso legal fijo del
        // pie ("Recibes este correo porque X tiene servicios...").
        $this->assertStringContainsString('<title>Hola María</title>', $html);
        $this->assertStringContainsString('Recibes este correo porque Hotel Fratelli tiene servicios', $html);
    }

    /** Mismo criterio que con bloques: el HTML libre en modo editor no sustituye. */
    public function test_editor_option_on_html_libre_leaves_variable_markers_untouched(): void
    {
        $html = RenderizadorCorreo::render([], Bloques::marcaPorDefecto(), ['contacto' => 'María'], [
            'html_libre' => '<p>Hola {{contacto}}</p>',
            'editor' => true,
        ]);

        $this->assertSame('<p>Hola {{contacto}}</p>', $html);
    }
}
