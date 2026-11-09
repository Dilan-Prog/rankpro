<?php

namespace App\Exceptions;

use App\Support\ErrorPanel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
        // Llave privada de Google (Search Console): nunca debe quedar en la sesión como "old input".
        'llave_json',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // 404/403 dentro de /admin/*: página de error DEL PANEL (el 404 público mostraba navbar y footer del sitio).
        // Solo con sesión iniciada (el layout del panel la necesita) y nunca para peticiones JSON.
        $this->renderable(function (Throwable $e, Request $request) {
            if (! $request->is('admin', 'admin/*') || $request->expectsJson() || ! $e instanceof HttpExceptionInterface) {
                return null;
            }
            $status = $e->getStatusCode();
            if (! in_array($status, [403, 404], true) || ! $request->hasSession() || $request->user() === null) {
                return null;
            }

            try {
                return response()->view('errors.admin', ['pageTitle' => $status === 403 ? 'Sin permiso' : 'No encontrado', 'error' => ErrorPanel::contexto($e, $status)], $status);
            } catch (Throwable) {
                // Si el propio layout del panel falla, cae a la vista de error por defecto en vez de enmascarar el error original.
                return null;
            }
        });

        // Solo para /api/*: siempre JSON, nunca una redirección a /login ni una
        // vista de error en HTML — n8n y cualquier cliente HTTP dependen de esto.
        $this->renderable(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof ErrorDeDominio) {
                return response()->json(['message' => $e->getMessage(), 'campo' => $e->campo], $e->status);
            }

            if ($e instanceof ValidationException) {
                return response()->json(['message' => 'Los datos enviados no son válidos.', 'errors' => $e->errors()], 422);
            }

            if ($e instanceof AuthenticationException) {
                return response()->json(['message' => 'No autenticado.'], 401);
            }

            if ($e instanceof AuthorizationException) {
                return response()->json(['message' => $e->getMessage() ?: 'No autorizado.'], 403);
            }

            if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                return response()->json(['message' => 'Recurso no encontrado.'], 404);
            }

            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
                // Se conservan las cabeceras de la excepción (Retry-After del 429, Allow del 405).
                $cabeceras = $e->getHeaders();

                // Los mensajes del framework vienen en inglés; los de abort() propios ya están en español.
                $genericos = [
                    Response::HTTP_METHOD_NOT_ALLOWED => 'Método no permitido para esta ruta.',
                    Response::HTTP_REQUEST_ENTITY_TOO_LARGE => 'La petición es demasiado grande.',
                    Response::HTTP_UNSUPPORTED_MEDIA_TYPE => 'Tipo de contenido no soportado.',
                    Response::HTTP_TOO_MANY_REQUESTS => 'Demasiadas peticiones. Intenta de nuevo en unos segundos.',
                    Response::HTTP_SERVICE_UNAVAILABLE => 'Servicio no disponible temporalmente.',
                ];
                $mensaje = $genericos[$status] ?? ($e->getMessage() ?: 'Error en la petición.');

                return response()->json(['message' => $mensaje], $status, $cabeceras);
            }

            if (config('app.debug')) {
                return response()->json(['message' => $e->getMessage(), 'exception' => get_class($e)], 500);
            }

            return response()->json(['message' => 'Error interno del servidor.'], 500);
        });
    }
}
