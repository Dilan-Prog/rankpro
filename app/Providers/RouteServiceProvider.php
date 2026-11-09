<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/admin/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        // Por token de Sanctum cuando lo hay (API v1: cada token de n8n tiene su
        // propio límite), si no por usuario o IP (la ruta /api/user de ejemplo).
        RateLimiter::for('api', function (Request $request) {
            $token = $request->user()?->currentAccessToken();
            $llave = $token ? 'token:'.$token->id : ($request->user()?->id ?: $request->ip());

            return Limit::perMinute((int) config('api.rate_limit', 120))->by($llave);
        });

        // El token del snippet no es un límite de confidencialidad (el JS que lo porta es público en el sitio del cliente), solo de atribución — el límite es por IP.
        RateLimiter::for('tracking-public', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // Píxel y clics de correo: mismo criterio, por IP.
        RateLimiter::for('correo-public', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        // /agendar es pública y sin sesión: mismo criterio que tracking-public, por IP.
        // Reservar (POST /agendar) es mucho más caro que consultar: crea una reunión y manda
        // correo a la dirección que el visitante escriba, así que se limita aparte y fuerte
        // (6/min y 30/hora por IP). El calendario sigue usando el límite holgado de lectura.
        RateLimiter::for('agendar-public', function (Request $request) {
            if ($request->isMethod('POST') && $request->routeIs('agendar.agendar')) {
                return [
                    Limit::perMinute(6)->by('reservar-min|'.$request->ip()),
                    Limit::perHour(30)->by('reservar-hora|'.$request->ip()),
                ];
            }

            return Limit::perMinute(60)->by($request->ip());
        });

        // Enlaces públicos de reportes: cualquiera puede probar tokens, así que van limitados por IP.
        RateLimiter::for('reporte-publico', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/publico_reportes.php'));

            Route::middleware('web')
                ->group(base_path('routes/publico_portal.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
