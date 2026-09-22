<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracionSmtp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Configuración de correo (SMTP): una fila única (id=1) que, activada,
 * sobreescribe los MAIL_* del .env en cada petición (ver
 * App\Support\ConfiguracionSmtpAplicador, enganchado en
 * AppServiceProvider::boot()). Sirve para no tener que editar el .env del
 * servidor a mano cada vez que cambia el correo/contraseña del SMTP.
 */
class ConfiguracionSmtpController extends Controller
{
    public function edit(): View
    {
        $config = ConfiguracionSmtp::actual();

        return view('admin.configuracion.smtp', [
            'pageTitle' => 'Configuración de correo',
            'config' => $config,
            'tieneContrasena' => (bool) $config?->password,
            'envActivo' => [
                'host' => (string) config('mail.mailers.smtp.host'),
                'port' => (string) config('mail.mailers.smtp.port'),
                'from' => (string) config('mail.from.address'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $config = ConfiguracionSmtp::query()->find(1) ?? new ConfiguracionSmtp();

        // Contraseña en blanco = "no la toques": así no hace falta re-escribirla
        // en cada guardado solo por cambiar el host o el puerto.
        if (! filled($data['password'] ?? null)) {
            unset($data['password']);
        }

        $config->forceFill($data + ['id' => 1, 'actualizado_por' => Auth::id()])->save();

        return redirect()->route('admin.configuracion.smtp.edit')
            ->with('status', $config->activa
                ? 'Guardado. Esta configuración ya está activa: los correos del panel salen por aquí.'
                : 'Guardado, pero sigue desactivada: los correos del panel siguen saliendo por el .env del servidor.');
    }

    /**
     * Manda un correo de prueba con los datos QUE HAY AHORA MISMO en el
     * formulario (no necesariamente guardados todavía), igual que "Enviarme
     * una prueba" en Enviar correo: para poder verificar antes de comprometerse.
     */
    public function probar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'host' => ['required', 'string', 'max:255'],
            'puerto' => ['required', 'integer', 'min:1', 'max:65535'],
            'cifrado' => ['nullable', 'in:tls,ssl'],
            'usuario' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'remitente_email' => ['nullable', 'email', 'max:255'],
            'remitente_nombre' => ['nullable', 'string', 'max:255'],
        ]);

        // Si dejó la contraseña en blanco (probando sin tocarla) se usa la ya
        // guardada; si nunca hubo una, no hay con qué autenticar.
        $password = filled($data['password'] ?? null) ? $data['password'] : ConfiguracionSmtp::actual()?->password;
        if (blank($password)) {
            return response()->json(['message' => 'Escribe la contraseña del SMTP para poder probar.'], 422);
        }

        Config::set([
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $data['host'],
            'mail.mailers.smtp.port' => $data['puerto'],
            'mail.mailers.smtp.encryption' => $data['cifrado'] ?? null,
            'mail.mailers.smtp.username' => $data['usuario'] ?? null,
            'mail.mailers.smtp.password' => $password,
        ]);

        $remitente = $data['remitente_email'] ?? config('mail.from.address');

        try {
            Mail::mailer('smtp')->raw(
                'Este es un correo de prueba de la configuración SMTP de RankPro. Si lo recibiste, la conexión funciona.',
                function ($message) use ($data, $remitente) {
                    $message->to($data['email'])
                        ->subject('[Prueba] Configuración SMTP')
                        ->from($remitente, $data['remitente_nombre'] ?? null);
                }
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => 'No se pudo enviar: '.$e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'mensaje' => "Prueba enviada a {$data['email']}."]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'puerto' => ['required', 'integer', 'min:1', 'max:65535'],
            'cifrado' => ['nullable', 'in:tls,ssl'],
            'usuario' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'remitente_email' => ['nullable', 'email', 'max:255'],
            'remitente_nombre' => ['nullable', 'string', 'max:255'],
        ]);

        // Checkbox: si no viene marcado, el navegador ni siquiera manda el
        // campo — $request->boolean() ya maneja eso (false por defecto).
        $data['activa'] = $request->boolean('activa');

        return $data;
    }
}
