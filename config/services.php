<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        // Token de verificacion de Google Search Console. Se emite como
        // <meta name="google-site-verification"> desde components/seo/meta.blade.php.
        'site_verification' => env('GOOGLE_SITE_VERIFICATION'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Perfiles sociales (sameAs de schema.org)
    |--------------------------------------------------------------------------
    |
    | Los consume components/seo/jsonld.blade.php para emitir "sameAs" en el nodo
    | ProfessionalService. Es la senal que usa Google para confirmar que el
    | negocio del sitio y los perfiles sociales son la misma entidad.
    |
    | IMPORTANTE: deja vacia cualquier clave cuyo perfil todavia NO exista. El
    | blade filtra los vacios y omite "sameAs" por completo si no queda ninguno.
    | Declarar un perfil inexistente (o el de otra marca) es peor que no declarar
    | nada: Google lo detecta como inconsistencia de entidad.
    |
    | Variables a crear en .env y .env.example (URL completa del perfil, con
    | https:// y sin parametros de campana):
    |
    |     SOCIAL_FACEBOOK=
    |     SOCIAL_INSTAGRAM=
    |     SOCIAL_LINKEDIN=
    |     SOCIAL_X=
    |     SOCIAL_YOUTUBE=
    |     SOCIAL_TIKTOK=
    |
    */

    'social' => [
        'facebook' => env('SOCIAL_FACEBOOK'),
        'instagram' => env('SOCIAL_INSTAGRAM'),
        'linkedin' => env('SOCIAL_LINKEDIN'),
        'x' => env('SOCIAL_X'),
        'youtube' => env('SOCIAL_YOUTUBE'),
        'tiktok' => env('SOCIAL_TIKTOK'),
    ],

];
