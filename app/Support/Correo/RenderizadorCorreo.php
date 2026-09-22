<?php

namespace App\Support\Correo;

use App\Models\CorreoPlantilla;
use Illuminate\Support\Facades\URL;

/**
 * Convierte bloques + marca (o HTML libre) en el HTML final de un correo.
 *
 * Todo va en tablas con estilos inline y un ancho máximo de 600px: es lo único
 * que los clientes de correo (Outlook de escritorio, Gmail, Apple Mail) pintan
 * igual. Nada de flex, grid ni clases con <style>. El único <style> que hay es
 * el de las media queries móviles, que los clientes que no lo soportan ignoran
 * sin romper nada.
 *
 * Orden de operaciones por texto: primero se sustituyen las {{variables}} y
 * después se escapa. Así una variable con "<" o "&" llega escapada al correo y
 * no puede inyectar etiquetas.
 */
final class RenderizadorCorreo
{
    private const FUENTE = "'Helvetica Neue',Helvetica,Arial,sans-serif";

    private const TINTA = '#1A2332';

    private const GRIS = '#64748B';

    private const GRIS_CLARO = '#94A3B8';

    private const FONDO = '#F4F6F8';

    /**
     * HTML completo del correo.
     *
     * @param  array<int, array<string, mixed>>  $bloques
     * @param  array<string, mixed>  $marca
     * @param  array<string, mixed>  $variables
     * @param  array{pixel_url?: ?string, token?: ?string, html_libre?: ?string, editor?: bool}  $opciones
     */
    public static function render(array $bloques, array $marca, array $variables = [], array $opciones = []): string
    {
        $htmlLibre = trim((string) ($opciones['html_libre'] ?? ''));
        $editor = (bool) ($opciones['editor'] ?? false);

        if ($htmlLibre !== '') {
            $html = Variables::sustituir($htmlLibre, $variables);
        } else {
            $html = self::documento($bloques, array_replace(Bloques::marcaPorDefecto(), $marca), $variables, $editor);
        }

        $token = (string) ($opciones['token'] ?? '');
        if ($token !== '') {
            $html = self::reescribirEnlaces($html, $token);
        }

        $pixel = (string) ($opciones['pixel_url'] ?? '');
        if ($pixel !== '') {
            $html = self::inyectarPixel($html, $pixel);
        }

        return $html;
    }

    /**
     * Atajo para una plantilla guardada: bloques, marca completa y, si lo hay,
     * HTML libre (que manda sobre los bloques).
     *
     * @param  array<string, mixed>  $variables
     * @param  array<string, mixed>  $opciones
     */
    public static function renderPlantilla(CorreoPlantilla $plantilla, array $variables = [], array $opciones = []): string
    {
        if ($plantilla->esHtmlLibre() && empty($opciones['html_libre'])) {
            $opciones['html_libre'] = $plantilla->html_personalizado;
        }

        return self::render($plantilla->bloques ?? [], $plantilla->marcaCompleta(), $variables, $opciones);
    }

    /**
     * Valores de ejemplo del catálogo, para previas y correos de prueba.
     *
     * @return array<string, string>
     */
    public static function variablesEjemplo(): array
    {
        return array_map(fn ($v) => $v['ejemplo'], Variables::catalogo());
    }

    public static function pixelUrl(string $token): string
    {
        return route('correo.abierto', ['token' => $token]);
    }

    /* ─── Documento ─────────────────────────────────────────────────────── */

