{{-- Schema del articulo, compartido por la plantilla generica y por las
     futuras vistas maquetadas a mano (pages/blog/articulos/{slug}.blade.php).
     Espera $articulo, $autor, $cluster y $servicios.

     Decisiones que NO deben deshacerse:
       - El FAQPage sale de contenido_html (FaqDelArticulo), nunca de un campo
         aparte: asi no puede decir algo distinto de lo visible.
       - publisher reutiliza el nodo Organization global (components/seo/jsonld)
         por @id en vez de duplicarlo.
       - about apunta por @id a los nodos Service que ya emite
         pages/servicios/_schema.blade.php. --}}
@php
    /** @var \App\Models\Articulo $articulo */
    $autorNombre = $autor?->name ?? 'Equipo RankPro';

    // Las paginas de autor (/nosotros/{autor}) TODAVIA NO EXISTEN: las hace una
    // fase posterior. Para no crear un @id sobre una URL 404, el fragmento
    // cuelga de /nosotros, que si existe. El dia que existan las fichas de
    // autor basta cambiar esta linea (y el enlace de la vista show).
    $autorId = url('/nosotros').'#person-'.Str::slug($autorNombre);

    $publicada = $articulo->fecha_publicacion?->toIso8601String();
    $modificada = $articulo->fechaEfectiva()?->toIso8601String();

    $faqsArticulo = \App\Support\Contenido\FaqDelArticulo::extraer($articulo->contenido_html);

    $imagen = $articulo->og_image ?: $articulo->imagen_destacada;
    if ($imagen && ! Str::startsWith($imagen, ['http://', 'https://'])) {
        $imagen = asset(ltrim($imagen, '/'));
    }

    $blogPosting = array_filter([
        '@type' => 'BlogPosting',
        '@id' => $articulo->url().'#article',
        // headline: maximo 110 caracteres (limite de Google). Se trunca el
        // titulo real, no el meta_title, que lleva sufijo de marca.
        'headline' => Str::limit($articulo->titulo, 110, ''),
        'description' => $articulo->meta_description ?: $articulo->resumen,
        'url' => $articulo->url(),
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $articulo->url()],
        'datePublished' => $publicada,
        'dateModified' => $modificada,
        'inLanguage' => 'es-MX',
        'wordCount' => $articulo->palabras,
        'articleSection' => $cluster['nombre'] ?? null,
        'image' => $imagen ?: null,
        'isPartOf' => ['@id' => route('blog.index').'#blog'],
        'publisher' => ['@id' => url('/#organization')],
        'author' => ['@id' => $autorId],
        'about' => collect($servicios)
            ->map(fn ($s) => ['@id' => route('servicios.show', $s['slug']).'#service'])
            ->values()
            ->all() ?: null,
    ], static fn ($v) => $v !== null && $v !== [] && $v !== '');
@endphp

@push('jsonld')
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                $blogPosting,
                [
                    '@type' => 'Person',
                    '@id' => $autorId,
                    'name' => $autorNombre,
                    'url' => url('/nosotros'),
                    'worksFor' => ['@id' => url('/#organization')],
                ],
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => url('/')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => route('blog.index')],
                        ['@type' => 'ListItem', 'position' => 3, 'name' => $cluster['nombre'] ?? 'Artículos', 'item' => isset($cluster['slug']) ? route('blog.cluster', $cluster['slug']) : route('blog.index')],
                        ['@type' => 'ListItem', 'position' => 4, 'name' => $articulo->titulo, 'item' => $articulo->url()],
                    ],
                ],
                ...($faqsArticulo === [] ? [] : [[
                    '@type' => 'FAQPage',
                    '@id' => $articulo->url().'#faq',
                    'mainEntity' => array_map(static fn (array $f): array => [
                        '@type' => 'Question',
                        'name' => $f['p'],
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['r']],
                    ], $faqsArticulo),
                ]]),
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endpush
