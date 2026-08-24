@extends('layouts.app')

@section('title', 'RankPro · Agencia de Marketing Digital en México')

@section('description', 'RankPro es la agencia de marketing digital en México experta en SEO, Google Ads y desarrollo web. Estrategias medibles que aumentan tus ventas y leads.')

@section('canonical', url('/'))

@php
    /*
     * Contenido propio de la portada. Sustituye a los testimonios y a las
     * cifras que se retiraron en la revisión de E-E-A-T: aquí solo hay
     * material verificable sobre cómo trabajamos, con quién encajamos y qué
     * entregamos. No se añade ningún dato de desempeño ni schema FAQPage:
     * las preguntas de abajo son distintas a las de cada página de servicio
     * para no duplicar contenido entre URLs.
     */
    $proceso = [
        [
            'titulo' => 'Diagnóstico',
            'desc' => 'Antes de proponer nada revisamos lo que ya tienes: estado técnico del sitio, cómo está configurada la medición, qué campañas corren hoy y qué palabras clave te traen tráfico. También preguntamos por el negocio, no solo por el sitio: qué servicios dejan mejor margen, cuál es el ticket promedio, cuánto tarda una venta en cerrarse y qué zonas puedes atender de verdad. Sin esa parte, cualquier estrategia es una plantilla.',
        ],
        [
            'titulo' => 'Propuesta',
            'desc' => 'Del diagnóstico sale un documento con hallazgos priorizados por impacto y esfuerzo, el canal o la combinación de canales que tiene sentido para tu caso, el alcance concreto de los primeros meses y qué vamos a considerar un resultado. Si creemos que el canal que nos pediste no es el adecuado, lo decimos en esta etapa y explicamos por qué, aunque implique un proyecto más chico.',
        ],
        [
            'titulo' => 'Implementación',
            'desc' => 'Ejecutamos por bloques y en un orden defendible: primero se destraba lo técnico que impide avanzar, después se construye la estructura —arquitectura de campañas, arquitectura de contenido o el sitio mismo— y al final se afinan los detalles. Trabajamos sobre tus propias cuentas de Google Ads, Analytics y Search Console, con accesos a tu nombre, para que nada de lo que se construye quede secuestrado en nuestra agencia.',
        ],
        [
            'titulo' => 'Medición',
            'desc' => 'La medición se define antes de lanzar, no después. Se acuerda qué cuenta como conversión, cuáles son primarias y cuáles secundarias, y se configura en GA4 y en la plataforma de anuncios con Google Tag Manager. Cada mes recibes un reporte con lo que se hizo, lo que se movió y lo que sigue, escrito para que se entienda sin ser especialista y con los números que puedes verificar tú mismo en tus propias cuentas.',
        ],
        [
            'titulo' => 'Iteración',
            'desc' => 'Ningún plan sobrevive intacto al contacto con los datos reales. Revisamos qué hipótesis se sostuvieron y cuáles no, ajustamos por bloques —no todo a la vez— y dejamos correr cada cambio el tiempo suficiente para que los datos signifiquen algo. Cuando algo no funciona, lo decimos en el reporte del mes en curso y proponemos la alternativa, en lugar de esperar a la renovación del contrato.',
        ],
    ];

    $paraQuienSi = [
        'Tienes un negocio en operación, con clientes y ventas, y quieres crecer el canal digital de forma sostenida.',
        'Puedes sostener una inversión mensual estable durante varios meses seguidos, no una prueba de un mes.',
        'Alguien de tu equipo puede darnos accesos, resolver dudas del negocio y aprobar cambios en tiempos razonables.',
        'Te interesa entender qué se está haciendo y por qué, no solo recibir un reporte al final del mes.',
        'Aceptas que el trabajo se juzgue por conversiones y ventas, no por métricas de vanidad como impresiones o seguidores.',
    ];

    $paraQuienNo = [
        'Buscas resultados de SEO en 30 días: ese no es el plazo del canal, y te lo diremos en la primera llamada en lugar de venderte el proyecto.',
        'Quieres una garantía de primera posición en Google o un costo por conversión fijo por contrato. Nadie serio puede darlo.',
        'Tu producto todavía no tiene demanda de búsqueda y necesitas educar al mercado primero: ahí conviene contenido o redes, no anuncios de búsqueda.',
        'El margen por venta no soporta el costo por clic de tu sector. Preferimos decírtelo antes de firmar.',
        'Necesitas resolver un pico puntual y no habrá nadie que dé continuidad al trabajo después.',
    ];

    $entregables = [
        'Auditoría inicial con hallazgos priorizados por impacto y esfuerzo.',
        'Investigación de palabras clave clasificada por intención de búsqueda.',
        'Configuración de conversiones en GA4 y en la plataforma de anuncios, con Google Tag Manager.',
        'Accesos y propiedad de todas las cuentas a nombre de tu empresa.',
        'Optimización recurrente documentada: qué se tocó, cuándo y con qué criterio.',
        'Reporte mensual con lo hecho, lo medido y los siguientes pasos.',
    ];

    $faqs = [
        [
            'p' => '¿Con qué tamaño de empresa trabajan?',
            'r' => 'Trabajamos sobre todo con pymes mexicanas que ya facturan y quieren ordenar y escalar su canal digital: negocios locales con zona de servicio definida, comercio electrónico con catálogo estable y empresas B2B cuyo ciclo de venta arranca con una solicitud de cotización. No es cuestión de tamaño sino de madurez: si el negocio todavía no tiene una oferta clara ni forma de atender la demanda que generemos, el marketing adelanta un problema en lugar de resolverlo.',
        ],
        [
            'p' => '¿Qué necesitan de nuestro lado para empezar?',
            'r' => 'Accesos de administrador a tu sitio, a Google Analytics, Search Console y las cuentas de anuncios que ya existan; si no existen, las creamos a tu nombre. Además necesitamos una persona de contacto que pueda responder preguntas del negocio y aprobar cambios, y la información comercial básica: márgenes por línea, ticket promedio, zonas que atiendes y capacidad real de atención. Nada de eso se comparte fuera del proyecto.',
        ],
        [
            'p' => '¿Cómo se mide si el trabajo está funcionando?',
            'r' => 'Con conversiones definidas y acordadas antes de arrancar, medidas en tus propias cuentas de GA4, Google Ads y Search Console. Eso significa que puedes abrir la plataforma y verificar cualquier número del reporte sin depender de nosotros. Las métricas intermedias —posiciones, impresiones, clics, costo por clic— sirven para diagnosticar, pero el criterio de éxito es el volumen y el costo de los contactos o ventas que llegan.',
        ],
        [
            'p' => '¿Hay permanencia mínima o puedo cancelar cuando quiera?',
            'r' => 'Trabajamos con periodos mínimos por proyecto porque hay canales que no producen nada evaluable en cuatro semanas, y comprometer un plazo también nos obliga a nosotros. Ese plazo se pacta por escrito antes de firmar y se explica de dónde sale. Al terminar el periodo puedes continuar, pausar o llevarte todo, porque las cuentas, el sitio y los datos ya están a tu nombre desde el primer día.',
        ],
        [
            'p' => '¿Qué pasa si la estrategia no está dando los resultados esperados?',
            'r' => 'Se dice en el reporte del mes en que se detecta, no al final del contrato. Revisamos qué hipótesis falló —oferta, canal, segmentación, página de destino o medición—, presentamos la evidencia y proponemos el ajuste. Si el diagnóstico es que el canal simplemente no es el adecuado para tu negocio, te lo planteamos aunque implique reducir o terminar el proyecto: sostener una cuenta que no funciona sale más caro para los dos.',
        ],
        [
            'p' => '¿Trabajan con empresas fuera de la Ciudad de México?',
            'r' => 'Sí. Operamos de forma remota con clientes de toda la República y las juntas se hacen por videollamada, con la documentación y los reportes en línea. Para negocios locales la ubicación sí importa en la estrategia —afecta la segmentación geográfica, el SEO local y la ficha de Google Business Profile—, pero no condiciona que podamos trabajar contigo.',
        ],
    ];