    /**
     * @param  array<int, array<string, mixed>>  $bloques
     * @param  array<string, mixed>  $marca
     * @param  array<string, mixed>  $variables
     */
    private static function documento(array $bloques, array $marca, array $variables, bool $editor = false): string
    {
        $color = self::colorSeguro($marca['color'] ?? null);
        $logo = self::cabeceraMarca($marca, $color, $variables);
        $cuerpo = implode("\n", array_filter(array_map(
            fn ($b, $i) => self::bloque(is_array($b) ? $b : [], $color, $variables, $i, $editor),
            $bloques,
            array_keys($bloques)
        )));
        $tituloBloque = collect($bloques)->first(fn ($b) => is_array($b) && ($b['tipo'] ?? '') === 'heading');
        $titulo = self::texto((string) ($tituloBloque['texto'] ?? 'RankPro'), $variables);
        $paddingCabecera = $logo !== '' ? '22px' : '14px';
        $redes = self::redes($marca, $color, $variables);
        // Aviso legal del pie: si no hay {{cliente}} en el envío, no dejamos un hueco.
        $cliente = self::texto('{{cliente}}', $variables);
        $clienteLegal = $cliente !== '' ? $cliente : 'tu empresa';
        $FONDO = self::FONDO;
        $FUENTE = self::FUENTE;
        $GRIS_CLARO = self::GRIS_CLARO;

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<title>{$titulo}</title>
<style>
  .rp-card,.rp-legal{width:100%!important;max-width:600px}
  @media (max-width:480px){
    .rp-pad{padding-left:20px!important;padding-right:20px!important}
    .rp-img{width:100%!important}
    .rp-kpi td{display:block!important;width:100%!important;margin-bottom:8px}
    .rp-kpi td.rp-gap{display:none!important}
  }
</style>
</head>
<body style="margin:0;padding:0;background:{$FONDO};-webkit-text-size-adjust:100%">
<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="background:{$FONDO}"><tr><td align="center" style="padding:24px 12px">
  <table class="rp-card" width="600" cellpadding="0" cellspacing="0" border="0" role="presentation" style="width:100%;max-width:600px;background:#ffffff;border-radius:16px">
    <tr><td class="rp-pad" style="padding:{$paddingCabecera} 32px;background:{$color};border-radius:16px 16px 0 0">{$logo}</td></tr>
    <tr><td style="height:26px;line-height:26px;font-size:0">&nbsp;</td></tr>
{$cuerpo}
{$redes}
    <tr><td style="height:24px;line-height:24px;font-size:0">&nbsp;</td></tr>
  </table>
  <table class="rp-legal" width="600" cellpadding="0" cellspacing="0" border="0" role="presentation" style="width:100%;max-width:600px"><tr><td class="rp-pad" style="padding:16px 32px;text-align:center;font-family:{$FUENTE};font-size:11px;line-height:1.5;color:{$GRIS_CLARO}">
    Recibes este correo porque {$clienteLegal} tiene servicios con RankPro Solutions.
  </td></tr></table>
</td></tr></table>
</body>
</html>
HTML;
    }

    /**
     * Cabecera con la marca. Sin logo devuelve vacío y la cabecera queda como
     * una franja de color más baja.
     *
     * @param  array<string, mixed>  $marca
     * @param  array<string, mixed>  $variables
     */
    private static function cabeceraMarca(array $marca, string $color, array $variables): string
    {
        $fuente = self::FUENTE;
        $tagline = self::texto((string) ($marca['tagline'] ?? ''), $variables);
        $taglineHtml = $tagline !== ''
            ? "<div style=\"font-family:{$fuente};font-size:11px;line-height:1.4;color:rgba(255,255,255,.8);letter-spacing:.04em\">{$tagline}</div>"
            : '';

        switch ($marca['logo'] ?? 'wordmark') {
            case 'monograma':
                return '<table cellpadding="0" cellspacing="0" border="0" role="presentation"><tr>'
                    ."<td style=\"width:38px;height:38px;background:#ffffff;border-radius:10px;text-align:center;vertical-align:middle;font-family:{$fuente};font-size:20px;font-weight:800;color:{$color}\">R</td>"
                    ."<td style=\"padding-left:12px;vertical-align:middle;font-family:{$fuente};font-size:18px;font-weight:700;color:#ffffff;letter-spacing:-.01em\">RankPro</td>"
                    .'</tr></table>';

            case 'apilado':
                return "<div style=\"font-family:{$fuente};font-size:22px;line-height:1.2;font-weight:800;color:#ffffff;letter-spacing:-.02em\">RankPro</div>"
                    .$taglineHtml;

            case 'imagen':
                $url = self::url((string) ($marca['logo_url'] ?? ''), $variables);
                if ($url === '') {
                    return '';
                }

                return "<img src=\"{$url}\" alt=\"RankPro\" height=\"36\" style=\"display:block;height:36px;max-width:220px;border:0\">";

            case 'ninguno':
                return '';

            case 'wordmark':
            default:
                return '<table cellpadding="0" cellspacing="0" border="0" role="presentation"><tr>'
                    ."<td style=\"vertical-align:middle;font-family:{$fuente};font-size:22px;font-weight:800;color:#ffffff;letter-spacing:-.02em\">RankPro</td>"
                    .($tagline !== '' ? "<td style=\"padding-left:12px;vertical-align:middle;font-family:{$fuente};font-size:11px;color:rgba(255,255,255,.8)\">{$tagline}</td>" : '')
                    .'</tr></table>';
        }
    }

