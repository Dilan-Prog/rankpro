<?php

namespace App\Support\Plantillas;

use App\Enums\TipoCobro;
use App\Enums\TipoServicio;

/**
 * Plantillas de alta por giro: estructura (no contenido editable), así que vive en código como App\Support\Servicios.
 * Es un punto de partida que el usuario valida: NO trae precios, metas ni cifras (el sistema no tiene benchmarks verificables).
 *
 * Marcadores de las keywords semilla: {ciudad} (Cliente::ciudad) y {servicio}, {producto} o {atraccion} (el "tema" que
 * se captura en el asistente). Si falta el dato de un marcador, esa keyword no se crea.
 */
class Giros
{
    public const MARCADORES = ['ciudad', 'servicio', 'producto', 'atraccion'];

    /**
     * @return array<string, array{clave: string, etiqueta: string, tema: array{marcador: string, etiqueta: string}, servicios: array<int, array{tipo: string, nombre: string, tipo_cobro: string}>, metricas: array<int, string>, keywords: array<int, string>, reportes: array<int, string>}>
     */
    public static function todos(): array
    {
        $m = TipoCobro::Mensual->value;
        $u = TipoCobro::Unico->value;
        $g = TipoCobro::Gratuito->value;

        return [
            'hotel' => [
                'clave' => 'hotel',
                'etiqueta' => 'Hotel',
                'tema' => ['marcador' => 'atraccion', 'etiqueta' => 'Atracción o zona cercana (opcional)'],
                'servicios' => [
                    ['tipo' => TipoServicio::Seo->value, 'nombre' => 'SEO local', 'tipo_cobro' => $m],
                    ['tipo' => TipoServicio::GoogleAds->value, 'nombre' => 'Google Ads', 'tipo_cobro' => $m],
                    ['tipo' => TipoServicio::Rediseno->value, 'nombre' => 'Página web', 'tipo_cobro' => $u],
                    ['tipo' => TipoServicio::Seo->value, 'nombre' => '1 URL de SEO', 'tipo_cobro' => $g],
                ],
                'metricas' => ['cotizaciones', 'clics_whatsapp', 'llamadas', 'cpa', 'posicion_media'],
                'keywords' => ['hotel en {ciudad}', 'hoteles en {ciudad}', 'hotel cerca de {atraccion}'],
                'reportes' => ['seo', 'ads'],
            ],
            'servicios_profesionales' => [
                'clave' => 'servicios_profesionales',
                'etiqueta' => 'Servicios profesionales',
                'tema' => ['marcador' => 'servicio', 'etiqueta' => 'Servicio principal (opcional)'],
                'servicios' => [
                    ['tipo' => TipoServicio::Seo->value, 'nombre' => 'SEO local', 'tipo_cobro' => $m],
                    ['tipo' => TipoServicio::GoogleAds->value, 'nombre' => 'Google Ads de búsqueda', 'tipo_cobro' => $m],
                    ['tipo' => TipoServicio::Seo->value, 'nombre' => 'Ficha de Google Business', 'tipo_cobro' => $u],
                ],
                'metricas' => ['llamadas', 'cotizaciones', 'cpa', 'posicion_media'],
                'keywords' => ['{servicio} en {ciudad}', '{servicio} cerca de mí'],
                'reportes' => ['seo'],
            ],
            'b2b_industrial' => [
                'clave' => 'b2b_industrial',
                'etiqueta' => 'B2B / industrial',
                'tema' => ['marcador' => 'producto', 'etiqueta' => 'Producto principal (opcional)'],
                'servicios' => [
                    ['tipo' => TipoServicio::Seo->value, 'nombre' => 'SEO técnico y de contenido', 'tipo_cobro' => $m],
                    ['tipo' => TipoServicio::GoogleAds->value, 'nombre' => 'Google Ads por cotización', 'tipo_cobro' => $m],
                    ['tipo' => TipoServicio::Rediseno->value, 'nombre' => 'Página web', 'tipo_cobro' => $u],
                ],
                'metricas' => ['cotizaciones', 'conversiones', 'sesiones_organicas', 'cpa'],
                'keywords' => ['proveedor de {producto}', 'fabricante de {producto} en México', '{producto} precio'],
                'reportes' => ['seo', 'ads'],
            ],
            'ecommerce' => [
                'clave' => 'ecommerce',
                'etiqueta' => 'E-commerce',
                'tema' => ['marcador' => 'producto', 'etiqueta' => 'Producto principal (opcional)'],
                'servicios' => [
                    ['tipo' => TipoServicio::Seo->value, 'nombre' => 'SEO de catálogo', 'tipo_cobro' => $m],
                    ['tipo' => TipoServicio::GoogleAds->value, 'nombre' => 'Google Ads (Shopping y búsqueda)', 'tipo_cobro' => $m],
                    ['tipo' => TipoServicio::MetaAds->value, 'nombre' => 'Meta Ads', 'tipo_cobro' => $m],
                    ['tipo' => TipoServicio::Rediseno->value, 'nombre' => 'Página web / tienda', 'tipo_cobro' => $u],
                ],
                'metricas' => ['conversiones', 'roas', 'tasa_conversion', 'sesiones_organicas'],
                'keywords' => ['comprar {producto}', '{producto} precio', '{producto} en {ciudad}'],
                'reportes' => ['ads', 'seo'],
            ],
        ];
    }

    /** @return array<int, string> */
    public static function claves(): array
    {
        return array_keys(self::todos());
    }

    public static function buscar(?string $clave): ?array
    {
        return $clave ? (self::todos()[$clave] ?? null) : null;
    }

    /** @return array<string, string> clave => etiqueta, para el select del giro */
    public static function etiquetas(): array
    {
        return array_map(fn (array $g) => $g['etiqueta'], self::todos());
    }

    /**
     * Rellena los marcadores de una keyword semilla. Devuelve null si falta el dato de alguno (esa keyword no se crea).
     *
     * @param  array<string, ?string>  $datos  marcador => valor
     */
    public static function rellenar(string $plantilla, array $datos): ?string
    {
        $falta = false;
        $texto = preg_replace_callback('/\{(\w+)\}/', function (array $m) use ($datos, &$falta) {
            $valor = self::limpiar($datos[$m[1]] ?? null);
            if ($valor === '') {
                $falta = true;
            }

            return $valor;
        }, $plantilla);

        return $falta ? null : trim(preg_replace('/\s+/u', ' ', mb_strtolower((string) $texto)));
    }

    /** El valor entra a una línea CSV: separadores y saltos se vuelven espacios para no partir la keyword. */
    private static function limpiar(?string $valor): string
    {
        return trim((string) preg_replace('/[\s,;]+/u', ' ', (string) $valor));
    }
}
