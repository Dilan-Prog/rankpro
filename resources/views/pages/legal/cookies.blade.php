{{--
    ============================================================================
    PLANTILLA — debe ser revisada por un abogado antes de publicarse; los datos
    del responsable, domicilio y ARCO deben completarse.
    ============================================================================
    Los marcadores [COMPLETAR: ...] señalan la información que debe verificarse
    contra las cookies realmente instaladas por el sitio antes de publicar.
--}}
@extends('layouts.app')

@section('title', 'Política de Cookies | RankPro')
@section('description', 'Qué cookies utiliza el sitio de RankPro, para qué sirven y cómo puedes configurarlas o eliminarlas desde tu navegador.')
@section('canonical', route('legal.cookies'))

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
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Política de Cookies', 'item' => route('legal.cookies')],
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
                        <li><span aria-current="page">Política de Cookies</span></li>
                    </ol>
                </nav>
                <div class="page-hero__inner">
                    <h1>Política de Cookies</h1>
                    <p class="page-hero__lead">
                        Explicamos qué son las cookies, cuáles utilizamos en este sitio, para qué sirven y
                        cómo puedes configurarlas o eliminarlas en cualquier momento.
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
                            <li><a href="#que-son">Qué son las cookies</a></li>
                            <li><a href="#tipos">Tipos de cookies que utilizamos</a></li>
                            <li><a href="#terceros">Cookies de terceros</a></li>
                            <li><a href="#consentimiento">Consentimiento y base legal</a></li>
                            <li><a href="#gestionar">Cómo configurar o eliminar cookies</a></li>
                            <li><a href="#actualizaciones">Actualizaciones de esta política</a></li>
                        </ol>
                    </div>

                    <h2 id="que-son">1. Qué son las cookies</h2>
                    <p>
                        Una cookie es un archivo de texto pequeño que un sitio web guarda en tu navegador
                        cuando lo visitas. Sirve para recordar información entre páginas o entre visitas: por
                        ejemplo, que ya iniciaste sesión, qué preferencias elegiste o cómo llegaste al sitio.
                        Junto con las cookies existen tecnologías equivalentes, como el almacenamiento local
                        del navegador y los píxeles de seguimiento, a las que aplica esta misma política.
                    </p>
                    <p>
                        Las cookies pueden ser <strong>de sesión</strong>, que se eliminan al cerrar el
                        navegador, o <strong>persistentes</strong>, que permanecen durante un periodo
                        determinado. También pueden ser <strong>propias</strong>, instaladas por este sitio, o
                        <strong>de terceros</strong>, instaladas por servicios externos que utilizamos.
                    </p>

                    <h2 id="tipos">2. Tipos de cookies que utilizamos</h2>
                    <p>
                        <strong>Cookies estrictamente necesarias.</strong> Permiten el funcionamiento básico
                        del sitio y no pueden desactivarse. Incluyen las que mantienen la sesión del usuario y
                        el token de seguridad que protege los formularios contra falsificación de peticiones.
                        No requieren consentimiento porque sin ellas el sitio no puede operar.
                    </p>
                    <p>
                        <strong>Cookies de preferencias.</strong> Guardan elecciones que haces en el sitio,
                        como el idioma o la aceptación de este aviso, para no volver a preguntártelo en cada
                        visita.
                    </p>
                    <p>
                        <strong>Cookies analíticas.</strong> Nos permiten entender de forma agregada cómo se
                        usa el sitio: qué páginas se visitan, cuánto tiempo se permanece en ellas y desde qué
                        canal llegó la visita. Usamos esta información para mejorar el contenido y la
                        experiencia. Los datos se tratan de forma estadística y no buscamos identificarte
                        personalmente a través de ellos.
                    </p>
                    <p>
                        <strong>Cookies de publicidad y remarketing.</strong> Se utilizan para medir la
                        efectividad de nuestras campañas y, en su caso, mostrar anuncios relevantes a personas
                        que ya visitaron el sitio. Son las únicas que pueden implicar seguimiento entre sitios.
                    </p>
                    <p>
                        <strong>[COMPLETAR: tabla con el nombre exacto de cada cookie instalada, su proveedor,
                        finalidad y duración, verificada contra el sitio en producción.]</strong>
                    </p>

                    <h2 id="terceros">3. Cookies de terceros</h2>
                    <p>
                        Este sitio puede incorporar servicios de terceros que instalan sus propias cookies y
                        que se rigen por sus respectivas políticas de privacidad. Entre ellos:
                    </p>
                    <ul>
                        <li><strong>Google Analytics</strong>, para medición y estadísticas de uso del sitio.</li>
                        <li><strong>Google Tag Manager</strong>, para administrar las etiquetas de medición.</li>
                        <li><strong>Google Ads</strong>, para medir conversiones de campañas publicitarias.</li>
                        <li><strong>Google Fonts</strong>, para la carga de tipografías del sitio.</li>
                        <li><strong>Meta Ads</strong>, cuando existan campañas activas en Facebook o Instagram.</li>
                    </ul>
                    <p>
                        RankPro no controla las cookies instaladas por estos terceros ni el uso que hagan de la
                        información recabada. Te recomendamos consultar sus políticas de privacidad si deseas
                        conocer el detalle.
                    </p>
                    <p><strong>[COMPLETAR: confirmar cuáles de estos servicios están efectivamente activos en producción y eliminar los que no.]</strong></p>

                    <h2 id="consentimiento">4. Consentimiento y base legal</h2>
                    <p>
                        En México, el uso de cookies que impliquen el tratamiento de datos personales se rige
                        por la Ley Federal de Protección de Datos Personales en Posesión de los Particulares y
                        su Reglamento, que exigen informar sobre su uso y ofrecer los medios para deshabilitarlas.
                        Nuestro <a href="{{ route('legal.privacidad') }}">aviso de privacidad</a> describe qué
                        datos tratamos y con qué finalidad.
                    </p>
                    <p>
                        Las cookies estrictamente necesarias se instalan por la operación del sitio. Para las
                        cookies analíticas y de publicidad recabamos tu consentimiento y puedes retirarlo en
                        cualquier momento cambiando la configuración de tu navegador o eliminando las cookies
                        almacenadas.
                    </p>
                    <p><strong>[COMPLETAR: describir el mecanismo de consentimiento efectivamente implementado en el sitio, en su caso el banner de cookies y el modo de consentimiento de Google.]</strong></p>

                    <h2 id="gestionar">5. Cómo configurar o eliminar cookies</h2>
                    <p>
                        Puedes aceptar, bloquear o eliminar las cookies instaladas desde la configuración de tu
                        navegador. El procedimiento varía según el programa que uses, y normalmente se encuentra
                        en el apartado de privacidad y seguridad:
                    </p>
                    <ul>
                        <li><strong>Google Chrome:</strong> Configuración › Privacidad y seguridad › Cookies y otros datos de sitios.</li>
                        <li><strong>Mozilla Firefox:</strong> Ajustes › Privacidad y seguridad › Cookies y datos del sitio.</li>
                        <li><strong>Safari:</strong> Preferencias › Privacidad › Gestionar datos de sitios web.</li>
                        <li><strong>Microsoft Edge:</strong> Configuración › Cookies y permisos del sitio.</li>
                    </ul>
                    <p>
                        La mayoría de los navegadores también ofrecen un modo de navegación privada que no
                        conserva las cookies al cerrar la ventana. Ten en cuenta que bloquear todas las cookies
                        puede afectar el funcionamiento de este y otros sitios: algunas funciones, como el envío
                        de formularios, dependen de las cookies estrictamente necesarias.
                    </p>

                    <h2 id="actualizaciones">6. Actualizaciones de esta política</h2>
                    <p>
                        Podemos actualizar esta política cuando cambien las herramientas que utilizamos o la
                        normativa aplicable. Los cambios se publicarán en esta misma página indicando la fecha
                        de la última actualización.
                    </p>
                    <p>
                        Si tienes dudas sobre el uso de cookies en este sitio, escríbenos a
                        <a href="mailto:administracion@rankprosolutions.com.mx">administracion@rankprosolutions.com.mx</a>.
                    </p>
                </div>
            </div>
        </section>
    </main>

    @include('components.footer')
@endsection
