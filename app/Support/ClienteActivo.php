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

    /**
     * Id del cliente activo, solo si el cliente aún existe. Si se borró (borrado suave incluido)
     * entre una visita y otra se limpia la sesión: así id() y modelo() nunca discrepan, y quien
     * compara contra id() (Contenido, SEO Plan, exportar keywords) no queda con un id huérfano.
     */
    public static function id(): ?int
    {
        return self::modelo()?->id;
    }

    public static function modelo(): ?Cliente
    {
        $id = session(self::CLAVE);

        if (! $id) {
            return null;
        }

        $cliente = Cliente::query()->find((int) $id);

        if ($cliente === null) {
            session()->forget(self::CLAVE);
        }

        return $cliente;
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
