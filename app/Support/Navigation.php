<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Single source of truth for the admin sidebar's grouped nav links, reused
 * by components/sidebar.blade.php (rendering) and CommandPaletteIndex
 * (search) so the module list only lives in one place.
 *
 * Cada enlace lleva el `permiso` (nombre en la tabla permissions) que exige
 * su módulo; groups() ya devuelve solo lo que el usuario puede abrir, así que
 * el menú y la paleta Ctrl+K nunca ofrecen un módulo que daría 403.
 *
 * El menú se organiza por flujo de trabajo (Hoy, Clientes, Cliente activo,
 * Reportes, Agencia, Más). Solo se reagrupa: los nombres de ruta y las URL no
 * cambian, por eso no hacen falta redirecciones.
 */
class Navigation
{
    /**
     * Grupo del menú que trabaja con el cliente activo (selector en el menú).
     */
    public const GRUPO_CLIENTE_ACTIVO = 'Cliente activo';

    /**
     * Grupo que nace cerrado: módulos que hoy no tienen datos en uso.
     */
    public const GRUPO_MAS = 'Más';

    /**
     * Todos los enlaces, sin filtrar por permiso ni por existencia de la ruta.
     *
     * Claves opcionales de cada enlace:
     * - `active`: patrón (o lista de patrones) de routeIs() que lo resalta.
     * - `seccion`: encabezado pequeño dentro del grupo (subgrupo visual).
     * - `requiere_cliente`: el módulo solo trabaja con el cliente activo.
     * - `param_cliente`: la ruta recibe el id del cliente activo (ver url()).
     * - `paleta`: etiqueta más descriptiva para la paleta Ctrl+K.
     *
     * Los módulos nuevos se listan aquí desde el inicio: mientras su ruta no
     * exista se omiten solos en groups(), así que dar de alta un módulo es
     * crear sus rutas, sin tocar este archivo.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function todos(): array
    {
        return [
            'Hoy' => [
                ['route' => 'admin.inicio', 'active' => 'admin.inicio', 'icon' => 'fa-house', 'label' => 'Inicio', 'permiso' => 'inicio'],
                ['route' => 'admin.calendario.index', 'active' => 'admin.calendario.*', 'icon' => 'fa-calendar-days', 'label' => 'Calendario', 'permiso' => 'calendario'],
                ['route' => 'admin.captura.index', 'active' => 'admin.captura.*', 'icon' => 'fa-clipboard-check', 'label' => 'Pendientes de captura', 'permiso' => 'captura'],
            ],
            'Clientes' => [
                // La lista no usa admin.clientes.*: el Resumen (clientes.show) tiene su propio ítem y no deben resaltarse a la vez.
                ['route' => 'admin.clientes.index', 'active' => ['admin.clientes.index', 'admin.clientes.store', 'admin.clientes.update', 'admin.clientes.destroy', 'admin.clientes.clics*', 'admin.clientes.conversiones*', 'admin.clientes.embudo.*', 'admin.clientes.integraciones*'], 'icon' => 'fa-users', 'label' => 'Lista', 'permiso' => 'clientes', 'paleta' => 'Lista de clientes'],
                ['route' => 'admin.servicios.index', 'active' => 'admin.servicios.*', 'icon' => 'fa-briefcase', 'label' => 'Servicios y contratos', 'permiso' => 'servicios'],
                ['route' => 'admin.propuestas.index', 'active' => 'admin.propuestas.*', 'icon' => 'fa-file-signature', 'label' => 'Propuestas', 'permiso' => 'propuestas'],
                ['route' => 'admin.conversiones.index', 'active' => 'admin.conversiones.*', 'icon' => 'fa-filter', 'label' => 'Conversiones', 'permiso' => 'conversiones'],
            ],
            self::GRUPO_CLIENTE_ACTIVO => [
                ['route' => 'admin.clientes.show', 'active' => 'admin.clientes.show', 'icon' => 'fa-address-card', 'label' => 'Resumen', 'permiso' => 'clientes', 'requiere_cliente' => true, 'param_cliente' => true, 'paleta' => 'Resumen del cliente activo'],
                ['route' => 'admin.identidad.index', 'active' => 'admin.identidad.*', 'icon' => 'fa-wand-magic-sparkles', 'label' => 'Identidad', 'permiso' => 'identidad', 'seccion' => 'Marca', 'requiere_cliente' => true, 'paleta' => 'Identidad de marca'],
                ['route' => 'admin.audiencia.index', 'active' => 'admin.audiencia.*', 'icon' => 'fa-people-group', 'label' => 'Audiencia', 'permiso' => 'audiencia', 'seccion' => 'Marca', 'requiere_cliente' => true],
                ['route' => 'admin.seo-plan.index', 'active' => 'admin.seo-plan.*', 'icon' => 'fa-chart-line', 'label' => 'SEO Plan', 'permiso' => 'seo-plan', 'seccion' => 'Estrategia', 'requiere_cliente' => true],
                ['route' => 'admin.contenido.index', 'active' => 'admin.contenido.*', 'icon' => 'fa-folder-tree', 'label' => 'Contenido', 'permiso' => 'contenido', 'seccion' => 'Estrategia', 'requiere_cliente' => true],
                ['route' => 'admin.seo.index', 'active' => 'admin.seo.*', 'icon' => 'fa-magnifying-glass', 'label' => 'SEO', 'permiso' => 'seo', 'seccion' => 'Seguimiento', 'paleta' => 'Módulo SEO'],
                ['route' => 'admin.keywords.index', 'active' => 'admin.keywords.*', 'icon' => 'fa-key', 'label' => 'Keywords', 'permiso' => 'keywords', 'seccion' => 'Seguimiento'],
                ['route' => 'admin.ads.index', 'active' => 'admin.ads.*', 'icon' => 'fa-bullhorn', 'label' => 'Ads', 'permiso' => 'ads', 'seccion' => 'Seguimiento', 'paleta' => 'Módulo Ads'],
                ['route' => 'admin.metricas.index', 'active' => 'admin.metricas.*', 'icon' => 'fa-chart-line', 'label' => 'Métricas', 'permiso' => 'metricas', 'seccion' => 'Seguimiento'],
                ['route' => 'admin.desarrollo.index', 'active' => 'admin.desarrollo.*', 'icon' => 'fa-code', 'label' => 'Desarrollo', 'permiso' => 'desarrollo'],
                // Misma pantalla que «Reportes > Todos» (ya filtra por el cliente activo): no se resalta para no marcar dos ítems a la vez.
                ['route' => 'admin.reportes.index', 'active' => [], 'icon' => 'fa-file-lines', 'label' => 'Reportes del cliente', 'permiso' => 'reportes'],
            ],
            'Reportes' => [
                ['route' => 'admin.reportes.index', 'active' => ['admin.reportes.index', 'admin.reportes.show', 'admin.reportes.store', 'admin.reportes.update', 'admin.reportes.destroy', 'admin.reportes.pdf', 'admin.reportes.preview', 'admin.reportes.xlsx', 'admin.reportes.secciones.*'], 'icon' => 'fa-file-lines', 'label' => 'Todos', 'permiso' => 'reportes', 'paleta' => 'Reportes'],
                ['route' => 'admin.reportes.tablero.index', 'active' => 'admin.reportes.tablero.*', 'icon' => 'fa-table-columns', 'label' => 'Tablero', 'permiso' => 'reportes', 'paleta' => 'Tablero de reportes'],
                ['route' => 'admin.reportes.programados.index', 'active' => 'admin.reportes.programados.*', 'icon' => 'fa-clock', 'label' => 'Programados', 'permiso' => 'reportes', 'paleta' => 'Reportes programados'],
            ],
            'Agencia' => [
                ['route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => 'fa-gauge-high', 'label' => 'Dashboard', 'permiso' => 'dashboard'],
                ['route' => 'admin.finanzas.index', 'active' => 'admin.finanzas.*', 'icon' => 'fa-dollar-sign', 'label' => 'Finanzas', 'permiso' => 'finanzas'],
                ['route' => 'admin.agenda.index', 'active' => 'admin.agenda.*', 'icon' => 'fa-calendar-check', 'label' => 'Agenda pública', 'permiso' => 'agenda', 'paleta' => 'Agenda'],
                ['route' => 'admin.archivos.index', 'active' => 'admin.archivos.*', 'icon' => 'fa-folder-open', 'label' => 'Archivos', 'permiso' => 'archivos'],
                ['route' => 'admin.correo.plantillas.index', 'active' => 'admin.correo.plantillas.*', 'icon' => 'fa-envelope-open-text', 'label' => 'Plantillas de correo', 'permiso' => 'correo'],
                ['route' => 'admin.correo.envios.index', 'active' => 'admin.correo.envios.*', 'icon' => 'fa-paper-plane', 'label' => 'Enviar correo', 'permiso' => 'correo'],
                ['route' => 'admin.usuarios.index', 'active' => 'admin.usuarios.*', 'icon' => 'fa-users-gear', 'label' => 'Usuarios', 'permiso' => 'usuarios'],
                ['route' => 'admin.roles.index', 'active' => 'admin.roles.*', 'icon' => 'fa-user-shield', 'label' => 'Roles', 'permiso' => 'roles'],
                ['route' => 'admin.integraciones.index', 'active' => 'admin.integraciones.*', 'icon' => 'fa-plug', 'label' => 'Integraciones', 'permiso' => 'integraciones'],
                ['route' => 'admin.configuracion.smtp.edit', 'active' => 'admin.configuracion.smtp.*', 'icon' => 'fa-server', 'label' => 'Configuración de correo', 'permiso' => 'configuracion'],
                ['route' => 'admin.configuracion.medicion.edit', 'active' => 'admin.configuracion.medicion.*', 'icon' => 'fa-chart-pie', 'label' => 'Medición y Google', 'permiso' => 'configuracion'],
            ],
            self::GRUPO_MAS => [
                ['route' => 'admin.mi-espacio.index', 'active' => 'admin.mi-espacio.*', 'icon' => 'fa-circle-check', 'label' => 'Mi espacio', 'permiso' => 'mi-espacio'],
                ['route' => 'admin.automatizaciones.index', 'active' => 'admin.automatizaciones.*', 'icon' => 'fa-robot', 'label' => 'Automatizaciones', 'permiso' => 'automatizaciones'],
                ['route' => 'admin.blog.index', 'active' => 'admin.blog.*', 'icon' => 'fa-newspaper', 'label' => 'Blog', 'permiso' => 'blog'],
            ],
        ];
    }

    /**
     * Enlaces que existen y que el usuario autenticado puede abrir; los grupos
     * que quedan vacíos se descartan.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function groups(): array
    {
        $usuario = auth()->user();
        $grupos = [];

        foreach (self::todos() as $grupo => $enlaces) {
            $visibles = array_values(array_filter(
                $enlaces,
                static fn (array $enlace): bool => Route::has($enlace['route'])
                    && ($usuario === null || $usuario->tienePermiso($enlace['permiso']))
            ));

            if ($visibles !== []) {
                $grupos[$grupo] = $visibles;
            }
        }

        return $grupos;
    }

    /**
     * ¿Este enlace está activo en la petición actual? `active` puede ser un patrón o una lista.
     */
    public static function estaActivo(array $enlace): bool
    {
        $patrones = (array) $enlace['active'];

        return $patrones !== [] && request()->routeIs(...$patrones);
    }

    /**
     * URL del enlace. Los que llevan el cliente activo (Resumen) sin cliente elegido caen a la
     * lista de clientes en vez de fallar por falta de parámetro.
     */
    public static function url(array $enlace): string
    {
        if (! empty($enlace['param_cliente'])) {
            $cliente = ClienteActivo::modelo();

            return $cliente !== null
                ? route($enlace['route'], $cliente->id)
                : route('admin.clientes.index');
        }

        return route($enlace['route']);
    }

    /**
     * Enlace que necesita un cliente activo y no hay ninguno elegido.
     */
    public static function pideCliente(array $enlace): bool
    {
        return ! empty($enlace['requiere_cliente']) && ClienteActivo::id() === null;
    }
}
