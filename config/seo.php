<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Indexabilidad
    |--------------------------------------------------------------------------
    |
    | Controla si las paginas publicas emiten "index, follow" o "noindex, nofollow".
    |
    | Historico: hasta ago-2026 esto dependia unicamente de APP_ENV === 'production'.
    | El .env de produccion no tenia APP_ENV=production, asi que el sitio entero
    | estuvo emitiendo noindex sin que nadie lo notara. Ahora es una decision
    | declarada de forma explicita, con APP_ENV solo como respaldo.
    |
    | SEO_INDEXABLE=true   -> index, follow  (produccion)
    | SEO_INDEXABLE=false  -> noindex, nofollow (staging, local, mantenimiento)
    | sin definir          -> se decide por APP_ENV, como antes
    |
    */

    'indexable' => env('SEO_INDEXABLE', null),

    /*
    |--------------------------------------------------------------------------
    | Directivas para robots
    |--------------------------------------------------------------------------
    */

    'robots_index' => 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
    'robots_noindex' => 'noindex, nofollow',

];
