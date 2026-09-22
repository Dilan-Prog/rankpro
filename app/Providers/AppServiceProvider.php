<?php

namespace App\Providers;

use App\Services\Webhooks\Despachador;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singleton: acumula las entregas pendientes de la petición en curso y
        // las vacía una sola vez en app()->terminating() (ver Despachador::emitir).
        $this->app->singleton(Despachador::class);
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

        // Paginacion propia (sin Tailwind), coherente con el CSS del sitio.
        // Se registra como vista por defecto para no repetir ->links('...') en
        // cada listado y para que ninguna vista caiga por error en la de Tailwind.
        // No se toca defaultSimpleView: la vista usa $elements, que
        // simplePaginate() no proporciona.
        Paginator::defaultView('vendor.pagination.rankpro');
    }
}
