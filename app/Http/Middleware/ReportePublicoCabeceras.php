<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de la vista pública de reportes (`/r/{token}`). Van en un middleware y no en
 * el controlador para que también las lleven las respuestas de error (404/410): un buscador
 * no debe indexar ni siquiera la página que dice "enlace vencido", y ningún proxy debe
 * guardar copia de un documento del cliente.
 */
class ReportePublicoCabeceras
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        $respuesta->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $respuesta->headers->set('Cache-Control', 'no-store, private');
        // El token viaja en la URL: que no se filtre en el Referer de ningún enlace saliente.
        $respuesta->headers->set('Referrer-Policy', 'no-referrer');

        return $respuesta;
    }
}
