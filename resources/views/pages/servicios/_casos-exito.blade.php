{{--
    Sección de casos de éxito con datos reales. Los datos viven en
    App\Support\CasosExito; aquí solo se pintan.

    Reglas que esta vista aplica y que no hay que romper al editar:
      - `url` nulo = sin consentimiento para nombrar: se muestra el sector y
        no aparece ningún nombre ni enlace.
      - Una métrica con `valor` nulo no se imprime. Es la manera de no publicar
        una cifra que todavía no se tiene, sin tener que borrar su hueco.
      - Los enlaces a sitios de clientes llevan rel="noopener" y se abren en
        pestaña nueva: sacan al visitante de la landing.
--}}
@php
    $casos = \App\Support\CasosExito::todos();
    $sectores = \App\Support\CasosExito::SECTORES;
    $servicios = \App\Support\CasosExito::SERVICIOS;
    $icoQuote = '<path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z"/>';
@endphp

<section class="cv-section cv-section--alt cv-casos" aria-labelledby="casos-titulo">
    <div class="container">
        <div class="cv-casos__head">
            <span class="cv-badge">Casos reales</span>
            <h2 id="casos-titulo" class="cv-casos__title">Lo que ha pasado en negocios como el tuyo</h2>
            <p class="cv-casos__lead">Cifras de Google Search Console y de los propios clientes. Donde hay nombre y enlace, es porque el cliente autorizó publicarlo.</p>
        </div>

        <div class="cv-casos__grid">
            @foreach ($casos as $caso)
                @php
                    $sector = $sectores[$caso['sector']];
                    $metricas = array_filter($caso['metricas'], fn ($m) => $m['valor'] !== null && $m['valor'] !== '');
                @endphp
                <article class="cv-caso" id="caso-{{ $caso['id'] }}">
                    <div class="cv-caso__top">
                        <span class="cv-caso__sector cv-caso__sector--{{ $sector['tono'] }}">{{ $sector['label'] }}</span>
                        @if ($caso['nombre'] && $caso['url'])
                            <a class="cv-caso__nombre" href="{{ $caso['url'] }}" target="_blank" rel="noopener">{{ $caso['nombre'] }}</a>
                        @endif
                    </div>

                    <h3 class="cv-caso__titulo">{{ $caso['titulo'] }}</h3>

                    <div class="cv-caso__estrella">
                        <div class="cv-caso__estrella-valor">{{ $caso['estrella']['valor'] }}</div>
                        <div class="cv-caso__estrella-label">{{ $caso['estrella']['label'] }}</div>
                    </div>

                    @if ($metricas)
                        <ul class="cv-caso__metricas">
                            @foreach ($metricas as $m)
                                <li>
                                    <strong>{{ $m['valor'] }}</strong>
                                    <span>{{ $m['label'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <p class="cv-caso__desc">{{ $caso['descripcion'] }}</p>

                    @if ($caso['cita'])
                        <blockquote class="cv-caso__cita">
                            {!! $ico($icoQuote, 14) !!}
                            <p>{{ $caso['cita'] }}</p>
                        </blockquote>
                    @endif

                    <ul class="cv-caso__tags" aria-label="Servicios contratados">
                        @foreach ($caso['servicios'] as $s)
                            <li class="cv-caso__tag">{{ $servicios[$s] ?? $s }}</li>
                        @endforeach
                    </ul>
                </article>
            @endforeach
        </div>
    </div>
</section>
