<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // En produccion todas las URLs generadas deben ser https. Detras de un
        // proxy o CDN, Laravel puede leer el esquema como http y emitir el
        // canonical y og:url en http:// mientras el sitio se sirve por https://,
        // lo que Google interpreta como contenido duplicado.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