    /**
     * Redes de la marca al pie de la tarjeta.
     *
     * @param  array<string, mixed>  $marca
     * @param  array<string, mixed>  $variables
     */
    private static function redes(array $marca, string $color, array $variables): string
    {
        $items = [];
        foreach ((array) ($marca['redes'] ?? []) as $red) {
            if (! is_array($red)) {
                continue;
            }
            $nombre = self::texto((string) ($red['nombre'] ?? ''), $variables);
            $url = self::url((string) ($red['url'] ?? ''), $variables);
            if ($nombre === '' || $url === '') {
                continue;
            }
            $items[] = "<a href=\"{$url}\" style=\"color:{$color};text-decoration:none;font-weight:600\">{$nombre}</a>";
        }

        if ($items === []) {
            return '';
        }

        $fuente = self::FUENTE;
        $gris = self::GRIS_CLARO;

        return "    <tr><td class=\"rp-pad\" style=\"padding:8px 32px 0;text-align:center;font-family:{$fuente};font-size:12px;line-height:1.6;color:{$gris}\">"
            .implode(" <span style=\"color:{$gris}\">&middot;</span> ", $items)
            .'</td></tr>';
    }

    /* ─── Bloques ───────────────────────────────────────────────────────── */

    /**
     * @param  array<string, mixed>  $b
     * @param  array<string, mixed>  $variables
     */
    private static function bloque(array $b, string $color, array $variables, int $indice = 0, bool $editor = false): string
    {
        $fuente = self::FUENTE;
        $tinta = self::TINTA;
        $gris = self::GRIS;
        $fondo = self::FONDO;
        $tipo = (string) ($b['tipo'] ?? '');

        switch ($tipo) {
            case 'heading':
                $alineacion = ($b['alineacion'] ?? 'izquierda') === 'centro' ? 'center' : 'left';
                $texto = self::texto((string) ($b['texto'] ?? ''), $variables);
                $contenido = "<h1 style=\"margin:0;font-family:{$fuente};font-size:24px;line-height:1.3;font-weight:700;color:{$tinta};text-align:{$alineacion}\">{$texto}</h1>";

                return self::fila(self::envolverEditor($editor, $indice, $tipo, $contenido), '0 32px 14px');

            case 'text':
                $parrafos = preg_split('/\r\n|\r|\n/', self::texto((string) ($b['texto'] ?? ''), $variables)) ?: [];
                $html = '';
                foreach ($parrafos as $p) {
                    if (trim($p) === '') {
                        continue;
                    }
                    $html .= "<p style=\"margin:0 0 12px;font-family:{$fuente};font-size:15px;line-height:1.65;color:#334155\">{$p}</p>";
                }
                if ($html === '') {
                    return '';
                }
                $contenido = $editor ? "<div data-rp-texto>{$html}</div>" : $html;

                return self::fila(self::envolverEditor($editor, $indice, $tipo, $contenido), '0 32px 8px');

            case 'list':
                $items = '';
                foreach ((array) ($b['items'] ?? []) as $item) {
                    $texto = self::texto((string) $item, $variables);
                    if ($texto === '') {
                        continue;
                    }
                    $items .= '<tr>'
                        ."<td width=\"18\" valign=\"top\" style=\"width:18px;padding:0 0 8px;font-family:{$fuente};font-size:15px;line-height:1.6;color:{$color};font-weight:700\">&bull;</td>"
                        ."<td valign=\"top\" style=\"padding:0 0 8px;font-family:{$fuente};font-size:15px;line-height:1.6;color:#334155\">{$texto}</td>"
                        .'</tr>';
                }
                if ($items === '') {
                    return '';
                }
                $contenido = "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" role=\"presentation\">{$items}</table>";

                return self::fila(self::envolverEditor($editor, $indice, $tipo, $contenido), '0 32px 12px');

            case 'kpi':
                $items = array_values(array_filter((array) ($b['items'] ?? []), 'is_array'));
                $items = array_slice($items, 0, 4);
                if (count($items) < 2) {
                    return '';
                }
                $celdas = [];
                foreach ($items as $i => $item) {
                    $valor = self::texto((string) ($item['valor'] ?? ''), $variables);
                    $label = self::texto((string) ($item['label'] ?? ''), $variables);
                    if ($i > 0) {
                        $celdas[] = '<td class="rp-gap" width="10" style="width:10px;font-size:0;line-height:0">&nbsp;</td>';
                    }
                    $celdas[] = "<td valign=\"top\" style=\"background:{$fondo};border-radius:12px;padding:16px 12px;text-align:center\">"
                        ."<div style=\"font-family:{$fuente};font-size:24px;line-height:1.2;font-weight:800;color:{$color}\">{$valor}</div>"
                        ."<div style=\"font-family:{$fuente};font-size:11px;line-height:1.4;font-weight:600;color:{$gris};text-transform:uppercase;letter-spacing:.06em;margin-top:6px\">{$label}</div>"
                        .'</td>';
                }
                $contenido = '<table class="rp-kpi" width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation"><tr>'.implode('', $celdas).'</tr></table>';

                return self::fila(self::envolverEditor($editor, $indice, $tipo, $contenido), '6px 32px 18px');

            case 'button':
                $texto = self::texto((string) ($b['texto'] ?? ''), $variables);
                $url = self::url((string) ($b['url'] ?? ''), $variables);
                if ($texto === '') {
                    return '';
                }
                $href = $url !== '' ? $url : '#';
                $contenido = '<table cellpadding="0" cellspacing="0" border="0" role="presentation"><tr>'
                    ."<td style=\"background:{$color};border-radius:10px\">"
                    ."<a href=\"{$href}\" style=\"display:inline-block;padding:13px 26px;font-family:{$fuente};font-size:15px;font-weight:700;line-height:1.2;color:#ffffff;text-decoration:none;border-radius:10px\">{$texto}</a>"
                    .'</td></tr></table>';

                return self::fila(self::envolverEditor($editor, $indice, $tipo, $contenido), '8px 32px 20px');

            case 'image':
                $url = self::url((string) ($b['url'] ?? ''), $variables);
                $alt = self::texto((string) ($b['alt'] ?? ''), $variables);
                if ($url === '') {
                    return '';
                }
                $contenido = "<img class=\"rp-img\" src=\"{$url}\" alt=\"{$alt}\" width=\"536\" style=\"display:block;width:100%;max-width:536px;height:auto;border:0;border-radius:12px\">";

                return self::fila(self::envolverEditor($editor, $indice, $tipo, $contenido), '4px 32px 20px');

            case 'divider':
                $contenido = '<div style="height:1px;line-height:1px;font-size:0;background:#E2E8F0">&nbsp;</div>';

                return self::fila(self::envolverEditor($editor, $indice, $tipo, $contenido), '8px 32px 20px');

            case 'footer':
                $texto = nl2br(self::texto((string) ($b['texto'] ?? ''), $variables), false);
                if (trim($texto) === '') {
                    return '';
                }
                $parrafo = "<p style=\"margin:0;font-family:{$fuente};font-size:12px;line-height:1.7;color:{$gris}\">{$texto}</p>";
                $contenido = $editor ? "<div data-rp-texto>{$parrafo}</div>" : $parrafo;

                return self::fila(self::envolverEditor($editor, $indice, $tipo, $contenido), '8px 32px 0');

            default:
                return '';
        }
    }

