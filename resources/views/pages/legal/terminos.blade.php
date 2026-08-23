{{--
    ============================================================================
    PLANTILLA — debe ser revisada por un abogado antes de publicarse; los datos
    del responsable, domicilio y ARCO deben completarse.
    ============================================================================
    Los marcadores [COMPLETAR: ...] señalan la información contractual que debe
    definirse (razón social, domicilio, jurisdicción, plazos y penalizaciones).
--}}
@extends('layouts.app')

@section('title', 'Términos y Condiciones | RankPro')
@section('description', 'Términos y condiciones de uso del sitio web y de contratación de los servicios de marketing digital de RankPro en México.')
@section('canonical', route('legal.terminos'))

@push('styles')
    @vite('resources/css/web/pages.css')
@endpush

@push('jsonld')
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => url('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Términos y Condiciones', 'item' => route('legal.terminos')],
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
                        <li><span aria-current="page">Términos y Condiciones</span></li>
                    </ol>
                </nav>
                <div class="page-hero__inner">
                    <h1>Términos y Condiciones</h1>
                    <p class="page-hero__lead">
                        Condiciones que rigen el uso de este sitio web y la contratación de los servicios de
                        marketing digital de RankPro.
                    </p>
                </div>
            </div>
        </section>

        <section class="page-section">
            <div class="container">
                <div class="page-section__inner prose">
                    <p class="prose__updated">Última actualización: {{ now()->translatedFormat('d \d\e F \d\e Y') }}</p>

                    <div class="legal-toc">
                        <h2>Contenido</h2>
                        <ol>
                            <li><a href="#objeto">Objeto y aceptación</a></li>
                            <li><a href="#servicios-tyc">Descripción de los servicios</a></li>
                            <li><a href="#contratacion">Contratación, vigencia y renovación</a></li>
                            <li><a href="#precios">Precios, pagos y facturación</a></li>
                            <li><a href="#obligaciones">Obligaciones del cliente</a></li>
                            <li><a href="#resultados">Alcance de resultados y limitación de responsabilidad</a></li>
                            <li><a href="#propiedad">Propiedad intelectual y accesos</a></li>
                            <li><a href="#confidencialidad">Confidencialidad y datos personales</a></li>
                            <li><a href="#terminacion">Terminación</a></li>
                            <li><a href="#jurisdiccion">Legislación y jurisdicción</a></li>
                        </ol>
                    </div>

                    <h2 id="objeto">1. Objeto y aceptación</h2>
                    <p>
                        Los presentes términos y condiciones regulan el acceso y uso del sitio web
                        rankprosolutions.com.mx, así como las condiciones generales de contratación de los
                        servicios ofrecidos por <strong>[COMPLETAR: razón social completa]</strong>, con
                        domicilio en <strong>[COMPLETAR: domicilio fiscal completo en México]</strong>, en lo
                        sucesivo “RankPro”.
                    </p>
                    <p>
                        El uso de este sitio implica la aceptación plena de estos términos. Si no estás de
                        acuerdo con alguna de sus disposiciones, te pedimos abstenerte de utilizarlo. La
                        contratación de servicios se rige además por la propuesta o contrato específico
                        firmado entre las partes, que prevalecerá sobre este documento en caso de conflicto.
                    </p>

                    <h2 id="servicios-tyc">2. Descripción de los servicios</h2>
                    <p>
                        RankPro presta servicios profesionales de marketing digital que incluyen, de manera
                        enunciativa y no limitativa: gestión de campañas de publicidad en buscadores y redes
                        sociales, posicionamiento orgánico en buscadores, desarrollo y mantenimiento de sitios
                        web, optimización de rendimiento e implementación de analítica digital.
                    </p>
                    <p>
                        El alcance específico de cada servicio, sus entregables y plazos se definen por escrito
                        en la propuesta comercial aceptada por el cliente. Cualquier actividad no descrita en
                        dicha propuesta se considera fuera de alcance y podrá cotizarse por separado.
                    </p>

                    <h2 id="contratacion">3. Contratación, vigencia y renovación</h2>
                    <p>
                        La relación comercial inicia con la aceptación por escrito de la propuesta y, cuando
                        aplique, con el pago del anticipo correspondiente. La vigencia, la periodicidad y las
                        condiciones de renovación se establecen en cada propuesta.
                    </p>
                    <p><strong>[COMPLETAR: plazos mínimos de contratación, condiciones de renovación automática y plazo de aviso para no renovar.]</strong></p>

                    <h2 id="precios">4. Precios, pagos y facturación</h2>
                    <p>
                        Los precios se expresan en pesos mexicanos y, salvo indicación en contrario, no
                        incluyen el Impuesto al Valor Agregado, que se añadirá conforme a la legislación
                        fiscal vigente. RankPro emitirá el comprobante fiscal digital correspondiente con los
                        datos de facturación proporcionados por el cliente.
                    </p>
                    <p>
                        <strong>La inversión publicitaria en plataformas de terceros</strong>, como Google Ads
                        o Meta Ads, es independiente de los honorarios de RankPro y se paga directamente a
                        dichas plataformas desde las cuentas del cliente, salvo acuerdo distinto por escrito.
                    </p>
                    <p>
                        El retraso en los pagos podrá dar lugar a la suspensión temporal de los servicios,
                        previo aviso al cliente. <strong>[COMPLETAR: plazos de pago, intereses moratorios y
                        política de suspensión.]</strong>
                    </p>

                    <h2 id="obligaciones">5. Obligaciones del cliente</h2>
                    <p>Para que RankPro pueda prestar los servicios contratados, el cliente se obliga a:</p>
                    <ul>
                        <li>Proporcionar en tiempo la información, materiales, accesos y aprobaciones necesarios.</li>
                        <li>Garantizar que los contenidos, marcas e imágenes que entregue son de su propiedad o cuenta con licencia para usarlos.</li>
                        <li>Asegurar que la información sobre sus productos, precios y promociones es veraz y cumple la legislación aplicable en materia de publicidad y protección al consumidor.</li>
                        <li>Designar a una persona con capacidad de decisión como contacto principal del proyecto.</li>
                        <li>Mantener vigentes los servicios de terceros necesarios, como dominio, hosting y cuentas publicitarias.</li>
                    </ul>
                    <p>
                        Los retrasos atribuibles a la falta de información, materiales o aprobaciones del
                        cliente no serán imputables a RankPro y podrán ajustar los plazos comprometidos.
                    </p>

                    <h2 id="resultados">6. Alcance de resultados y limitación de responsabilidad</h2>
                    <p>
                        Los servicios de marketing digital dependen de factores externos que ninguna agencia
                        controla, entre ellos los algoritmos y políticas de buscadores y redes sociales, el
                        comportamiento de la competencia, la estacionalidad y las condiciones del mercado.
                        En consecuencia, <strong>RankPro presta una obligación de medios y no de resultados</strong>:
                        no garantiza posiciones específicas en buscadores, volúmenes de ventas, costos por
                        conversión determinados ni retornos de inversión concretos.
                    </p>
                    <p>
                        RankPro no será responsable por suspensiones, rechazos o sanciones aplicadas por
                        plataformas de terceros derivadas del incumplimiento de sus políticas por parte del
                        cliente o de la naturaleza de sus productos o servicios, ni por caídas, pérdidas de
                        datos o fallas atribuibles a proveedores de hosting, dominio o servicios externos.
                    </p>
                    <p><strong>[COMPLETAR: límite máximo de responsabilidad económica, generalmente referido a los honorarios pagados en un periodo determinado.]</strong></p>

                    <h2 id="propiedad">7. Propiedad intelectual y accesos</h2>
                    <p>
                        Los materiales entregados y aprobados, una vez cubierto el pago total correspondiente,
                        son propiedad del cliente para su uso comercial. Las metodologías, plantillas,
                        herramientas internas y desarrollos previos de RankPro permanecen como propiedad de
                        RankPro.
                    </p>
                    <p>
                        Las cuentas de Google Ads, Google Analytics, Google Tag Manager, Search Console,
                        redes sociales, dominio y hosting se crean o se mantienen a nombre del cliente. RankPro
                        opera con accesos delegados, que serán revocados al término de la relación sin que ello
                        afecte la propiedad del cliente sobre sus cuentas ni sobre su información histórica.
                    </p>
                    <p>
                        Salvo indicación contraria del cliente, RankPro podrá mencionar el nombre y logotipo
                        del cliente con fines de portafolio y referencia comercial.
                    </p>

                    <h2 id="confidencialidad">8. Confidencialidad y datos personales</h2>
                    <p>
                        Ambas partes se obligan a mantener como confidencial la información comercial,
                        técnica y estratégica a la que tengan acceso con motivo de la relación, y a no
                        divulgarla a terceros sin autorización previa por escrito, salvo requerimiento de
                        autoridad competente.
                    </p>
                    <p>
                        El tratamiento de datos personales se rige por nuestro
                        <a href="{{ route('legal.privacidad') }}">aviso de privacidad</a>. Cuando RankPro trate
                        datos personales por cuenta del cliente actuará como encargado, conforme a la
                        legislación aplicable.
                    </p>

                    <h2 id="terminacion">9. Terminación</h2>
                    <p>
                        Cualquiera de las partes podrá dar por terminada la relación mediante aviso por escrito
                        con la anticipación establecida en la propuesta. A la terminación, RankPro entregará los
                        materiales y accesos correspondientes al trabajo pagado, y el cliente cubrirá los
                        importes devengados hasta la fecha efectiva de terminación.
                    </p>
                    <p><strong>[COMPLETAR: plazo de aviso de terminación, política de reembolsos y penalizaciones por terminación anticipada.]</strong></p>

                    <h2 id="jurisdiccion">10. Legislación y jurisdicción</h2>
                    <p>
                        Estos términos se rigen por la legislación de los Estados Unidos Mexicanos. Para la
                        interpretación y cumplimiento de los mismos, las partes se someten a la jurisdicción de
                        los tribunales competentes de <strong>[COMPLETAR: ciudad y estado]</strong>, renunciando
                        a cualquier otro fuero que pudiera corresponderles por razón de sus domicilios presentes
                        o futuros.
                    </p>
                    <p>
                        RankPro se reserva el derecho de modificar estos términos en cualquier momento. Los
                        cambios se publicarán en esta página con la fecha de última actualización y aplicarán a
                        las contrataciones posteriores a su publicación.
                    </p>
                    <p>
                        Para cualquier duda sobre este documento, escríbenos a
                        <a href="mailto:administracion@rankprosolutions.com.mx">administracion@rankprosolutions.com.mx</a>.
                    </p>
                </div>
            </div>
        </section>
    </main>

    @include('components.footer')
@endsection
