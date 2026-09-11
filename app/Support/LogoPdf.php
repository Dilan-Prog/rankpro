<?php

namespace App\Support;

/**
 * El logotipo para los PDF, incrustado en base64.
 *
 * Ni ruta de disco ni URL, a propósito: una ruta la lee dompdf pero no el
 * navegador, y la vista previa —que renderiza el mismo Blade en un iframe—
 * mostraba la imagen rota; una URL la lee el navegador pero no dompdf, que
 * tiene el acceso remoto deshabilitado y tampoco entiende <picture> ni webp,
 * que es como sirve el logo el sitio público. El PNG en base64 vale para los
 * dos contextos y son 11 KB, una vez por documento.
 *
 * Compartido entre Reportes y Propuestas para que el próximo PDF que necesite
 * el logo lo tenga en una línea, y para que un cambio de fichero se haga en un
 * solo sitio.
 */
class LogoPdf
{
    /**
     * Dos versiones porque los documentos no comparten fondo: la portada de
     * Reportes es blanca y la de Propuestas es azul marino. El logo negro sobre
     * el marino desaparece —el texto es casi del mismo tono que el fondo—, que
     * es exactamente lo que pasó la primera vez.
     */
    private const RUTAS = [
        'negro' => 'images/rankpro-logo-black.png',
        'blanco' => 'images/rankpro-logo-white.png',
    ];

    /**
     * Data URI del logo, o null si el fichero no está en el servidor.
     *
     * @param  'negro'|'blanco'  $variante  negro para fondos claros, blanco para oscuros
     */
    public static function dataUri(string $variante = 'negro'): ?string
    {
        $ruta = public_path(self::RUTAS[$variante] ?? self::RUTAS['negro']);

        if (! is_file($ruta)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($ruta));
    }
}
