<?php

namespace App\Support\Correo;

/**
 * Tipos de bloque del constructor de plantillas y su forma. Fuente única para
 * el editor (qué campos pinta por tipo), el validador del controlador y el
 * renderizador (qué HTML de correo produce cada uno).
 *
 * Forma de `correo_plantillas.bloques`: lista ordenada de
 *   {tipo, ...campos del tipo}
 *
 *   heading  {texto, alineacion: izquierda|centro}
 *   text     {texto}                     (saltos de línea = párrafos)
 *   list     {items: string[]}
 *   kpi      {items: [{label, valor}]}   (2 a 4)
 *   button   {texto, url}
 *   image    {url, alt}
 *   divider  {}
 *   footer   {texto}                     (saltos de línea respetados)
 *
 * Forma de `correo_plantillas.marca`:
 *   {color: '#0F9D6E', logo: wordmark|monograma|apilado|imagen|ninguno,
 *    logo_url: ?string, tagline: ?string, redes: [{nombre, url}]}
 */
class Bloques
{
    public const TIPOS = ['heading', 'text', 'list', 'kpi', 'button', 'image', 'divider', 'footer'];

    public const LOGOS = ['wordmark', 'monograma', 'apilado', 'imagen', 'ninguno'];

    public const COLORES = [
        '#0F9D6E' => 'Verde RankPro',
        '#047857' => 'Verde profundo',
        '#1A2332' => 'Tinta',
        '#0B7A56' => 'Verde oscuro',
    ];

    /**
     * @return array<string, array{label: string, defecto: array<string, mixed>}>
     */
    public static function catalogo(): array
    {
        return [
            'heading' => ['label' => 'Encabezado', 'defecto' => ['texto' => 'Un título que explique el correo', 'alineacion' => 'izquierda']],
            'text' => ['label' => 'Párrafo', 'defecto' => ['texto' => 'Hola {{contacto}}, escribe aquí el mensaje.']],
            'list' => ['label' => 'Lista', 'defecto' => ['items' => ['Primer punto', 'Segundo punto', 'Tercer punto']]],
            'kpi' => ['label' => 'Bloque de cifras', 'defecto' => ['items' => [['label' => 'Sesiones', 'valor' => '12,480'], ['label' => 'Conversiones', 'valor' => '318'], ['label' => 'ROAS', 'valor' => '5.4x']]]],
            'button' => ['label' => 'Botón', 'defecto' => ['texto' => 'Ver el reporte completo', 'url' => '{{enlace_reporte}}']],
            'image' => ['label' => 'Imagen', 'defecto' => ['url' => '', 'alt' => 'Gráfica de resultados del mes']],
            'divider' => ['label' => 'Separador', 'defecto' => []],
            'footer' => ['label' => 'Pie / firma', 'defecto' => ['texto' => "RankPro Solutions · Agencia de marketing digital\nAguascalientes, México · administracion@rankprosolutions.com.mx"]],
        ];
    }

    /** @return array<string, mixed> */
    public static function marcaPorDefecto(): array
    {
        return [
            'color' => '#0F9D6E',
            'logo' => 'wordmark',
            'logo_url' => null,
            'tagline' => 'Digital Solutions & Services',
            'redes' => [
                ['nombre' => 'Sitio web', 'url' => 'https://rankprosolutions.com.mx'],
                ['nombre' => 'WhatsApp', 'url' => 'https://wa.me/527341036410'],
            ],
        ];
    }

    /**
     * Bloques con los que nace una plantilla nueva: lo mínimo para que la previa
     * enseñe algo y el editor no arranque en blanco.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function porDefecto(): array
    {
        $c = self::catalogo();

        return [
            ['tipo' => 'heading'] + $c['heading']['defecto'],
            ['tipo' => 'text'] + $c['text']['defecto'],
            ['tipo' => 'footer'] + $c['footer']['defecto'],
        ];
    }

    /**
     * Reglas de validación de `bloques` y `marca`, prefijadas para mezclarlas
     * con las del registro. Cada tipo valida solo sus campos.
     *
     * @return array<string, mixed>
     */
    public static function reglas(): array
    {
        return [
            'bloques' => ['present', 'array', 'max:40'],
            'bloques.*.tipo' => ['required', 'in:'.implode(',', self::TIPOS)],
            'bloques.*.texto' => ['nullable', 'string', 'max:5000'],
            'bloques.*.alineacion' => ['nullable', 'in:izquierda,centro'],
            'bloques.*.url' => ['nullable', 'string', 'max:2000'],
            'bloques.*.alt' => ['nullable', 'string', 'max:255'],
            'bloques.*.items' => ['nullable', 'array', 'max:12'],
            'marca' => ['nullable', 'array'],
            'marca.color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'marca.logo' => ['nullable', 'in:'.implode(',', self::LOGOS)],
            'marca.logo_url' => ['nullable', 'string', 'max:2000'],
            'marca.tagline' => ['nullable', 'string', 'max:120'],
            'marca.redes' => ['nullable', 'array', 'max:8'],
            'marca.redes.*.nombre' => ['required_with:marca.redes', 'string', 'max:40'],
            'marca.redes.*.url' => ['required_with:marca.redes', 'string', 'max:2000'],
        ];
    }
}
