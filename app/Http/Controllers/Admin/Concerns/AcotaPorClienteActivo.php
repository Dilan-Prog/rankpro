<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\KeywordCluster;
use App\Models\SeoOnPageAccion;
use App\Support\ClienteActivo;

/**
 * Todo lo que toca el SEO Plan se acota al cliente activo del menú: un id de
 * otro cliente en la URL responde 404 y no se filtra ni se modifica nada ajeno.
 */
trait AcotaPorClienteActivo
{
    protected function clienteActivoId(): int
    {
        $id = ClienteActivo::id();
        abort_if($id === null || ClienteActivo::modelo() === null, 409, 'Elige un cliente en el menú para trabajar con el SEO Plan.');

        return $id;
    }

    protected function actividadDelCliente(SeoOnPageAccion $accion): SeoOnPageAccion
    {
        $pertenece = $accion->seoCampana()->withTrashed()->where('cliente_id', $this->clienteActivoId())->exists();
        abort_unless($pertenece, 404);

        return $accion;
    }

    protected function clusterDelCliente(KeywordCluster $cluster): KeywordCluster
    {
        abort_unless($cluster->cliente_id === $this->clienteActivoId(), 404);

        return $cluster;
    }
}
