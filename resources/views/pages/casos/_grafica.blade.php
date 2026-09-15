{{--
    Grafica de barras en CSS para una serie de la seccion "Evolucion".
    Espera $grafica = {titulo, subtitulo, fuente, serie[{label, valor}]}.

    Sin librerias: cada barra es un <li> con la altura en una variable CSS
    (--h) proporcional al maximo de la serie, y el tono verde se oscurece
    conforme avanza la serie (--tono), como pide la hoja de estilo. Sin ejes
    numericos: el valor va sobre cada barra y la cifra exacta ya esta en los
    KPIs. La linea de fuente es obligatoria, igual que en las cifras.
--}}
@php
    $fuentes = \App\Support\CasosExito::FUENTES;
    $serie = array_values($grafica['serie'] ?? []);
    $numeros = array_map(fn ($p) => (float) preg_replace('/[^\d.\-]/', '', (string) $p['valor']), $serie);
    $max = max(1e-9, max($numeros ?: [0]));
    $n = max(1, count($serie));
@endphp
@if ($serie)
    <figure class="cs-grafica">
        <figcaption>
            <p class="cs-grafica__titulo">{{ $grafica['titulo'] }}</p>
            @if (! empty($grafica['subtitulo']))
                <p class="cs-grafica__sub">{{ $grafica['subtitulo'] }}</p>
            @endif
        </figcaption>
        <ul class="cs-barras" role="list" aria-label="{{ $grafica['titulo'] }}">
            @foreach ($serie as $i => $punto)
                <li class="cs-barra" style="--h: {{ round($numeros[$i] / $max * 100, 1) }}%; --tono: {{ round(25 + 75 * $i / max(1, $n - 1)) }}%;">
                    <span class="cs-barra__valor">{{ $punto['valor'] }}</span>
                    <span class="cs-barra__col" aria-hidden="true"></span>
                    <span class="sr-only" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);">{{ $punto['label'] }}</span>
                </li>
            @endforeach
        </ul>
        <ul class="cs-barras__labels" aria-hidden="true">
            @foreach ($serie as $punto)
                <li>{{ $punto['label'] }}</li>
            @endforeach
        </ul>
        <span class="cs-fuente">Fuente · {{ $fuentes[$grafica['fuente']] ?? $grafica['fuente'] }}</span>
    </figure>
@endif
