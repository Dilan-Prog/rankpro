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
    private const RUTA = 'images/rankpro-logo-black.png';

    /** Data URI del logo, o null si el fichero no está en el servidor. */
    public static function dataUri(): ?string
    {
        $ruta = public_path(self::RUTA);

        if (! is_file($ruta)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($ruta));
    }
}
