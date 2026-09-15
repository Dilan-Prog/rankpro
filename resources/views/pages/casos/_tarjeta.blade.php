{{--
    Tarjeta de caso para el listado. Espera $caso (forma de CasosExito::todos()).

    Reglas:
      - `nombre`/`url` nulos = caso anonimizado: banda con trama, marco
        punteado en lugar de iniciales, "Cliente anonimizado · Publicado por
        sector" y ningun enlace saliente.
      - La cifra estrella es kpis[0] y va siempre con su fuente.
      - Toda la tarjeta es clicable a traves del enlace del titulo (::after).
--}}
@php
    $sector = \App\Support\CasosExito::SECTORES[$caso['sector']];
    $servicios = \App\Support\CasosExito::SERVICIOS;
    $fuentes = \App\Support\CasosExito::FUENTES;
    $anonimo = $caso['nombre'] === null || $caso['url'] === null;
    $estrella = $caso['kpis'][0] ?? null;
    $urlCaso = route('casos.show', $caso['slug']);
@endphp
<article class="cs-card" data-sector="{{ $caso['sector'] }}" data-servicios="{{ implode(' ', $caso['servicios']) }}">
    <div class="cs-card__banda {{ $anonimo ? 'cs-card__banda--anon' : 'cs-card__banda--'.$sector['tono'] }}">
        <div class="cs-card__cliente">
            @if ($anonimo)
                <span class="cs-logo cs-logo--anon" aria-hidden="true"></span>
                <div>
                    <div class="cs-card__nombre">Cliente anonimizado</div>
                    <div class="cs-card__sub">Publicado por sector</div>
                </div>
            @else
                <span class="cs-logo" aria-hidden="true">{{ $caso['iniciales'] }}</span>
                <div>
                    <div class="cs-card__nombre">{{ $caso['nombre'] }}</div>
                    @if ($caso['ubicacion'])
                        <div class="cs-card__sub">{{ $caso['ubicacion'] }}</div>
                    @endif
                </div>
            @endif
        </div>
        <span class="cs-sector cs-sector--{{ $sector['tono'] }}">{{ $sector['label'] }}</span>
    </div>

    <div class="cs-card__cuerpo">
        <h2 class="cs-card__titulo"><a href="{{ $urlCaso }}">{{ $caso['titulo'] }}</a></h2>

        @if ($estrella)
            <div class="cs-cifra">
                <div class="cs-cifra__valor">{{ $estrella['valor'] }}</div>
                <div class="cs-cifra__label">{{ $estrella['label'] }}</div>
                <div class="cs-fuente">Fuente · {{ $fuentes[$estrella['fuente']] ?? $estrella['fuente'] }}</div>
            </div>
        @endif

        <ul class="cs-card__tags" aria-label="Servicios y duración">
            @foreach ($caso['servicios'] as $s)
                <li class="cs-tag">{{ $servicios[$s] ?? $s }}</li>
            @endforeach
            @if ($caso['duracion'])
                <li class="cs-tag">{{ $caso['duracion'] }}</li>
            @endif
        </ul>

        <span class="cs-enlace" aria-hidden="true">Ver el caso completo →</span>
    </div>
</article>
