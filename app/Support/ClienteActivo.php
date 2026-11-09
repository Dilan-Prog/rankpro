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

    private const MEMO = 'cliente_activo_memo';

    private const MEMO_OPCIONES = 'cliente_activo_opciones';

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

        // Se memoiza en la petición (no en una estática: viviría entre tests y peticiones del mismo proceso)
        // porque el menú, la barra superior y el controlador lo piden varias veces por página.
        $atributos = request()->attributes;
        $memo = $atributos->get(self::MEMO);

        if (is_array($memo) && $memo['id'] === (int) $id) {
            return $memo['cliente'];
        }

        $cliente = Cliente::query()->find((int) $id);

        if ($cliente === null) {
            session()->forget(self::CLAVE);
        }

        $atributos->set(self::MEMO, ['id' => (int) $id, 'cliente' => $cliente]);

        return $cliente;
    }

    /**
     * Para listas con filtro propio por cliente: si la URL no trae `$parametro`, el cliente activo se
     * inyecta como si lo hubiera traído (así filtro, exportación y selector coinciden) y se devuelve
     * para el aviso. Un parámetro explícito manda sobre el activo y no genera aviso.
     */
    public static function aplicarA(\Illuminate\Http\Request $request, string $parametro = 'cliente_id'): ?Cliente
    {
        if ($request->filled($parametro)) {
            return null;
        }

        $cliente = self::modelo();

        if ($cliente !== null) {
            $request->merge([$parametro => $cliente->id]);
        }

        return $cliente;
    }

    public static function establecer(?int $id): void
    {
        request()->attributes->remove(self::MEMO);

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
        // Menú y barra superior pintan el mismo selector: una sola consulta por petición.
        $atributos = request()->attributes;

        if (! $atributos->has(self::MEMO_OPCIONES)) {
            $atributos->set(self::MEMO_OPCIONES, Cliente::query()->orderBy('empresa')->get(['id', 'empresa', 'nombre']));
        }

        return $atributos->get(self::MEMO_OPCIONES);
    }
}