@endphp

@section('content')
    @include('components.topbar')
    @include('components.navbar')

    <main>
        @include('components.hero')
        @include('components.partners')
        @include('components.services')

        <section class="process" id="metodologia" aria-labelledby="metodologia-titulo">
            <div class="container">
                <div class="section-header">
                    <div class="section-badge">METODOLOGÍA</div>
                    <h2 id="metodologia-titulo">Cómo <span class="text-brand">trabajamos</span></h2>
                    <p>El mismo proceso para cualquier servicio: entender antes de proponer, ejecutar por bloques y medir en tus propias cuentas.</p>
                </div>

                <ol class="process__grid">
                    @foreach ($proceso as $i => $paso)
                        <li class="process-step">
                            <div class="process-step__number gradient-{{ $i + 1 }}" aria-hidden="true">{{ $i + 1 }}</div>
                            <h3 class="process-step__title">{{ $paso['titulo'] }}</h3>
                            <p class="process-step__desc">{{ $paso['desc'] }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        <section class="fit" id="encaje" aria-labelledby="encaje-titulo">
            <div class="container">
                <div class="section-header">
                    <div class="section-badge">ANTES DE CONTRATAR</div>
                    <h2 id="encaje-titulo">Para quién <span class="text-brand">es</span> y para quién <span class="text-brand">no</span></h2>
                    <p>Preferimos filtrar en la primera llamada y no en el tercer mes. Si tu caso está en la columna de la derecha, te lo diremos.</p>
                </div>

                <div class="fit__grid">
                    <div class="fit-panel fit-panel--yes">
                        <h3 class="fit-panel__title">Encajamos bien si…</h3>
                        <ul class="fit-panel__list">
                            @foreach ($paraQuienSi as $item)
                                <li class="fit-panel__item">
                                    <svg class="fit-panel__icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 6 9 17l-5-5"></path></svg>
                                    <span>{{ $item }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="fit-panel fit-panel--no">
                        <h3 class="fit-panel__title">No somos tu agencia si…</h3>
                        <ul class="fit-panel__list">
                            @foreach ($paraQuienNo as $item)
                                <li class="fit-panel__item">
                                    <svg class="fit-panel__icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                                    <span>{{ $item }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <div class="fit__deliverables">
                    <h3 class="fit__deliverables-title">Qué incluye trabajar con nosotros</h3>
                    <ul class="fit__deliverables-list">
                        @foreach ($entregables as $item)
                            <li class="fit-panel__item">
                                <svg class="fit-panel__icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 6 9 17l-5-5"></path></svg>
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </section>

        <section class="home-faq" id="preguntas-frecuentes" aria-labelledby="faq-titulo">
            <div class="container">
                <div class="section-header">
                    <div class="section-badge">PREGUNTAS FRECUENTES</div>
                    <h2 id="faq-titulo">Lo que nos preguntan <span class="text-brand">antes de empezar</span></h2>
                    <p>Dudas sobre cómo funciona la relación con la agencia. Las preguntas propias de cada servicio están en su página correspondiente.</p>
                </div>

                <div class="home-faq__list">
                    @foreach ($faqs as $faq)
                        <article class="home-faq__item">
                            <h3 class="home-faq__question">{{ $faq['p'] }}</h3>
                            <p class="home-faq__answer">{{ $faq['r'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    </main>

    @include('components.footer')
@endsection
