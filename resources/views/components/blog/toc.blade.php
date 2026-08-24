@props(['toc' => []])

@if (! empty($toc))
    <nav class="toc" aria-labelledby="toc-titulo">
        <h2 class="toc__titulo" id="toc-titulo">En este artículo</h2>
        <ol class="toc__lista">
            @foreach ($toc as $item)
                <li class="toc__item toc__item--n{{ $item['nivel'] ?? 2 }}">
                    <a class="toc__enlace" href="#{{ $item['id'] }}">{{ $item['texto'] }}</a>
                </li>
            @endforeach
        </ol>
    </nav>
@endif
