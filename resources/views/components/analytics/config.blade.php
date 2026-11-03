{{--
    Datos de medicion para el JS publico · <head>
    ------------------------------------------------------------------
    Expone window.rankproMedicion = {modo, conversiones} para que el JS sepa si hay
    GTM o gtag y a que etiqueta de conversion de Google Ads mandar cada evento.
    Solo se emite cuando hay medicion; las etiquetas pasan por FormatosGoogle (lo
    que no tiene el formato se manda como null), y el JSON escapa <, >, & y comillas
    para que nada pueda cerrar el <script>.
--}}
@if (\App\Support\Medicion::hayMedicion())
    @php
        $datosMedicion = [
            'modo' => \App\Support\Medicion::modo(),
            'conversiones' => [
                'agendar_completado' => \App\Support\Medicion::conversion('agendar_completado'),
                'click_whatsapp' => \App\Support\Medicion::conversion('click_whatsapp'),
                'click_telefono' => \App\Support\Medicion::conversion('click_telefono'),
            ],
        ];
    @endphp
    <script>window.rankproMedicion = {!! json_encode($datosMedicion, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!};</script>
@endif
