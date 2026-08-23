{{--
    ============================================================================
    PLANTILLA — debe ser revisada por un abogado antes de publicarse; los datos
    del responsable, domicilio y ARCO deben completarse.
    ============================================================================
    Los marcadores [COMPLETAR: ...] señalan la información que falta y que es
    obligatoria conforme a la Ley Federal de Protección de Datos Personales en
    Posesión de los Particulares (LFPDPPP) y su Reglamento.
--}}
@extends('layouts.app')

@section('title', 'Aviso de Privacidad | RankPro')
@section('description', 'Aviso de privacidad de RankPro conforme a la Ley Federal de Protección de Datos Personales en Posesión de los Particulares: qué datos tratamos, para qué y cómo ejercer tus derechos ARCO.')
@section('canonical', route('legal.privacidad'))

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
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Aviso de Privacidad', 'item' => route('legal.privacidad')],
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
                        <li><span aria-current="page">Aviso de Privacidad</span></li>
                    </ol>
                </nav>
                <div class="page-hero__inner">
                    <h1>Aviso de Privacidad</h1>
                    <p class="page-hero__lead">
                        Este aviso describe qué datos personales recabamos, con qué finalidad los usamos y
                        cómo puedes ejercer tus derechos de acceso, rectificación, cancelación y oposición.
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
                            <li><a href="#responsable">Identidad y domicilio del responsable</a></li>
                            <li><a href="#datos">Datos personales que recabamos</a></li>
                            <li><a href="#finalidades">Finalidades del tratamiento</a></li>
                            <li><a href="#transferencias">Transferencias y encargados</a></li>
                            <li><a href="#arco">Derechos ARCO y revocación del consentimiento</a></li>
                            <li><a href="#cookies-priv">Cookies y tecnologías de rastreo</a></li>
                            <li><a href="#seguridad">Medidas de seguridad y conservación</a></li>
                            <li><a href="#cambios">Cambios al aviso de privacidad</a></li>
                        </ol>
                    </div>

                    <h2 id="responsable">1. Identidad y domicilio del responsable</h2>
                    <p>
                        <strong>[COMPLETAR: razón social completa del responsable]</strong>, en lo sucesivo
                        “RankPro”, con domicilio en <strong>[COMPLETAR: calle, número, colonia, código postal,
                        municipio o alcaldía, estado, México]</strong>, es responsable del tratamiento de los
                        datos personales que nos proporcionas, en términos de la Ley Federal de Protección de
                        Datos Personales en Posesión de los Particulares (LFPDPPP), su Reglamento y los
                        Lineamientos del Aviso de Privacidad.
                    </p>
                    <p>
                        Para cualquier asunto relacionado con este aviso puedes contactarnos en
                        <a href="mailto:administracion@rankprosolutions.com.mx">administracion@rankprosolutions.com.mx</a>
                        o al teléfono <strong>+52 734 103 6410</strong>. El departamento encargado de atender las
                        solicitudes en materia de datos personales es <strong>[COMPLETAR: nombre del área o
                        persona designada]</strong>.
                    </p>

                    <h2 id="datos">2. Datos personales que recabamos</h2>
                    <p>
                        Recabamos datos personales de forma directa cuando los proporcionas a través de
                        nuestros formularios, por correo electrónico, por WhatsApp o durante la contratación
                        de nuestros servicios; y de forma indirecta a través de tecnologías de rastreo en
                        nuestro sitio web.
                    </p>
                    <p>Los datos que podemos tratar son:</p>
                    <ul>
                        <li><strong>Datos de identificación y contacto:</strong> nombre, correo electrónico, número telefónico, empresa y puesto.</li>
                        <li><strong>Datos del proyecto:</strong> información sobre tu negocio, sitio web, objetivos comerciales y presupuesto aproximado.</li>
                        <li><strong>Datos de facturación:</strong> razón social, RFC, domicilio fiscal y régimen fiscal, únicamente cuando exista una relación contractual.</li>
                        <li><strong>Datos de navegación:</strong> dirección IP, tipo de dispositivo y navegador, páginas visitadas y origen del tráfico.</li>
                    </ul>
                    <p>
                        <strong>No recabamos datos personales sensibles</strong> ni datos patrimoniales o
                        financieros distintos a los estrictamente necesarios para la facturación de nuestros
                        servicios. Tampoco recabamos datos de menores de edad de forma intencional.
                    </p>

                    <h2 id="finalidades">3. Finalidades del tratamiento</h2>
                    <p><strong>Finalidades primarias</strong>, necesarias para la relación con nosotros:</p>
                    <ul>
                        <li>Atender tus solicitudes de información, cotización y contacto.</li>
                        <li>Elaborar propuestas comerciales y formalizar la contratación de servicios.</li>
                        <li>Prestar, administrar y dar seguimiento a los servicios contratados.</li>
                        <li>Emitir comprobantes fiscales y gestionar cobros y pagos.</li>
                        <li>Dar soporte, atender aclaraciones y cumplir obligaciones legales aplicables.</li>
                    </ul>
                    <p><strong>Finalidades secundarias</strong>, que no son necesarias para la relación y a las que puedes oponerte:</p>
                    <ul>
                        <li>Envío de comunicaciones informativas, boletines y contenido sobre marketing digital.</li>
                        <li>Invitaciones a eventos, webinars o encuestas de satisfacción.</li>
                        <li>Elaboración de estadísticas internas y mejora de nuestros servicios.</li>
                    </ul>
                    <p>
                        Si no deseas que tus datos se traten para las finalidades secundarias, puedes
                        manifestarlo enviando un correo a
                        <a href="mailto:administracion@rankprosolutions.com.mx">administracion@rankprosolutions.com.mx</a>.
                        Tu negativa no será motivo para negarte los servicios que solicitas.
                    </p>

                    <h2 id="transferencias">4. Transferencias y encargados</h2>
                    <p>
                        Para prestar nuestros servicios utilizamos proveedores tecnológicos que actúan como
                        encargados del tratamiento, entre ellos plataformas de analítica y publicidad digital,
                        servicios de correo electrónico, alojamiento web y herramientas de gestión de proyectos.
                        Algunos de estos proveedores pueden almacenar información fuera de México.
                    </p>
                    <p>
                        <strong>No vendemos, cedemos ni comercializamos tus datos personales.</strong> Solo
                        realizamos transferencias sin requerir tu consentimiento en los supuestos previstos por
                        el artículo 37 de la LFPDPPP, como el cumplimiento de obligaciones legales o
                        requerimientos de autoridad competente. Cualquier otra transferencia se te informará y
                        requerirá tu consentimiento expreso.
                    </p>
                    <p><strong>[COMPLETAR: listado específico de proveedores y encargados utilizados, para mayor transparencia.]</strong></p>

                    <h2 id="arco">5. Derechos ARCO y revocación del consentimiento</h2>
                    <p>
                        Tienes derecho a conocer qué datos personales tenemos de ti, para qué los utilizamos y
                        las condiciones de su uso (<strong>Acceso</strong>); a solicitar la corrección de
                        información inexacta o incompleta (<strong>Rectificación</strong>); a que la eliminemos
                        de nuestros registros cuando consideres que no está siendo utilizada conforme a los
                        principios de la ley (<strong>Cancelación</strong>); y a oponerte al uso de tus datos
                        para fines específicos (<strong>Oposición</strong>). También puedes revocar en cualquier
                        momento el consentimiento que nos hayas otorgado.
                    </p>
                    <p>
                        Para ejercer cualquiera de estos derechos, envía tu solicitud al correo
                        <a href="mailto:administracion@rankprosolutions.com.mx">administracion@rankprosolutions.com.mx</a>
                        con la siguiente información:
                    </p>
                    <ul>
                        <li>Nombre completo y medio para comunicarte la respuesta.</li>
                        <li>Copia de una identificación oficial que acredite tu identidad, o la del representante legal junto con el documento que acredite la representación.</li>
                        <li>Descripción clara y precisa de los datos respecto de los que ejerces el derecho.</li>
                        <li>Cualquier documento que facilite la localización de tus datos personales.</li>
                    </ul>
                    <p>
                        Daremos respuesta en un plazo máximo de <strong>20 días hábiles</strong> contados desde
                        la recepción de la solicitud, y de resultar procedente se hará efectiva dentro de los
                        <strong>15 días hábiles</strong> siguientes. Estos plazos pueden ampliarse una sola vez
                        por igual periodo cuando las circunstancias lo justifiquen.
                    </p>
                    <p><strong>[COMPLETAR: procedimiento y medios adicionales para el ejercicio de derechos ARCO, así como formato de solicitud si se utiliza uno.]</strong></p>
                    <p>
                        Si consideras que tu derecho a la protección de datos personales ha sido vulnerado,
                        puedes acudir ante la autoridad competente en materia de protección de datos personales
                        en México.
                    </p>

                    <h2 id="cookies-priv">6. Cookies y tecnologías de rastreo</h2>
                    <p>
                        Nuestro sitio utiliza cookies y tecnologías similares para recordar tus preferencias,
                        medir el uso del sitio y, en su caso, mostrar publicidad relevante. Puedes consultar el
                        detalle y las opciones de configuración en nuestra
                        <a href="{{ route('legal.cookies') }}">política de cookies</a>.
                    </p>

                    <h2 id="seguridad">7. Medidas de seguridad y conservación</h2>
                    <p>
                        Aplicamos medidas de seguridad administrativas, técnicas y físicas razonables para
                        proteger tus datos personales contra daño, pérdida, alteración, destrucción o uso,
                        acceso o tratamiento no autorizados. Entre ellas se incluyen el control de accesos por
                        usuario, el uso de conexiones cifradas y la limitación del acceso al personal que
                        requiere la información para desempeñar sus funciones.
                    </p>
                    <p>
                        Conservamos los datos únicamente durante el tiempo necesario para cumplir las
                        finalidades descritas y los plazos legales aplicables en materia fiscal y mercantil.
                        Concluidos esos plazos, los datos se bloquean y posteriormente se suprimen.
                    </p>

                    <h2 id="cambios">8. Cambios al aviso de privacidad</h2>
                    <p>
                        Este aviso puede modificarse por cambios legales, en nuestras prácticas o en nuestro
                        modelo de negocio. Cualquier modificación se publicará en esta misma página, indicando
                        la fecha de última actualización. Te recomendamos revisarla periódicamente.
                    </p>
                </div>
            </div>
        </section>
    </main>

    @include('components.footer')
@endsection
