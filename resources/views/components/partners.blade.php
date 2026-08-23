@php
    $partners = ['Google Partner', 'Meta Business', 'Semrush'];
@endphp

<section class="partners" aria-labelledby="partners-titulo">
    <div class="container partners__inner">
        <h2 class="partners__label" id="partners-titulo">Certificados y Partners Oficiales</h2>
        <div class="partners__list">
            @foreach ($partners as $partner)
                <div class="partners__item">{{ $partner }}</div>
            @endforeach
        </div>
    </div>
</section>
