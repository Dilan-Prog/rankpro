<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ElementoContenido;
use App\Support\ClienteActivo;

/**
 * Base de los controladores del módulo «Contenido».
 *
 * Todo elemento se resuelve contra el CLIENTE ACTIVO de la sesión: el binding de
 * ruta por id encontraría elementos de cualquier cliente, así que sin esta
 * comprobación bastaría con adivinar un id para leer o borrar contenido de otro
 * cliente. Un elemento ajeno responde 404 (no 403) para no confirmar que existe.
 */
abstract class ContenidoBaseController extends Controller
{
    protected function clienteActivoId(): ?int
    {
        return ClienteActivo::id();
    }

    /** Cliente activo, o aborta 422 (endpoints JSON que no tienen sentido sin cliente). */
    protected function clienteActivoObligatorio(): int
    {
        $id = $this->clienteActivoId();

        if ($id === null || ClienteActivo::modelo() === null) {
            abort(response()->json(['message' => 'Elige primero un cliente en el menú.'], 422));
        }

        return $id;
    }

    /** Aborta 404 si el elemento no es del cliente activo. */
    protected function delClienteActivo(ElementoContenido $elemento): ElementoContenido
    {
        abort_unless($elemento->cliente_id === $this->clienteActivoId(), 404);

        return $elemento;
    }
}
