<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * One permission per admin module.
     */
    public function run(): void
    {
        $modules = [
            ['name' => 'dashboard', 'label' => 'Dashboard', 'module' => 'dashboard'],
            ['name' => 'clientes', 'label' => 'CRM Clientes', 'module' => 'clientes'],
            ['name' => 'servicios', 'label' => 'Servicios', 'module' => 'servicios'],
            ['name' => 'seo', 'label' => 'Módulo SEO', 'module' => 'seo'],
            ['name' => 'keywords', 'label' => 'Keywords', 'module' => 'keywords'],
            ['name' => 'ads', 'label' => 'Módulo Ads', 'module' => 'ads'],
            ['name' => 'desarrollo', 'label' => 'Desarrollo', 'module' => 'desarrollo'],
            ['name' => 'finanzas', 'label' => 'Finanzas', 'module' => 'finanzas'],
            ['name' => 'archivos', 'label' => 'Archivos', 'module' => 'archivos'],
            ['name' => 'integraciones', 'label' => 'Integraciones', 'module' => 'integraciones'],
            ['name' => 'roles', 'label' => 'Roles y Permisos', 'module' => 'roles'],
        ];

        foreach ($modules as $module) {
            Permission::updateOrCreate(['name' => $module['name']], $module);
        }
    }
}
