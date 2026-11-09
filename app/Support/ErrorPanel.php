<?php

namespace App\Support;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Qué decirle a quien cae en un 404/403 dentro de /admin. El 404 genérico de Laravel pintaba la página
 * pública (navbar y footer del sitio) y no explicaba nada: aquí se averigua el motivo real para poder
 * distinguir "no existe", "se eliminó" y "es de otro cliente" (el cliente activo acota las listas).
 */
class ErrorPanel
{
    /** Mensaje que un `abort(404, ...)` puede llevar para declarar que el motivo es el cliente activo. */
    public const MENSAJE_OTRO_CLIENTE = 'Este recurso pertenece a otro cliente.';

    /**
     * @return array{status: int, titulo: string, mensaje: string, cliente_activo: ?Cliente, otro_cliente: bool}
     */
    public static function contexto(Throwable $e, int $status): array
    {
        $activo = ClienteActivo::modelo();
        $otroCliente = false;

        if ($status === 403) {
            $titulo = 'Sin permiso';
            $mensaje = self::textoPropio($e) ?: 'Tu usuario no tiene permiso para ver o hacer esto.';
        } else {
            $titulo = 'No encontrado';
            $mensaje = 'No encontramos lo que buscas: la dirección es incorrecta o el registro ya no existe.';
            $motivo = self::motivoDelModelo($e, $activo);

            if ($motivo === 'otro_cliente' || self::textoPropio($e) === self::MENSAJE_OTRO_CLIENTE) {
                $otroCliente = true;
                $mensaje = 'Este recurso pertenece a otro cliente. Cambia el cliente activo para verlo.';
            } elseif ($motivo === 'eliminado') {
                $mensaje = 'Este registro fue eliminado y ya no está disponible.';
            } elseif ($activo !== null) {
                // Los módulos acotados responden 404 sin detalle: se avisa de la posibilidad, sin afirmarla.
                $mensaje .= " Si pertenece a otro cliente, cambia el cliente activo (ahora: {$activo->nombre_comercial}).";
            }
        }

        return ['status' => $status, 'titulo' => $titulo, 'mensaje' => $mensaje, 'cliente_activo' => $activo, 'otro_cliente' => $otroCliente];
    }

    /** Solo los mensajes que escribimos nosotros con abort(); los del framework vienen en inglés. */
    private static function textoPropio(Throwable $e): string
    {
        if (! $e instanceof HttpExceptionInterface) {
            return '';
        }
        $texto = trim($e->getMessage());

        return in_array($texto, ['', 'Not Found', 'Forbidden', 'This action is unauthorized.'], true) ? '' : $texto;
    }

    /** @return 'otro_cliente'|'eliminado'|null  Solo si el binding falló y el registro existe en la base. */
    private static function motivoDelModelo(Throwable $e, ?Cliente $activo): ?string
    {
        $previo = $e instanceof ModelNotFoundException ? $e : $e->getPrevious();
        if (! $previo instanceof ModelNotFoundException) {
            return null;
        }

        try {
            $clase = $previo->getModel();
            $id = $previo->getIds()[0] ?? null;
            if (! class_exists($clase) || $id === null) {
                return null;
            }

            $consulta = $clase::query()->withoutGlobalScopes();
            if (in_array(SoftDeletes::class, class_uses_recursive($clase), true)) {
                $consulta->withTrashed();
            }
            $fila = $consulta->find($id);
            if ($fila === null) {
                return null;
            }
            if (method_exists($fila, 'trashed') && $fila->trashed()) {
                return 'eliminado';
            }
            if ($activo !== null && isset($fila->cliente_id) && (int) $fila->cliente_id !== $activo->id) {
                return 'otro_cliente';
            }
        } catch (Throwable) {
            // Sin pistas, se muestra el mensaje genérico: nunca romper la página de error.
        }

        return null;
    }
}
