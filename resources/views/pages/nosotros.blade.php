@extends('layouts.app')

@section('title', 'Sobre RankPro | Agencia de Marketing Digital en México')
@section('description', 'Quiénes somos y cómo trabajamos en RankPro: agencia de marketing digital en México enfocada en medición, transparencia y resultados verificables.')
@section('canonical', route('nosotros'))
@section('body_class', 'rd rd-nosotros')

@push('styles')
    @vite(['resources/css/web/pages.css', 'resources/css/web/rediseno/base.css', 'resources/css/web/rediseno/nosotros.css'])
@endpush

@push('jsonld')
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'AboutPage',
                    'name' => 'Sobre RankPro',
                    'url' => route('nosotros'),
                    'description' => 'Historia, enfoque de trabajo y valores de RankPro, agencia de marketing digital en México.',
                    'about' => ['@id' => url('/#organization')],
                ],
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => url('/')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Nosotros', 'item' => route('nosotros')],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endpush

@section('content')
    @include('components.topbar')
    @include('components.navbar')

    <main>
        <section class="page-hero">
            <div class="container">
                <nav class="breadcrumb" aria-label="Ruta de navegación">
                    <ol>
                        <li><a href="{{ url('/') }}">Inicio</a></li>
                        <li aria-hidden="true" class="breadcrumb__sep">/</li>
                        <li><span aria-current="page">Nosotros</span></li>
                    </ol>
                </nav>

                <div class="page-hero__dos">
                <div class="page-hero__inner">
                    <h1>Somos RankPro, una agencia digital que trabaja con datos a la vista</h1>
                    <p class="page-hero__lead">
                        Ayudamos a empresas mexicanas a crecer con marketing digital medible: publicidad
                        en buscadores, posicionamiento orgánico, desarrollo web y analítica. Nuestro
                        compromiso no es con las métricas que se ven bonitas en una presentación, sino
                        con las que mueven el negocio.
                    </p>
                    <div class="page-hero__actions">
                        <a href="{{ route('contacto') }}" class="btn btn-primary">Hablemos de tu proyecto</a>
                        <a href="{{ route('servicios.index') }}" class="btn btn-outline">Ver servicios</a>
                    </div>
                </div>
                {{-- Adorno sin texto ni cifras, para no aparentar datos reales. --}}
                <div class="page-hero__adorno" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 520 400" focusable="false">
                        <rect x="1" y="1" width="518" height="398" rx="24" fill="#fff" stroke="#E3E9E6"/>
                        <rect x="26" y="26" width="132" height="12" rx="6" fill="#0E1A15"/><rect x="26" y="47" width="86" height="8" rx="4" fill="#C9D3CE"/>
                        <rect x="402" y="26" width="92" height="28" rx="14" fill="#E6F4EE"/><rect x="420" y="36" width="56" height="8" rx="4" fill="#0B7D58"/>
                        <path d="M1 76H519" stroke="#EEF2F0"/>
                        <rect x="26" y="98" width="290" height="168" rx="16" fill="#F4F7F5"/>
                        <g fill="#C9D3CE"><rect x="46" y="122" width="52" height="8" rx="4"/><rect x="46" y="156" width="40" height="8" rx="4"/><rect x="46" y="190" width="58" height="8" rx="4"/><rect x="46" y="224" width="46" height="8" rx="4"/></g>
                        <g fill="#E3E9E6"><rect x="122" y="120" width="172" height="12" rx="6"/><rect x="122" y="154" width="172" height="12" rx="6"/><rect x="122" y="188" width="172" height="12" rx="6"/><rect x="122" y="222" width="172" height="12" rx="6"/></g>
                        <rect x="122" y="120" width="138" height="12" rx="6" fill="#0B7D58"/><rect x="122" y="154" width="104" height="12" rx="6" fill="#0F9D6E"/><rect x="122" y="188" width="72" height="12" rx="6" fill="#4FD1A5"/><rect x="122" y="222" width="46" height="12" rx="6" fill="#0E1A15"/>
                        <rect x="332" y="98" width="162" height="168" rx="16" fill="#0B1712"/>
                        <path d="M362 182C378 154 396 144 413 144S448 154 464 182C448 210 430 220 413 220S378 210 362 182Z" fill="none" stroke="#4FD1A5" stroke-width="5" stroke-linejoin="round"/>
                        <circle cx="413" cy="182" r="17" fill="#4FD1A5"/><circle cx="413" cy="182" r="6" fill="#0B1712"/>
                        <g fill="none" stroke="#E3E9E6"><rect x="26" y="284" width="227" height="38" rx="12"/><rect x="267" y="284" width="227" height="38" rx="12"/><rect x="26" y="336" width="227" height="38" rx="12"/><rect x="267" y="336" width="227" height="38" rx="12"/></g>
                        <g fill="#0F9D6E"><circle cx="47" cy="303" r="6"/><circle cx="288" cy="303" r="6"/><circle cx="288" cy="355" r="6"/></g><circle cx="47" cy="355" r="6" fill="#4FD1A5"/>
                        <g fill="#C9D3CE"><rect x="64" y="299" width="110" height="8" rx="4"/><rect x="305" y="299" width="84" height="8" rx="4"/><rect x="64" y="351" width="92" height="8" rx="4"/><rect x="305" y="351" width="122" height="8" rx="4"/></g>
                    </svg>
                </div>
                </div>
            </div>
        </section>

        <section class="page-section">
            <div class="container">
                <div class="prose nos-historia">
                    <h2>Cómo empezó RankPro</h2>
                    <p>
                        RankPro nació de una frustración común entre empresarios mexicanos: contratar
                        marketing digital y no poder explicar, meses después, qué se hizo con el
                        presupuesto. Reportes llenos de impresiones y alcance, campañas que nadie sabía
                        cómo estaban estructuradas, sitios web entregados sin una sola conversión
                        configurada. El servicio existía; la claridad, no.
                    </p>
                    <p>
                        Con esa idea armamos una agencia que trabaja al revés de esa costumbre: primero
                        se define qué se va a medir, después se ejecuta, y el cliente tiene acceso a las
                        mismas plataformas donde nosotros vemos los números. Si algo no está funcionando,
                        se dice en el mes en que pasa, no en la junta de renovación.
                    </p>
                    <p>
                        Esa forma de trabajar se sostiene en cosas verificables: acceso del cliente a sus
                        propias cuentas, reportes que explican qué se hizo y por qué, y acuerdos claros
                        desde el inicio. Preferimos que la relación continúe porque el trabajo se entiende
                        y se sostiene en el tiempo, no por un contrato que cuesta cancelar.
                    </p>

                </div>
            </div>
        </section>

        <section class="page-section nos-metodo">
            <div class="container">
                <div class="prose nos-metodo__intro">
                    <h2>Cómo trabajamos</h2>
                    <p>
                        Nuestro método no tiene nada de espectacular y esa es la intención. Es un ciclo
                        repetible que se aplica igual a una campaña de Google Ads que a un proyecto de
                        posicionamiento orgánico.
                    </p>

                </div>

                <div class="prose nos-pasos">
                    <div class="nos-paso">
                    <h3><span class="nos-paso__num">1.</span> Diagnóstico antes de proponer</h3>
                    <p>
                        Nunca cotizamos sin haber revisado tu situación real: qué vendes, con qué margen,
                        a quién, qué has intentado antes y qué está pasando hoy en tus cuentas de
                        analítica y publicidad. Muchas veces ese diagnóstico revela que el problema no es
                        el que creías —y a veces que el servicio que venías a contratar no es el que
                        necesitas. Lo decimos aunque nos convenga menos.
                    </p>

                    </div>
                    <div class="nos-paso">
                    <h3><span class="nos-paso__num">2.</span> Plan con alcance escrito</h3>
                    <p>
                        La propuesta detalla entregables, tiempos y qué indicadores vamos a mover. Sin
                        partidas vagas del tipo "gestión mensual". Si algo no está en el documento, no
                        está contratado, y si hace falta ampliarlo lo conversamos antes, no en la
                        factura.
                    </p>

                    </div>
                    <div class="nos-paso">
                    <h3><span class="nos-paso__num">3.</span> Medición configurada desde el día uno</h3>
                    <p>
                        Antes de lanzar cualquier campaña verificamos que las conversiones estén bien
                        definidas, sin duplicados y con el tráfico interno filtrado. Optimizar sobre datos
                        sucios es peor que no optimizar, porque produce decisiones equivocadas con toda
                        la confianza del mundo.
                    </p>

                    </div>
                    <div class="nos-paso">
                    <h3><span class="nos-paso__num">4.</span> Ejecución y ajuste continuo</h3>
                    <p>
                        El trabajo real está en el mantenimiento semanal: revisar términos de búsqueda,
                        ajustar pujas, probar creatividades, publicar y actualizar contenido, corregir lo
                        técnico. Los cambios se hacen por bloques y se dejan correr el tiempo suficiente
                        para que los datos signifiquen algo, no cada 48 horas por ansiedad.
                    </p>

                    </div>
                    <div class="nos-paso">
                    <h3><span class="nos-paso__num">5.</span> Reporte que se entiende</h3>
                    <p>
                        Cada mes recibes un reporte con lo que se hizo, qué resultados dio, qué no
                        funcionó y qué sigue. En el idioma del negocio, no en jerga. Y con acceso directo
                        a las plataformas para que puedas verificar cada cifra por tu cuenta cuando
                        quieras.
                    </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="page-section page-section--alt" aria-labelledby="valores">
            <div class="container">
                <div class="page-section__inner prose nos-valores">
                    <h2 id="valores">En qué creemos</h2>
                    <p>
                        Estos principios son los que aplicamos cuando nadie está viendo, que es cuando de
                        verdad cuentan.
                    </p>
                </div>

                <ul class="value-grid">
                    <li class="value-card">
                        <h3>Las cuentas son del cliente</h3>
                        <p>
                            Google Ads, Analytics, Tag Manager, Search Console, el dominio y el hosting se
                            crean o se mantienen a nombre de tu empresa. Nosotros trabajamos con accesos.
                            Si algún día decides trabajar con alguien más, todo el histórico se queda
                            contigo sin negociaciones incómodas.
                        </p>
                    </li>
                    <li class="value-card">
                        <h3>No prometemos lo que no controlamos</h3>
                        <p>
                            No garantizamos primeras posiciones en Google ni un costo por conversión
                            específico, porque dependen de subastas, competencia y algoritmos que no
                            controla ninguna agencia. Sí garantizamos el trabajo, los entregables y la
                            transparencia de los datos.
                        </p>
                    </li>
                    <li class="value-card">
                        <h3>Nada de atajos que pongan en riesgo tu dominio</h3>
                        <p>
                            No compramos enlaces masivos, no participamos en redes de blogs, no inflamos
                            seguidores ni generamos tráfico artificial. Son tácticas que producen una
                            gráfica bonita durante un trimestre y una penalización después. Preferimos
                            crecer más lento y no tener que explicar una caída.
                        </p>
                    </li>
                    <li class="value-card">
                        <h3>Decir que no cuando corresponde</h3>
                        <p>
                            Hay negocios a los que Google Ads no les conviene, sitios que no necesitan
                            rediseño y proyectos donde el presupuesto rinde más en otro canal. Lo decimos
                            antes de firmar. Un cliente al que le vendimos algo que no necesitaba no
                            renueva, y con razón.
                        </p>
                    </li>
                </ul>
            </div>
        </section>

        <section class="page-section">
            <div class="container">
                <div class="prose nos-doble">
                    <div class="nos-panel">
                    <h2>Cómo está formado el equipo</h2>
                    <p>
                        Trabajamos con especialistas por disciplina en lugar de generalistas que hacen un
                        poco de todo: publicidad de búsqueda, SEO técnico y de contenidos, desarrollo web,
                        diseño y analítica. Cada proyecto tiene una persona responsable que conoce tu
                        cuenta y con la que hablas directamente, sin cadenas de intermediarios que
                        traducen mal lo que pediste.
                    </p>
                    <p>
                        Las plataformas con las que trabajamos —Google Ads, GA4, Tag Manager, Search
                        Console, Meta Ads, WordPress, Shopify y desarrollo a medida— cambian de reglas
                        con frecuencia, así que la actualización constante es parte del trabajo, no un
                        extra. Cuando aparece un cambio relevante para tu cuenta, te lo explicamos y
                        ajustamos, sin esperar a que lo notes en los resultados.
                    </p>

                    </div>
                    <div class="nos-panel">
                    <h2>Con qué tipo de negocios trabajamos</h2>
                    <p>
                        Atendemos empresas en toda la República Mexicana, presencialmente en nuestra zona
                        y en remoto para el resto del país. Nos acomodamos especialmente bien con
                        negocios que ya tienen operación y quieren crecer con orden: comercio local con
                        zona de servicio definida, comercio electrónico, empresas B2B con ciclo de venta
                        por cotización y marcas de consumo que necesitan construir presencia.
                    </p>
                    <p>
                        Somos peor opción para quien busca resultados garantizados en dos semanas o para
                        proyectos donde el presupuesto no alcanza a generar suficientes datos como para
                        optimizar. En esos casos preferimos decirlo de entrada y, si podemos, orientarte
                        hacia lo que sí tiene sentido en tu etapa.
                    </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="page-section page-section--alt">
            <div class="container">
                <div class="page-cta">
                    <h2>¿Platicamos?</h2>
                    <p>
                        Una conversación inicial sin costo para entender tu negocio y decirte con
                        honestidad si podemos ayudarte y cómo.
                    </p>
                    <div class="page-cta__actions">
                        <a href="{{ route('contacto') }}" class="btn btn-light">Ir a contacto</a>
                        <a href="https://wa.me/525562230266" class="btn btn-ghost" rel="noopener nofollow">WhatsApp +52 55 6223 0266</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    @include('components.footer')
@endsection
