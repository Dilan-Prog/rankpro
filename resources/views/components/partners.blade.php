@php
    /*
     * Plataformas sobre las que operamos a diario. NO son logotipos de clientes
     * ni acreditaciones oficiales: la afirmación "Google Partner" se retiró en
     * la revisión de E-E-A-T porque no podía enlazarse al directorio oficial de
     * partners de Google. Si en algún momento se obtiene la acreditación, debe
     * volver acompañada del enlace de verificación, no como texto suelto.
     */
    $plataformas = ['Google Ads', 'Google Analytics 4', 'Search Console', 'Meta Ads', 'WordPress', 'Shopify'];
@endphp

<section class="partners" aria-labelledby="partners-titulo">
    <div class="container partners__inner">
        <h2 class="partners__label" id="partners-titulo">Plataformas con las que trabajamos todos los días</h2>
        <ul class="partners__list">
            @foreach ($plataformas as $plataforma)
                <li class="partners__item">{{ $plataforma }}</li>
            @endforeach
        </ul>
    </div>
</section>