    /**
     * Envuelve el contenido de un bloque en el marcador estructural que usa el
     * editor visual para ubicar y reemplazar un bloque desde el iframe de la
     * previa. Sin atributo `style`: es puramente estructural y no afecta el
     * layout en los clientes de correo. Fuera del modo editor (correo real,
     * plantillas) no se añade nada.
     */
    private static function envolverEditor(bool $editor, int $indice, string $tipo, string $contenido): string
    {
        if (! $editor || $contenido === '') {
            return $contenido;
        }

        return "<div data-rp-bloque=\"{$indice}\" data-rp-tipo=\"{$tipo}\">{$contenido}</div>";
    }

    /** Una fila de la tarjeta con el relleno lateral estándar. */
    private static function fila(string $contenido, string $padding): string
    {
        return "    <tr><td class=\"rp-pad\" style=\"padding:{$padding}\">{$contenido}</td></tr>";
    }

    /* ─── Utilidades ────────────────────────────────────────────────────── */

    /**
     * Sustituye variables y escapa: el orden importa (ver docblock de clase).
     *
     * @param  array<string, mixed>  $variables
     */
    private static function texto(string $texto, array $variables): string
    {
        return e(Variables::sustituir($texto, $variables));
    }

    /**
     * URL para un atributo href/src: variables sustituidas, escapada y sin
     * esquemas que no sean http(s) o mailto (un `javascript:` no llega al correo).
     *
     * @param  array<string, mixed>  $variables
     */
    private static function url(string $url, array $variables): string
    {
        $url = trim(Variables::sustituir($url, $variables));
        if ($url === '' || $url === '#') {
            return '';
        }
        if (! preg_match('#^(https?://|mailto:|tel:)#i', $url)) {
            return '';
        }

        return e($url);
    }

    /** Solo acepta un hexadecimal de 6 dígitos: va dentro de atributos style. */
    private static function colorSeguro(?string $color): string
    {
        return is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color)
            ? $color
            : Bloques::marcaPorDefecto()['color'];
    }

    /**
     * Cada href http(s) pasa por la ruta firmada de clics. Se aplica también al
     * HTML libre, así que el enlace puede venir con &amp; escapado: se decodifica
     * antes de firmar y la URL firmada se vuelve a escapar.
     */
    private static function reescribirEnlaces(string $html, string $token): string
    {
        return (string) preg_replace_callback(
            '/href\s*=\s*(["\'])(https?:\/\/[^"\']+)\1/i',
            function ($m) use ($token) {
                $destino = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $firmada = URL::signedRoute('correo.clic', ['token' => $token, 'u' => $destino]);

                return 'href='.$m[1].e($firmada).$m[1];
            },
            $html
        );
    }

    private static function inyectarPixel(string $html, string $pixelUrl): string
    {
        $img = '<img src="'.e($pixelUrl).'" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0">';
        $pos = stripos($html, '</body>');

        return $pos === false
            ? $html.$img
            : substr($html, 0, $pos).$img.substr($html, $pos);
    }
}
