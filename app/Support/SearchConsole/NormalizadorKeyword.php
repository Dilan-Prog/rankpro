<?php

namespace App\Support\SearchConsole;

use Illuminate\Support\Str;

/** Misma regla que el "pegar desde Search Console" de Keywords: minúsculas, sin acentos, espacios colapsados. */
class NormalizadorKeyword
{
    public static function normalizar(string $keyword): string
    {
        $texto = Str::lower(Str::ascii($keyword));

        return trim(preg_replace('/\s+/', ' ', $texto) ?? '');
    }
}
