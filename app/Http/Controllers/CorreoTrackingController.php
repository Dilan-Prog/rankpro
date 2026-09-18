<?php

namespace App\Http\Controllers;

use App\Models\CorreoDestinatario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Medición de correos: píxel de apertura y redirección de clics. Rutas
 * públicas sin sesión (las llama el cliente de correo del destinatario).
 *
 * Ambas responden siempre "bien" aunque el token no exista: un 404 en el
 * píxel se ve como imagen rota en algunos clientes, y un enlace firmado que
 * deja de redirigir castigaría al lector por un registro borrado.
 */
class CorreoTrackingController extends Controller
{
    /** GIF de 1×1 transparente. */
    private const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function abierto(Request $request, string $token): Response
    {
        $destinatario = CorreoDestinatario::where('token', $token)->first();

        if ($destinatario) {
            $destinatario->eventos()->create([
                'tipo' => 'apertura',
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
                'created_at' => now(),
            ]);

            $destinatario->aperturas = $destinatario->aperturas + 1;
            if ($destinatario->primera_apertura_en === null) {
                $destinatario->primera_apertura_en = now();
            }
            $destinatario->save();
        }

        return response(base64_decode(self::PIXEL), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function clic(Request $request, string $token): RedirectResponse|Response
    {
        $url = (string) $request->query('u', '');

        // Solo http(s): la URL viene firmada, pero un `javascript:` nunca debe
        // salir de aquí como redirección.
        if (! preg_match('#^https?://#i', $url)) {
            return response('Enlace no válido.', 400);
        }

        $destinatario = CorreoDestinatario::where('token', $token)->first();

        if ($destinatario) {
            $destinatario->eventos()->create([
                'tipo' => 'clic',
                'url' => mb_substr($url, 0, 2000),
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
                'created_at' => now(),
            ]);
            $destinatario->increment('clics');
        }

        return redirect()->away($url, 302);
    }
}
