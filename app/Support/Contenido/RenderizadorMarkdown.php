<?php

namespace App\Support\Contenido;

use DOMDocument;
use DOMXPath;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Convierte el Markdown de un articulo a HTML, y de paso extrae la tabla de
 * contenidos y el numero de palabras.
 *
 * Se usa AL GUARDAR, no en cada visita: el resultado se persiste en las
 * columnas contenido_html / toc / palabras de la tabla articulos.
 *
 * Por que un Environment propio y no Str::markdown():
 *   1. Hace falta HeadingPermalinkExtension para que los H2/H3 tengan `id`.
 *      Sin `id` no hay tabla de contenidos ni enlaces profundos, y son los
 *      enlaces profundos los que Google usa para los "saltar a la seccion"
 *      de la SERP.
 *   2. html_input: 'escape'. El contenido lo escribe el equipo, pero el dia que
 *      alguien pegue Markdown de una fuente externa esta es la unica barrera.
 */
class RenderizadorMarkdown
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
            'heading_permalink' => [
                'html_class' => 'ancla-titulo',
                'id_prefix' => '',
                'fragment_prefix' => '',
                'insert' => 'after',
                'min_heading_level' => 2,
                'max_heading_level' => 3,
                'title' => 'Enlace a esta sección',
                'symbol' => '#',
                'aria_hidden' => true,
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new HeadingPermalinkExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new AttributesExtension());
        $environment->addExtension(new AutolinkExtension());

        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * @return array{html: string, toc: list<array{id: string, texto: string, nivel: int}>, palabras: int}
     */
    public function procesar(string $markdown): array
    {
        $html = (string) $this->converter->convert($markdown);

        return [
            'html' => $html,
            'toc' => $this->extraerToc($html),
            'palabras' => $this->contarPalabras($html),
        ];
    }

    public function soloHtml(string $markdown): string
    {
        return (string) $this->converter->convert($markdown);
    }

    /**
     * Tabla de contenidos a partir del HTML YA renderizado.
     *
     * Se hace sobre el HTML y no sobre el Markdown crudo a proposito: un "##"
     * dentro de un bloque de codigo produciria entradas fantasma si se buscara
     * con una expresion regular sobre el texto original.
     *
     * @return list<array{id: string, texto: string, nivel: int}>
     */
    private function extraerToc(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $doc = new DOMDocument();

        // El prologo XML fuerza UTF-8; sin el, DOMDocument asume ISO-8859-1 y
        // destroza los acentos. LIBXML_NOERROR silencia los avisos por
        // fragmentos de HTML sin <html>/<body>, que es justo nuestro caso.
        $previo = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div>'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        $toc = [];

        foreach ((new DOMXPath($doc))->query('//h2|//h3') as $nodo) {
            $id = $nodo->getAttribute('id');

            if ($id === '') {
                continue; // Sin ancla no se puede enlazar; no entra en la TOC.
            }

            // El texto incluye el simbolo del permalink ("#"): se quita para que
            // la TOC no muestre almohadillas sueltas.
            $texto = trim(preg_replace('/\s*#\s*$/u', '', $nodo->textContent));

            if ($texto === '') {
                continue;
            }

            $toc[] = [
                'id' => $id,
                'texto' => $texto,
                'nivel' => (int) substr($nodo->nodeName, 1),
            ];
        }

        return $toc;
    }

    /**
     * Palabras del texto visible, sin marcado ni bloques de codigo.
     *
     * Los bloques de codigo se excluyen porque inflan el conteo sin ser lectura:
     * un articulo de 900 palabras con 600 lineas de codigo no cumple el estandar
     * editorial aunque el contador diga que si.
     */
    private function contarPalabras(string $html): int
    {
        $sinCodigo = preg_replace('#<pre\b[^>]*>.*?</pre>#is', ' ', $html) ?? $html;
        $texto = html_entity_decode(strip_tags($sinCodigo), ENT_QUOTES, 'UTF-8');

        return str_word_count($texto, 0, 'áéíóúüñÁÉÍÓÚÜÑ¿¡0123456789');
    }
}
