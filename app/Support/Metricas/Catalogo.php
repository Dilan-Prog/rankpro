<?php

namespace App\Support\Metricas;

use App\Enums\SentidoMetrica;

/**
 * Catálogo estándar de métricas de marketing: unidad, sentido y fuente típica ya decididos para que dos clientes
 * no midan el "CPA" con unidades distintas. Es estructura y no contenido, así que vive en código (como App\Support\Servicios).
 * Ninguna entrada trae meta: el sistema no tiene benchmarks verificables y la meta la fija el usuario por cliente.
 */
class Catalogo
{
    /** Fuentes válidas de una métrica; `manual` es la de las personalizadas. */
    public const FUENTES = ['manual', 'google_ads', 'search_console', 'ga4', 'crm', 'tracking', 'n8n'];

    /** @return array<string, string> clave => etiqueta para los selectores */
    public static function fuentes(): array
    {
        return [
            'manual' => 'Manual',
            'google_ads' => 'Google Ads',
            'search_console' => 'Search Console',
            'ga4' => 'Google Analytics 4',
            'crm' => 'CRM',
            'tracking' => 'Tracking propio',
            'n8n' => 'n8n',
        ];
    }

    /**
     * La clave del array es la `clave` de la entrada (queda guardada en metricas_cliente.metrica_estandar).
     * `fuente_tipica` es la fuente que se guarda al agregarla desde el catálogo.
     *
     * @return array<string, array{clave: string, nombre: string, unidad: string, sentido: string, fuente_tipica: string, descripcion: string}>
     */
    public static function todos(): array
    {
        $entradas = [
            ['impresiones', 'Impresiones', 'impresiones', 'mayor', 'google_ads', 'Veces que se mostró un anuncio o un resultado (Google Ads o Search Console).'],
            ['clics', 'Clics', 'clics', 'mayor', 'google_ads', 'Clics recibidos desde anuncios o desde resultados orgánicos.'],
            ['ctr', 'CTR', '%', 'mayor', 'google_ads', 'Clics entre impresiones, en porcentaje.'],
            ['cpc', 'CPC', 'MXN', 'menor', 'google_ads', 'Costo promedio por clic.'],
            ['cpa', 'CPA / costo por lead', 'MXN', 'menor', 'google_ads', 'Inversión entre conversiones o leads obtenidos.'],
            ['roas', 'ROAS', 'x', 'mayor', 'google_ads', 'Ingreso atribuido por cada peso invertido en anuncios.'],
            ['conversiones', 'Conversiones', 'conv.', 'mayor', 'google_ads', 'Acciones valiosas completadas (formularios, llamadas, compras).'],
            ['inversion', 'Inversión', 'MXN', 'mayor', 'google_ads', 'Gasto en medios del mes; es informativa, por eso no lleva meta y su semáforo sale "sin meta".'],
            ['posicion_media', 'Posición media', 'posición', 'menor', 'search_console', 'Posición promedio en Google; un número más bajo es mejor.'],
            ['keywords_top10', 'Keywords en top 10', 'keywords', 'mayor', 'manual', 'Palabras clave posicionadas en la primera página de Google.'],
            ['sesiones_organicas', 'Sesiones orgánicas', 'sesiones', 'mayor', 'ga4', 'Visitas al sitio que llegaron desde búsqueda orgánica.'],
            ['llamadas', 'Llamadas', 'llamadas', 'mayor', 'tracking', 'Llamadas recibidas atribuibles al marketing (tracking o conteo manual).'],
            ['clics_whatsapp', 'Clics a WhatsApp', 'clics', 'mayor', 'tracking', 'Clics en los botones o enlaces a WhatsApp del sitio.'],
            ['cotizaciones', 'Cotizaciones / reservas', 'cantidad', 'mayor', 'manual', 'Cotizaciones o reservas cerradas o solicitadas en el mes.'],
            ['tasa_conversion', 'Tasa de conversión', '%', 'mayor', 'ga4', 'Conversiones entre sesiones, en porcentaje.'],
        ];

        $catalogo = [];
        foreach ($entradas as [$clave, $nombre, $unidad, $sentido, $fuente, $descripcion]) {
            $catalogo[$clave] = [
                'clave' => $clave,
                'nombre' => $nombre,
                'unidad' => $unidad,
                'sentido' => $sentido,
                'fuente_tipica' => $fuente,
                'descripcion' => $descripcion,
            ];
        }

        return $catalogo;
    }

    /** @return array{clave: string, nombre: string, unidad: string, sentido: string, fuente_tipica: string, descripcion: string}|null */
    public static function buscar(string $clave): ?array
    {
        return self::todos()[$clave] ?? null;
    }

    /** @return array<int, string> */
    public static function claves(): array
    {
        return array_keys(self::todos());
    }

    public static function sentido(string $clave): ?SentidoMetrica
    {
        $entrada = self::buscar($clave);

        return $entrada ? SentidoMetrica::from($entrada['sentido']) : null;
    }
}
