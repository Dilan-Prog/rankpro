<?php

namespace App\Support\Portal;

use App\Models\Cliente;
use Illuminate\Support\Facades\Storage;

/**
 * Identidad visual del portal: logo, slogan y color primario del cliente, o la de RankPro si no hay.
 *
 * LISTA BLANCA: solo logo (como bandera; se sirve por una ruta del propio portal), slogan y UN color.
 * El color se valida como #RRGGBB antes de llegar a un <style>, así que no puede inyectar CSS.
 */
class Marca
{
    public const COLOR_RANKPRO = '#0F9D6E';

    /** @return array{nombre: string, slogan: ?string, color: string, sobre_color: string, logo: bool, propia: bool} */
    public static function para(Cliente $cliente): array
    {
        $identidad = $cliente->identidad;
        $color = self::colorPrimario($identidad?->paleta);
        $logo = $identidad?->logo_ruta && Storage::disk('local')->exists($identidad->logo_ruta);
        $slogan = trim((string) $identidad?->slogan);

        return [
            'nombre' => $cliente->nombre_comercial,
            'slogan' => $slogan !== '' ? mb_substr($slogan, 0, 160) : null,
            'color' => $color ?? self::COLOR_RANKPRO,
            'sobre_color' => self::textoSobre($color ?? self::COLOR_RANKPRO),
            'logo' => (bool) $logo,
            'propia' => $color !== null || $logo,
        ];
    }

    /** Ruta del logo SOLO del cliente dado (la identidad es 1:1 con el cliente del enlace). */
    public static function rutaLogo(Cliente $cliente): ?string
    {
        $ruta = $cliente->identidad?->logo_ruta;

        return $ruta && Storage::disk('local')->exists($ruta) ? $ruta : null;
    }

    private static function colorPrimario(mixed $paleta): ?string
    {
        foreach ((array) $paleta as $color) {
            $hex = is_array($color) ? (string) ($color['hex'] ?? '') : '';
            if (preg_match('/^#[0-9A-Fa-f]{6}$/', $hex) === 1) {
                return strtoupper($hex);
            }
        }

        return null;
    }

    /** Negro o blanco según la luminancia, para que el texto sobre el color primario se lea. */
    private static function textoSobre(string $hex): string
    {
        [$r, $g, $b] = array_map('hexdec', str_split(ltrim($hex, '#'), 2));
        $luminancia = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminancia > 0.6 ? '#111827' : '#FFFFFF';
    }
}
