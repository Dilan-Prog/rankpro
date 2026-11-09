<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras del portal del cliente. Va como middleware EXTERNO (antes del throttle y de PortalAcceso)
 * para que también las lleven el 404, el 410 y el 429: ni un buscador debe indexar la página de
 * "enlace vencido" ni un proxy guardar copia de un documento del cliente.
 */
class PortalCabeceras
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        $respuesta->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $respuesta->headers->set('Cache-Control', 'no-store, private');
        // El token viaja en la URL: que no se filtre en el Referer de ningún enlace saliente.
        $respuesta->headers->set('Referrer-Policy', 'no-referrer');
        $respuesta->headers->set('X-Content-Type-Options', 'nosniff');

        return $respuesta;
    }
}
