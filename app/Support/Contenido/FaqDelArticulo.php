<?php

namespace App\Support\Contenido;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Preguntas frecuentes leídas del HTML que se pinta.
 *
 * Se extraen de contenido_html, no de un campo aparte: así el FAQPage no puede
 * decir algo distinto de lo visible. Formato reconocido, bajo el primer <h2>
 * que empiece por "Preguntas frecuentes" y hasta el siguiente <h2>:
 *
 *   <p><strong>¿Pregunta?</strong> Respuesta</p>   (el que usan las guías)
 *   <h3>¿Pregunta?</h3><p>Respuesta</p>...
 */
class FaqDelArticulo
{
    /** @return list<array{p: string, r: string}> */
    public static function extraer(?string $html): array
    {
        if (trim((string) $html) === '') {
            return [];
        }

        $doc = new DOMDocument();
        $previo = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        // El permalink "#" del encabezado no forma parte del texto.
        $xpath = new DOMXPath($doc);
        foreach (iterator_to_array($xpath->query('//a[contains(@class,"ancla-titulo")]')) as $ancla) {
            $ancla->parentNode->removeChild($ancla);
        }

        $titulo = null;
        foreach ($xpath->query('//h2') as $h2) {
            if (preg_match('/^preguntas frecuentes/iu', self::texto($h2->textContent))) {
                $titulo = $h2;
                break;
            }
        }

        $faqs = [];
        $abierta = false;

        for ($n = $titulo?->nextSibling; $n !== null; $n = $n->nextSibling) {
            if (! $n instanceof DOMElement) {
                continue;
            }
            if ($n->nodeName === 'h2') {
                break;
            }
            if ($n->nodeName === 'h3') {
                $faqs[] = ['p' => self::texto($n->textContent), 'r' => ''];
                $abierta = true;

                continue;
            }

            $primero = $n->firstChild;
            $pregunta = $n->nodeName === 'p' && $primero instanceof DOMElement && $primero->nodeName === 'strong'
                ? self::texto($primero->textContent)
                : '';

            if (str_ends_with($pregunta, '?')) {
                $n->removeChild($primero);
                $faqs[] = ['p' => $pregunta, 'r' => self::texto($n->textContent)];
                $abierta = true;
            } elseif ($abierta && in_array($n->nodeName, ['p', 'ul', 'ol'], true)) {
                $i = array_key_last($faqs);
                $faqs[$i]['r'] = trim($faqs[$i]['r'].' '.self::texto($n->textContent));
            } else {
                $abierta = false;
            }
        }

        return array_values(array_filter($faqs, static fn (array $f): bool => $f['r'] !== ''));
    }

    private static function texto(string $s): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $s));
    }
}
