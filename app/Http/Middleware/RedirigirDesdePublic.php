<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirige /public/loquesea -> /loquesea con un 301.
 *
 * EL PROBLEMA
 * El document root del hosting apunta a la raiz del proyecto en vez de a
 * /public. Con esa configuracion el sitio entero responde DOS veces: en
 * /servicios y en /public/servicios. Symfony descuenta el directorio del script
 * al calcular el path, asi que /public/servicios enruta igual y devuelve 200
 * con el contenido completo; peor aun, url() prefija /public y la pagina
 * duplicada se declara canonica de si misma. Para Google son dos sitios
 * identicos compitiendo entre ellos.
 *
 * POR QUE EN PHP Y NO SOLO EN .htaccess
 * El .htaccess de la raiz ya trae la regla equivalente, pero es un archivo
 * oculto y los clientes FTP y gestores de archivos lo omiten por defecto: en
 * produccion quedo una version antigua sin esa regla, mientras el codigo PHP si
 * se desplegaba. Esta capa viaja con el codigo, asi que protege aunque el
 * .htaccess no llegue, aunque alguien lo sobrescriba, o aunque el servidor
 * cambie de Apache a otro que no lea .htaccess.
 *
 * Las dos capas conviven sin pisarse: si el .htaccess actua primero, aqui no
 * llega nada; si no actua, redirige esto. En ambos casos es UN solo salto.
 *
 * SE PUEDE BORRAR cuando el document root apunte a /public de forma definitiva.
 */
class RedirigirDesdePublic
{
    public function handle(Request $request, Closure $next): Response
    {
        // getBaseUrl() es el directorio del front controller visto desde la web:
        // vale '' cuando el document root es correcto y '/public' cuando no.
        $base = rtrim($request->getBaseUrl(), '/');

        if ($base === '' || ! str_ends_with($base, '/public')) {
            return $next($request);
        }

        // Solo redirige lecturas: un 301 sobre un POST haria que el navegador
        // reenviara la peticion como GET y se perderian los datos del envio.
        if (! $request->isMethodSafe()) {
            return $next($request);
        }

        $destino = $request->getSchemeAndHttpHost()
            . ($request->getPathInfo() ?: '/')
            . ($request->getQueryString() !== null ? '?' . $request->getQueryString() : '');

        return redirect()->to($destino, 301);
    }
}
