{{--
    Strip de sectores bajo el hero. Discreto a propósito: es prueba social de
    apoyo, no debe competir con el CTA. Los sectores salen de los casos
    publicados (CasosExito::sectores()) para que nunca anuncie uno del que
    no hay caso debajo.

    Iconos SVG en línea, no emojis: es lo que usa el resto de la landing.
--}}
@php
    $sectoresStrip = \App\Support\CasosExito::sectores();
    $icoSector = [
        'teal' => '<path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/><path d="M9 9h1"/><path d="M9 13h1"/><path d="M9 17h1"/>',
        'ink' => '<path d="M2 20a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8l-7 5V8l-7 5V4a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/>',
        'brand' => '<path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/>',
    ];
@endphp

<section class="cv-section cv-section--tight cv-casos-strip" aria-labelledby="casos-strip-titulo">
    <div class="container">
        <p class="cv-casos-strip__label" id="casos-strip-titulo">Empresas que confían en RankPro</p>
        <ul class="cv-casos-strip__list">
            @foreach ($sectoresStrip as $sector)
                <li class="cv-casos-strip__pill cv-casos-strip__pill--{{ $sector['tono'] }}">
                    {!! $ico($icoSector[$sector['tono']] ?? $icoSector['brand'], 14) !!}
                    <span>{{ $sector['label'] }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</section>
