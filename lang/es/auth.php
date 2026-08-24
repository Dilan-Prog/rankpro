<?php

/*
|--------------------------------------------------------------------------
| Mensajes de autenticación en español
|--------------------------------------------------------------------------
|
| Los usan App\Http\Requests\Auth\LoginRequest (auth.failed, auth.throttle)
| y App\Http\Controllers\Auth\ConfirmablePasswordController (auth.password).
|
*/

return [
    'failed' => 'Estas credenciales no coinciden con nuestros registros.',
    'password' => 'La contraseña es incorrecta.',
    'throttle' => 'Demasiados intentos de acceso. Intenta de nuevo en :seconds segundos.',
];
