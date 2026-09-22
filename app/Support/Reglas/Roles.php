<?php

namespace App\Support\Reglas;

use App\Models\Role;
use Illuminate\Validation\Rule;

/**
 * Reglas de validación de Role, compartidas entre el controlador web
 * (Admin\RolesController) y la API (Api\V1\Crm\RolesApiController).
 */
class Roles
{
    public static function guardar(?Role $role = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/',
                $role ? Rule::unique('roles', 'name')->ignore($role->id) : Rule::unique('roles', 'name'),
            ],
            'label' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    public static function mensajes(): array
    {
        return [
            'name.regex' => 'El identificador solo puede tener minúsculas, números y guion bajo.',
        ];
    }
}
