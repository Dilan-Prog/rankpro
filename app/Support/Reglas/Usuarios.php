<?php

namespace App\Support\Reglas;

use App\Enums\AreaUsuario;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Reglas de validación de User, compartidas entre el controlador web
 * (Admin\UsersController) y la API (Api\V1\Crm\UsuariosApiController).
 * El password se valida aparte de los datos base porque cada flujo lo
 * trata distinto: el formulario web siempre lo pide confirmado; la API de
 * creación lo hace opcional (genera uno aleatorio si no viene) y el cambio
 * de password por API no exige confirmación (no hay campo duplicado que
 * confirmar en una llamada servidor-a-servidor).
 */
class Usuarios
{
    public static function datos(?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', $user ? Rule::unique('users', 'email')->ignore($user->id) : Rule::unique('users', 'email')],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'area' => ['nullable', Rule::enum(AreaUsuario::class)],
            'telefono' => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** Alta desde el formulario web: password obligatorio y confirmado. */
    public static function passwordConfirmada(): array
    {
        return ['password' => ['required', 'confirmed', Password::defaults()]];
    }

    /** Alta desde la API: password opcional (si no viene, se genera uno aleatorio). */
    public static function passwordAlCrear(): array
    {
        return ['password' => ['nullable', Password::defaults()]];
    }

    /** Cambio de password vía API (POST /usuarios/{user}/password). */
    public static function passwordCambio(): array
    {
        return ['password' => ['required', Password::defaults()]];
    }
}
