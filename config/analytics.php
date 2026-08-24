<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Tag Manager
    |--------------------------------------------------------------------------
    |
    | Contenedor GTM del sitio publico (formato GTM-XXXXXXX). Desde GTM se
    | despliegan GA4 y las etiquetas de Google Ads: no se anaden aqui a mano,
    | para tener un solo punto de control y un solo script.
    |
    | Si esta vacio, NO se emite ningun script. Asi el entorno local no
    | contamina los datos de produccion sin tener que tocar el codigo.
    |
    */

    'gtm_id' => env('GTM_ID'),

    /*
    |--------------------------------------------------------------------------
    | Entornos donde se activa la medicion
    |--------------------------------------------------------------------------
    |
    | Aunque GTM_ID este definido, solo se emite en estos entornos. Evita que
    | una copia de la base de datos o del .env en staging mande eventos a la
    | propiedad real de GA4.
    |
    */

    'entornos' => ['production'],

    /*
    |--------------------------------------------------------------------------
    | Consent Mode v2
    |--------------------------------------------------------------------------
    |
    | Estado por defecto antes de que la persona decida. 'denied' en todo lo
    | que no sea estrictamente necesario: es el unico default defendible y el
    | que exige la normativa europea; en Mexico no es obligatorio, pero Google
    | usa las senales de consentimiento para el modelado de conversiones, asi
    | que declararlas bien mejora los datos en lugar de empeorarlos.
    |
    | 'security_storage' va en granted porque cubre antifraude y seguridad.
    |
    */

    'consent_por_defecto' => [
        'ad_storage' => 'denied',
        'ad_user_data' => 'denied',
        'ad_personalization' => 'denied',
        'analytics_storage' => 'denied',
        'functionality_storage' => 'denied',
        'personalization_storage' => 'denied',
        'security_storage' => 'granted',
    ],

    /*
    | Dias que se recuerda la decision antes de volver a preguntar.
    */
    'consent_dias' => 180,

];
