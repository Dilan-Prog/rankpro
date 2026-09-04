@extends('layouts.app')

@section('title', 'Sobre RankPro | Agencia de Marketing Digital en México')
@section('description', 'Quiénes somos y cómo trabajamos en RankPro: agencia de marketing digital en México enfocada en medición, transparencia y resultados verificables.')
@section('canonical', route('nosotros'))

@push('styles')
    @vite('resources/css/web/pages.css')
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
            </div>
        </section>

        <section class="page-section">
            <div class="container">
                <div class="page-section__inner prose">
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
                        Hoy acumulamos <strong>4 años de experiencia en México</strong>, hemos trabajado con
                        <strong>más de 200 clientes</strong> y sostenemos una <strong>tasa de retención del 98%</strong>,
                        con <strong>más de $120M en ventas generadas</strong> para los negocios que atendemos.
                        Esas cifras nos importan menos por lo que dicen de nosotros que por lo que
                        implican: que la mayoría de nuestros clientes se quedan porque el trabajo se
                        sostiene en el tiempo.
                    </p>

                    <h2>Cómo trabajamos</h2>
                    <p>
                        Nuestro método no tiene nada de espectacular y esa es la intención. Es un ciclo
                        repetible que se aplica igual a una campaña de Google Ads que a un proyecto de
                        posicionamiento orgánico.
                    </p>

                    <h3>1. Diagnóstico antes de proponer</h3>
                    <p>
                        Nunca cotizamos sin haber revisado tu situación real: qué vendes, con qué margen,
                        a quién, qué has intentado antes y qué está pasando hoy en tus cuentas de
                        analítica y publicidad. Muchas veces ese diagnóstico revela que el problema no es
                        el que creías —y a veces que el servicio que venías a contratar no es el que
                        necesitas. Lo decimos aunque nos convenga menos.
                    </p>

                    <h3>2. Plan con alcance escrito</h3>
                    <p>
                        La propuesta detalla entregables, tiempos y qué indicadores vamos a mover. Sin
                        partidas vagas del tipo "gestión mensual". Si algo no está en el documento, no
                        está contratado, y si hace falta ampliarlo lo conversamos antes, no en la
                        factura.
                    </p>

                    <h3>3. Medición configurada desde el día uno</h3>
                    <p>
                        Antes de lanzar cualquier campaña verificamos que las conversiones estén bien
                        definidas, sin duplicados y con el tráfico interno filtrado. Optimizar sobre datos
                        sucios es peor que no optimizar, porque produce decisiones equivocadas con toda
                        la confianza del mundo.
                    </p>

                    <h3>4. Ejecución y ajuste continuo</h3>
                    <p>
                        El trabajo real está en el mantenimiento semanal: revisar términos de búsqueda,
                        ajustar pujas, probar creatividades, publicar y actualizar contenido, corregir lo
                        técnico. Los cambios se hacen por bloques y se dejan correr el tiempo suficiente
                        para que los datos signifiquen algo, no cada 48 horas por ansiedad.
                    </p>

                    <h3>5. Reporte que se entiende</h3>
                    <p>
                        Cada mes recibes un reporte con lo que se hizo, qué resultados dio, qué no
                        funcionó y qué sigue. En el idioma del negocio, no en jerga. Y con acceso directo
                        a las plataformas para que puedas verificar cada cifra por tu cuenta cuando
                        quieras.
                    </p>
                </div>
            </div>
        </section>

        <section class="page-section page-section--alt" aria-labelledby="valores">
            <div class="container">
                <div class="page-section__inner prose">
                    <h2 id="valores">En qué creemos</h2>
                    <p>
                        Estos principios son los que aplicamos cuando nadie está viendo, que es cuando de
                        verdad cuentan.
                    </p>
                </div>

                <ul class="value-grid" style="margin-top: 2rem;">
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
                <div class="page-section__inner prose">
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
                        <a href="https://wa.me/527341036410" class="btn btn-ghost" rel="noopener nofollow">WhatsApp +52 734 103 6410</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    @include('components.footer')
@endsection
