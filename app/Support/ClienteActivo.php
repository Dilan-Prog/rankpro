<?php

namespace App\Support;

use App\Models\Cliente;

/**
 * Cliente activo del panel: el que se elige en el selector del menú (sección
 * «Marca») y con el que trabajan los módulos de esa sección (Identidad,
 * Audiencia, SEO Plan, Contenido).
 *
 * Vive en la sesión y no en la base: es una preferencia de navegación de cada
 * usuario, no un dato del negocio. Si el cliente se borra o desactiva entre una
 * visita y otra, simplemente se trata como «ninguno».
 */
class ClienteActivo
{
    public const CLAVE = 'cliente_activo_id';

    public static function id(): ?int
    {
        $id = session(self::CLAVE);

        return $id ? (int) $id : null;
    }

    public static function modelo(): ?Cliente
    {
        $id = self::id();

        return $id ? Cliente::query()->find($id) : null;
    }

    public static function establecer(?int $id): void
    {
        if ($id === null) {
            session()->forget(self::CLAVE);

            return;
        }

        session([self::CLAVE => $id]);
    }

    /**
     * Clientes para el selector del menú, por nombre de empresa.
     *
     * @return \Illuminate\Support\Collection<int, Cliente>
     */
    public static function opciones()
    {
        return Cliente::query()->orderBy('empresa')->get(['id', 'empresa', 'nombre']);
    }
}
