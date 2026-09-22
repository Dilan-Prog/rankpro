<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Exceptions\ErrorDeDominio;
use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\Role;
use App\Support\Api\ConsultaOpciones;
use App\Support\Api\Respuesta;
use App\Support\Api\Serializador;
use App\Support\Reglas\Roles as ReglasRoles;
use Illuminate\Http\Request;

class RolesApiController extends ControladorApi
{
    public function index(Request $request)
    {
        return $this->listar(Role::query(), $request, new ConsultaOpciones(
            buscarEn: ['name', 'label'],
            ordenables: ['id', 'name', 'created_at', 'updated_at'],
            incluibles: ['permissions'],
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate(ReglasRoles::guardar(), ReglasRoles::mensajes());

        $role = Role::create([
            'name' => $data['name'],
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
        ]);
        $role->permissions()->sync($data['permissions'] ?? []);

        return Respuesta::recurso(Serializador::modelo($role->fresh('permissions'), ['permissions']), 201);
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate(ReglasRoles::guardar($role), ReglasRoles::mensajes());

        $role->update([
            'name' => $data['name'],
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
        ]);
        $role->permissions()->sync($data['permissions'] ?? []);

        return Respuesta::recurso(Serializador::modelo($role->fresh('permissions'), ['permissions']));
    }

    public function destroy(Role $role)
    {
        if ($role->users()->exists()) {
            throw new ErrorDeDominio("No puedes eliminar el rol \"{$role->label}\" porque tiene usuarios asignados. Reasígnalos primero.", 'role');
        }

        $role->delete();

        return Respuesta::eliminado();
    }
}
