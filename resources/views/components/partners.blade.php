@php
    $partners = ['Google Partner', 'Meta Business', 'HubSpot', 'Shopify', 'Semrush', 'Klaviyo'];
@endphp

<section class="partners">
    <div class="container partners__inner">
        <p class="partners__label">Certificados y Partners Oficiales</p>
        <div class="partners__list">
            @foreach ($partners as $partner)
                <div class="partners__item">{{ $partner }}</div>
            @endforeach
        </div>
    </div>
</section>
