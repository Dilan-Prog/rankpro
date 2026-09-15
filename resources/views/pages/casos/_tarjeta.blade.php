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
                <span class="cs-logo cs-logo--anon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 20a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8l-7 5V8l-7 5V4a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M17 18h1"/><path d="M12 18h1"/><path d="M7 18h1"/></svg></span>
                <div>
                    <div class="cs-card__nombre">Cliente anonimizado</div>
                    <div class="cs-card__sub">Publicado por sector</div>
                </div>
            @else
                @if (!empty($caso['logo']))
                    {{-- El logo del cliente es blanco: va sobre el verde, sin caja. Si no
                         carga, se muestra el recuadro de iniciales que va detras. --}}
                    <img class="cs-logo cs-logo--img" src="{{ $caso['logo'] }}" alt="" width="125" height="84" loading="lazy" onerror="this.hidden=true;this.nextElementSibling.hidden=false">
                    <span class="cs-logo" aria-hidden="true" hidden>{{ $caso['iniciales'] }}</span>
                @else
                    <span class="cs-logo" aria-hidden="true">{{ $caso['iniciales'] }}</span>
                @endif
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
