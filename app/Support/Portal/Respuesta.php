<?php

namespace App\Support\Portal;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;

/**
 * Errores del portal. Una página propia (sin navbar del sitio) y con el mismo texto para enlace
 * vencido o revocado: quien prueba tokens no aprende si un enlace existió alguna vez.
 */
class Respuesta
{
    private const MENSAJES = [
        404 => ['No encontramos esta página', 'Revisa que el enlace esté completo o pide uno nuevo a tu contacto en RankPro.'],
        410 => ['Este enlace ya no está disponible', 'Pide uno nuevo a tu contacto en RankPro.'],
        429 => ['Demasiadas solicitudes', 'Espera un minuto y vuelve a intentarlo.'],
    ];

    public static function error(int $codigo): Response
    {
        [$titulo, $detalle] = self::MENSAJES[$codigo] ?? self::MENSAJES[404];

        return response()->view('portal.error', ['titulo' => $titulo, 'detalle' => $detalle, 'codigo' => $codigo], $codigo);
    }

    /** Para cortar desde un controlador conservando las cabeceras del middleware. */
    public static function abortar(int $codigo): never
    {
        throw new HttpResponseException(self::error($codigo));
    }
}
