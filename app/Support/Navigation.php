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
 */
class Navigation
{
    /**
     * Grupo del menú que trabaja con el cliente activo (selector en el menú).
     */
    public const GRUPO_MARCA = 'Marca';

    /**
     * Todos los enlaces, sin filtrar por permiso ni por existencia de la ruta.
     *
     * Los módulos nuevos se listan aquí desde el inicio: mientras su ruta no
     * exista se omiten solos en groups(), así que dar de alta un módulo es
     * crear sus rutas, sin tocar este archivo.
     *
     * @return array<string, array<int, array{route: string, active: string, icon: string, label: string, permiso: string}>>
     */
    public static function todos(): array
    {
        return [
            'General' => [
                ['route' => 'admin.inicio', 'active' => 'admin.inicio', 'icon' => 'fa-house', 'label' => 'Inicio', 'permiso' => 'inicio'],
                ['route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => 'fa-gauge-high', 'label' => 'Dashboard', 'permiso' => 'dashboard'],
            ],
            'Agencia' => [
                ['route' => 'admin.mi-espacio.index', 'active' => 'admin.mi-espacio.*', 'icon' => 'fa-circle-check', 'label' => 'Mi espacio', 'permiso' => 'mi-espacio'],
                ['route' => 'admin.calendario.index', 'active' => 'admin.calendario.*', 'icon' => 'fa-calendar-days', 'label' => 'Calendario', 'permiso' => 'calendario'],
                ['route' => 'admin.clientes.index', 'active' => 'admin.clientes.*', 'icon' => 'fa-users', 'label' => 'CRM Clientes', 'permiso' => 'clientes'],
                ['route' => 'admin.servicios.index', 'active' => 'admin.servicios.*', 'icon' => 'fa-briefcase', 'label' => 'Servicios', 'permiso' => 'servicios'],
                ['route' => 'admin.agenda.index', 'active' => 'admin.agenda.*', 'icon' => 'fa-calendar-check', 'label' => 'Agenda', 'permiso' => 'agenda'],
                ['route' => 'admin.propuestas.index', 'active' => 'admin.propuestas.*', 'icon' => 'fa-file-signature', 'label' => 'Propuestas', 'permiso' => 'propuestas'],
                ['route' => 'admin.reportes.index', 'active' => 'admin.reportes.*', 'icon' => 'fa-file-lines', 'label' => 'Reportes', 'permiso' => 'reportes'],
                ['route' => 'admin.conversiones.index', 'active' => 'admin.conversiones.*', 'icon' => 'fa-filter', 'label' => 'Conversiones', 'permiso' => 'conversiones'],
                ['route' => 'admin.automatizaciones.index', 'active' => 'admin.automatizaciones.*', 'icon' => 'fa-robot', 'label' => 'Automatizaciones', 'permiso' => 'automatizaciones'],
                ['route' => 'admin.blog.index', 'active' => 'admin.blog.*', 'icon' => 'fa-newspaper', 'label' => 'Blog', 'permiso' => 'blog'],
                ['route' => 'admin.captura.index', 'active' => 'admin.captura.*', 'icon' => 'fa-clipboard-check', 'label' => 'Pendientes de captura', 'permiso' => 'captura'],
            ],
            self::GRUPO_MARCA => [
                ['route' => 'admin.identidad.index', 'active' => 'admin.identidad.*', 'icon' => 'fa-wand-magic-sparkles', 'label' => 'Identidad de marca', 'permiso' => 'identidad'],
                ['route' => 'admin.audiencia.index', 'active' => 'admin.audiencia.*', 'icon' => 'fa-people-group', 'label' => 'Audiencia', 'permiso' => 'audiencia'],
                ['route' => 'admin.seo-plan.index', 'active' => 'admin.seo-plan.*', 'icon' => 'fa-chart-line', 'label' => 'SEO Plan', 'permiso' => 'seo-plan'],
                ['route' => 'admin.contenido.index', 'active' => 'admin.contenido.*', 'icon' => 'fa-folder-tree', 'label' => 'Contenido', 'permiso' => 'contenido'],
                ['route' => 'admin.metricas.index', 'active' => 'admin.metricas.*', 'icon' => 'fa-chart-line', 'label' => 'Métricas', 'permiso' => 'metricas'],
                ['route' => 'admin.seo.index', 'active' => 'admin.seo.*', 'icon' => 'fa-magnifying-glass', 'label' => 'Módulo SEO', 'permiso' => 'seo'],
                ['route' => 'admin.keywords.index', 'active' => 'admin.keywords.*', 'icon' => 'fa-key', 'label' => 'Keywords', 'permiso' => 'keywords'],
                ['route' => 'admin.ads.index', 'active' => 'admin.ads.*', 'icon' => 'fa-bullhorn', 'label' => 'Módulo Ads', 'permiso' => 'ads'],
                ['route' => 'admin.desarrollo.index', 'active' => 'admin.desarrollo.*', 'icon' => 'fa-code', 'label' => 'Desarrollo', 'permiso' => 'desarrollo'],
            ],
            'Administración' => [
                ['route' => 'admin.finanzas.index', 'active' => 'admin.finanzas.*', 'icon' => 'fa-dollar-sign', 'label' => 'Finanzas', 'permiso' => 'finanzas'],
                ['route' => 'admin.archivos.index', 'active' => 'admin.archivos.*', 'icon' => 'fa-folder-open', 'label' => 'Archivos', 'permiso' => 'archivos'],
                ['route' => 'admin.correo.plantillas.index', 'active' => 'admin.correo.plantillas.*', 'icon' => 'fa-envelope-open-text', 'label' => 'Plantillas de correo', 'permiso' => 'correo'],
                ['route' => 'admin.correo.envios.index', 'active' => 'admin.correo.envios.*', 'icon' => 'fa-paper-plane', 'label' => 'Enviar correo', 'permiso' => 'correo'],
                ['route' => 'admin.usuarios.index', 'active' => 'admin.usuarios.*', 'icon' => 'fa-users-gear', 'label' => 'Usuarios', 'permiso' => 'usuarios'],
                ['route' => 'admin.roles.index', 'active' => 'admin.roles.*', 'icon' => 'fa-user-shield', 'label' => 'Roles', 'permiso' => 'roles'],
                ['route' => 'admin.integraciones.index', 'active' => 'admin.integraciones.*', 'icon' => 'fa-plug', 'label' => 'Integraciones', 'permiso' => 'integraciones'],
                ['route' => 'admin.configuracion.smtp.edit', 'active' => 'admin.configuracion.smtp.*', 'icon' => 'fa-server', 'label' => 'Configuración de correo', 'permiso' => 'configuracion'],
                ['route' => 'admin.configuracion.medicion.edit', 'active' => 'admin.configuracion.medicion.*', 'icon' => 'fa-chart-pie', 'label' => 'Medición y Google', 'permiso' => 'configuracion'],
            ],
        ];
    }

    /**
     * Enlaces que existen y que el usuario autenticado puede abrir; los grupos
     * que quedan vacíos se descartan.
     *
     * @return array<string, array<int, array{route: string, active: string, icon: string, label: string, permiso: string}>>
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
}
