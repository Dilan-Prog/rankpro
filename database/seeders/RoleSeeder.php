<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Three starter roles, each granted a set of module permissions.
     * Adjustable later once a Roles admin screen exists.
     */
    public function run(): void
    {
        $allModules = Permission::pluck('id', 'name');

        $roles = [
            'admin' => [
                'label' => 'Administrador',
                'description' => 'Acceso total a todos los módulos.',
                'permissions' => $allModules->keys()->all(),
            ],
            'manager' => [
                'label' => 'Gerente',
                'description' => 'Gestiona operación de clientes y campañas, sin administrar roles ni integraciones.',
                'permissions' => ['dashboard', 'clientes', 'servicios', 'seo', 'keywords', 'ads', 'desarrollo', 'finanzas', 'archivos'],
            ],
            'viewer' => [
                'label' => 'Visualizador',
                'description' => 'Acceso de solo consulta a los módulos principales de reporting.',
                'permissions' => ['dashboard', 'clientes', 'seo', 'keywords', 'ads', 'finanzas'],
            ],
        ];

        foreach ($roles as $name => $data) {
            $role = Role::updateOrCreate(
                ['name' => $name],
                ['label' => $data['label'], 'description' => $data['description']]
            );

            $permissionIds = $allModules->only($data['permissions'])->values()->all();
            $role->permissions()->sync($permissionIds);
        }
    }
}
