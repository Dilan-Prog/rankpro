<?php

namespace App\Exceptions;

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
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
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
                if ($status === Response::HTTP_TOO_MANY_REQUESTS) {
                    return response()->json(['message' => 'Demasiadas peticiones. Intenta de nuevo en unos segundos.'], 429);
                }

                return response()->json(['message' => $e->getMessage() ?: 'Error en la petición.'], $status);
            }

            if (config('app.debug')) {
                return response()->json(['message' => $e->getMessage(), 'exception' => get_class($e)], 500);
            }

            return response()->json(['message' => 'Error interno del servidor.'], 500);
        });
    }
}
