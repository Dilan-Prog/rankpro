<?php

namespace App\Support\Contenido;

use Illuminate\Support\Str;
use League\CommonMark\Node\Node;
use League\CommonMark\Normalizer\TextNormalizerInterface;

/**
 * Genera los `id` de los encabezados del blog en ASCII.
 *
 * El normalizador que trae CommonMark conserva los acentos, asi que "Qué es INP"
 * produce id="qué-es-inp". Funciona, pero el navegador lo percent-codifica al
 * copiar el enlace (#qu%C3%A9-es-inp), que es feo de compartir y ensucia los
 * informes de Search Console cuando Google enlaza a una seccion concreta desde
 * los resultados.
 *
 * Str::slug hace la transliteracion a ASCII y ya se usa en el resto del proyecto.
 */
class NormalizadorSlug implements TextNormalizerInterface
{
    public function normalize(string $text, array $context = []): string
    {
        $slug = Str::slug($text, '-', 'es');

        // Un encabezado solo de simbolos o de emojis deja el slug vacio; en ese
        // caso se genera uno estable a partir del texto para no perder el ancla.
        return $slug !== '' ? $slug : 'seccion-'.substr(md5($text), 0, 8);
    }
}
