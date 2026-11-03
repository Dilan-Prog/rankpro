<?php

namespace App\Support;

/**
 * Saca los IDs de Google de un fragmento pegado (el bloque <script> que da Google).
 *
 * El dueño no tiene por qué saber qué parte del código es el ID: pega todo y aquí se buscan,
 * con regex, solo los valores que cumplen App\Support\FormatosGoogle. El texto pegado NUNCA se
 * guarda ni se devuelve: solo los IDs válidos (por eso es seguro mostrar el resultado).
 */
class ExtractorEtiquetasGoogle
{
    /**
     * @return array{gtm: ?string, ga4: ?string, ads: ?string, conversiones: list<string>}
     */
    public static function desde(string $texto): array
    {
        return [
            'gtm' => self::primero('/(?<![A-Za-z0-9_-])GTM-[A-Z0-9]{4,10}(?![A-Za-z0-9_-])/', $texto, [FormatosGoogle::class, 'gtm']),
            'ga4' => self::primero('/(?<![A-Za-z0-9_-])G-[A-Z0-9]{6,12}(?![A-Za-z0-9_-])/', $texto, [FormatosGoogle::class, 'ga4']),
            // «AW-123/etiqueta» también delata la cuenta: el «/» no cuenta como carácter de ID.
            'ads' => self::primero('/(?<![A-Za-z0-9_-])AW-\d{6,15}(?![A-Za-z0-9_-])/', $texto, [FormatosGoogle::class, 'ads']),
            'conversiones' => self::conversiones($texto),
        ];
    }

    /** @param callable(string): ?string $validar */
    private static function primero(string $patron, string $texto, callable $validar): ?string
    {
        if (preg_match_all($patron, $texto, $coincidencias) === false) {
            return null;
        }

        foreach ($coincidencias[0] as $candidato) {
            $id = $validar($candidato);
            if ($id !== null) {
                return $id;
            }
        }

        return null;
    }

    /** Etiquetas de conversión: solo las que están como valor de `send_to`. */
    private static function conversiones(string $texto): array
    {
        $patron = '/send_to[\'"]?\s*[:=]\s*[\'"]\s*(AW-\d{6,15}\/[A-Za-z0-9_-]{6,40})\s*[\'"]/';
        if (preg_match_all($patron, $texto, $coincidencias) === false) {
            return [];
        }

        $lista = [];
        foreach ($coincidencias[1] as $candidata) {
            $etiqueta = FormatosGoogle::conversion($candidata);
            if ($etiqueta !== null && ! in_array($etiqueta, $lista, true)) {
                $lista[] = $etiqueta;
            }
        }

        return $lista;
    }
}
