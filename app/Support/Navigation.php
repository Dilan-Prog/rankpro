<?php

namespace App\Support;

/**
 * Single source of truth for the admin sidebar's grouped nav links, reused
 * by components/sidebar.blade.php (rendering) and CommandPaletteIndex
 * (search) so the module list only lives in one place.
 */
class Navigation
{
    /**
     * @return array<string, array<int, array{route: string, active: string, icon: string, label: string}>>
     */
    public static function groups(): array
    {
        return [
            'General' => [
                ['route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => 'fa-gauge-high', 'label' => 'Dashboard'],
            ],
            'Operación' => [
                ['route' => 'admin.clientes.index', 'active' => 'admin.clientes.*', 'icon' => 'fa-users', 'label' => 'CRM Clientes'],
                ['route' => 'admin.servicios.index', 'active' => 'admin.servicios.*', 'icon' => 'fa-briefcase', 'label' => 'Servicios'],
                ['route' => 'admin.seo.index', 'active' => 'admin.seo.*', 'icon' => 'fa-magnifying-glass', 'label' => 'Módulo SEO'],
                ['route' => 'admin.keywords.index', 'active' => 'admin.keywords.*', 'icon' => 'fa-key', 'label' => 'Keywords'],
                ['route' => 'admin.blog.index', 'active' => 'admin.blog.*', 'icon' => 'fa-newspaper', 'label' => 'Blog'],
                ['route' => 'admin.ads.index', 'active' => 'admin.ads.*', 'icon' => 'fa-bullhorn', 'label' => 'Módulo Ads'],
                ['route' => 'admin.automatizaciones.index', 'active' => 'admin.automatizaciones.*', 'icon' => 'fa-robot', 'label' => 'Automatizaciones'],
                ['route' => 'admin.conversiones.index', 'active' => 'admin.conversiones.*', 'icon' => 'fa-filter', 'label' => 'Conversiones'],
                ['route' => 'admin.reportes.index', 'active' => 'admin.reportes.*', 'icon' => 'fa-file-lines', 'label' => 'Reportes'],
                ['route' => 'admin.desarrollo.index', 'active' => 'admin.desarrollo.*', 'icon' => 'fa-code', 'label' => 'Desarrollo'],
            ],
            'Administración' => [
                ['route' => 'admin.finanzas.index', 'active' => 'admin.finanzas.*', 'icon' => 'fa-dollar-sign', 'label' => 'Finanzas'],
                ['route' => 'admin.archivos.index', 'active' => 'admin.archivos.*', 'icon' => 'fa-folder-open', 'label' => 'Archivos'],
                ['route' => 'admin.usuarios.index', 'active' => 'admin.usuarios.*', 'icon' => 'fa-users-gear', 'label' => 'Usuarios'],
                ['route' => 'admin.roles.index', 'active' => 'admin.roles.*', 'icon' => 'fa-user-shield', 'label' => 'Roles'],
                ['route' => 'admin.integraciones.index', 'active' => 'admin.integraciones.*', 'icon' => 'fa-plug', 'label' => 'Integraciones'],
            ],
        ];
    }
}
