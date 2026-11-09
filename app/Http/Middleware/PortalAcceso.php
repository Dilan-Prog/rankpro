<?php

namespace App\Http\Middleware;

use App\Models\PortalAcceso as Acceso;
use App\Support\Portal\Respuesta;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve el enlace mágico del portal: 404 si el token es desconocido, 410 si venció o fue revocado
 * (mismo mensaje para ambos), y deja el acceso en la petición. Cada petición válida suma al contador
 * y deja una fila de auditoría. Los controladores NUNCA leen un cliente de la URL: salen de aquí.
 */
class PortalAcceso
{
    public const PETICIONES_POR_MINUTO = 60;

    public function handle(Request $request, Closure $next): Response
    {
        // Límite por IP aquí y no con `throttle:`: ese alias está en la lista de prioridad del Kernel y se
        // ordenaba por delante de PortalCabeceras, dejando el 429 sin sus cabeceras. Los 404 también cuentan.
        $clave = 'portal|'.$request->ip();
        if (RateLimiter::tooManyAttempts($clave, self::PETICIONES_POR_MINUTO)) {
            return Respuesta::error(429)->header('Retry-After', (string) RateLimiter::availableIn($clave));
        }
        RateLimiter::hit($clave, 60);

        $token = (string) $request->route('token');

        // Longitud y alfabeto fijos: descarta basura antes de tocar la base.
        if (strlen($token) !== Acceso::LONGITUD_TOKEN || ! ctype_alnum($token)) {
            return Respuesta::error(404);
        }

        $acceso = Acceso::buscarPorToken($token);
        if ($acceso === null) {
            return Respuesta::error(404);
        }

        // Un cliente eliminado se trata como enlace no disponible (la relación excluye los eliminados).
        if ($acceso->estado() !== 'vigente' || $acceso->cliente === null) {
            return Respuesta::error(410);
        }

        $acceso->registrarVisita($request);
        $request->attributes->set('portal_acceso', $acceso);

        return $next($request);
    }
}
