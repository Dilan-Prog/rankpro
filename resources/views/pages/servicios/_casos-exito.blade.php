{{--
    Teaser de casos de exito para las landings de servicio. Los datos viven en
    App\Support\CasosExito (misma forma que el listado /casos-de-exito); aqui
    solo se pintan y cada tarjeta enlaza a su caso completo.

    Reglas que esta vista aplica y que no hay que romper al editar:
      - La cifra estrella es kpis[0] y va siempre con su fuente (FUENTES).
      - `nombre`/`url` nulos = sin consentimiento para nombrar: la tarjeta
        muestra "Cliente anonimizado" y no aparece ningun nombre ni enlace.
      - Nada de enlaces salientes aqui: el unico destino es el caso en el sitio.
        El enlace al cliente, con rel="noopener", vive en la pagina del caso.
--}}
@php
    $casosTeaser = \App\Support\CasosExito::todos();
    $sectoresTeaser = \App\Support\CasosExito::SECTORES;
    $fuentesTeaser = \App\Support\CasosExito::FUENTES;
    $totalTeaser = count($casosTeaser);
@endphp

<section class="cv-section cv-section--alt cv-casos" aria-labelledby="casos-titulo">
    <div class="container">
        <div class="cv-casos__head">
            <div>
                <span class="cv-badge">Casos de éxito</span>
                <h2 id="casos-titulo" class="cv-casos__title">Lo que este servicio ya logró</h2>
                <p class="cv-casos__lead">Cifras de Google Search Console y de los propios clientes, cada una con su fuente. Donde hay nombre es porque el cliente autorizó publicarlo.</p>
            </div>
            <a class="cv-casos__todos" href="{{ route('casos.index') }}">Ver todos los casos →</a>
        </div>

        <div class="cv-casos__grid">
            @foreach ($casosTeaser as $caso)
                @php
                    $sector = $sectoresTeaser[$caso['sector']];
                    $estrella = $caso['kpis'][0] ?? null;
                    $anonimo = $caso['nombre'] === null || $caso['url'] === null;
                @endphp
                <article class="cv-caso">
                    <div class="cv-caso__top">
                        <span class="cv-caso__sector cv-caso__sector--{{ $sector['tono'] }}">{{ $sector['label'] }}</span>
                        <span class="cv-caso__nombre">{{ $anonimo ? 'Cliente anonimizado' : $caso['nombre'] }}</span>
                    </div>

                    @if ($estrella)
                        <div class="cv-caso__estrella">
                            <div class="cv-caso__estrella-valor">{{ $estrella['valor'] }}</div>
                            <div class="cv-caso__estrella-label">{{ $estrella['label'] }}</div>
                            <div class="cv-caso__fuente">Fuente · {{ $fuentesTeaser[$estrella['fuente']] ?? $estrella['fuente'] }}</div>
                        </div>
                    @endif

                    <h3 class="cv-caso__titulo"><a href="{{ route('casos.show', $caso['slug']) }}">{{ $caso['titulo'] }}</a></h3>
                    <p class="cv-caso__desc">{{ $caso['resumen'] }}</p>

                    <span class="cv-caso__link" aria-hidden="true">Ver el caso completo →</span>
                </article>
            @endforeach
        </div>
    </div>
</section>
