<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Exceptions\ErrorDeDominio;
use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\User;
use App\Support\Api\ConsultaOpciones;
use App\Support\Api\Respuesta;
use App\Support\Api\Serializador;
use App\Support\Reglas\Usuarios as ReglasUsuarios;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UsuariosApiController extends ControladorApi
{
    public function index(Request $request)
    {
        return $this->listar(User::query(), $request, new ConsultaOpciones(
            buscarEn: ['name', 'email'],
            filtrosExactos: ['is_active', 'role_id', 'area'],
            ordenables: ['id', 'name', 'created_at', 'updated_at'],
            incluibles: ['role'],
        ));
    }

    public function show(User $user)
    {
        return Respuesta::recurso(Serializador::modelo($user));
    }

    /**
     * Si no viene password, genera una aleatoria y la devuelve UNA VEZ como
     * password_temporal (el modelo la oculta en cualquier otra respuesta).
     */
    public function store(Request $request)
    {
        $data = $request->validate(array_merge(ReglasUsuarios::datos(), ReglasUsuarios::passwordAlCrear()));

        $passwordTemporal = null;
        if (blank($data['password'] ?? null)) {
            $passwordTemporal = Str::password(14);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'] ?? $passwordTemporal,
            'role_id' => $data['role_id'] ?? null,
            'area' => $data['area'] ?? null,
            'telefono' => $data['telefono'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $respuesta = Serializador::modelo($user->fresh());
        if ($passwordTemporal) {
            $respuesta['password_temporal'] = $passwordTemporal;
        }

        return Respuesta::recurso($respuesta, 201);
    }

    /** No permite cambiar el password aquí — para eso está password(). */
    public function update(Request $request, User $user)
    {
        $data = $request->validate(ReglasUsuarios::datos($user));

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'role_id' => $data['role_id'] ?? null,
            'area' => $data['area'] ?? null,
            'telefono' => $data['telefono'] ?? null,
            'is_active' => $request->boolean('is_active', $user->is_active),
        ]);

        return Respuesta::recurso(Serializador::modelo($user->fresh()));
    }

    public function password(Request $request, User $user)
    {
        $data = $request->validate(ReglasUsuarios::passwordCambio());

        $user->update(['password' => $data['password']]);

        return Respuesta::mensaje('Contraseña actualizada.');
    }

    /**
     * Misma lógica que Admin\UsersController::deactivate() (nunca puede
     * desactivarse a sí mismo) y además revoca sus tokens de Sanctum: una
     * cuenta desactivada no debe seguir pudiendo usar la API.
     */
    public function desactivar(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            throw new ErrorDeDominio('No puedes desactivar tu propia cuenta.', 'is_active');
        }

        $user->update(['is_active' => false]);
        $user->tokens()->delete();

        return Respuesta::recurso(Serializador::modelo($user->fresh()));
    }
}
