<?php

namespace App\Support;

/**
 * Clusters tematicos del blog.
 *
 * Misma idea que App\Support\Servicios: fuente unica de verdad, sin base de
 * datos, para todo lo que es estructura del sitio y no contenido editable.
 *
 * Cada cluster apunta a un servicio del catalogo mediante la clave 'servicio'.
 * Esa relacion es la columna vertebral del modelo hub-and-spoke: los articulos
 * (spokes) enlazan hacia su pagina de servicio (hub) y la pagina de servicio
 * enlaza de vuelta a sus articulos. Sin ese enlace de ida y vuelta el blog seria
 * un silo que no aporta nada a las paginas que de verdad convierten.
 *
 * Los 6 clusters salen del plan de keywords (agosto 2026, base Semrush MX).
 */
class Clusters
{
    /**
     * @return array<string, array{
     *     slug: string, nombre: string, nombre_corto: string, descripcion: string,
     *     h1: string, meta_title: string, meta_description: string,
     *     servicio: string, gradient: string
     * }>
     */
    public static function todos(): array
    {
        return [
            'pagespeed-core-web-vitals' => [
                'slug' => 'pagespeed-core-web-vitals',
                'nombre' => 'PageSpeed y Core Web Vitals',
                'nombre_corto' => 'Velocidad web',
                'descripcion' => 'Cómo medir y arreglar LCP, INP y CLS en sitios reales, con hostings y plantillas que se usan en México.',
                'h1' => 'Velocidad web y Core Web Vitals',
                'meta_title' => 'Core Web Vitals y velocidad web | Blog RankPro',
                'meta_description' => 'Guías prácticas para medir y arreglar LCP, INP y CLS: qué revisar, con qué herramientas y qué cambia de verdad en el posicionamiento.',
                'servicio' => 'pagespeed-core-web-vitals',
                'gradient' => 'gradient-4',
            ],

            'analytics-ga4' => [
                'slug' => 'analytics-ga4',
                'nombre' => 'Analítica y GA4',
                'nombre_corto' => 'Analítica',
                'descripcion' => 'Medir bien antes de optimizar: GA4, Google Tag Manager, conversiones de WhatsApp y llamadas, y por qué los números nunca cuadran entre plataformas.',
                'h1' => 'Analítica digital y Google Analytics 4',
                'meta_title' => 'Guías de GA4 y Google Tag Manager | Blog RankPro',
                'meta_description' => 'Cómo configurar GA4 y GTM sin morir en el intento: conversiones de WhatsApp y llamadas, exclusión de tráfico interno y reportes que sí se entienden.',
                'servicio' => 'analytics-data',
                'gradient' => 'gradient-5',
            ],

            'sem-google-ads' => [
                'slug' => 'sem-google-ads',
                'nombre' => 'SEM y Google Ads',
                'nombre_corto' => 'Google Ads',
                'descripcion' => 'Presupuestos reales, nivel de calidad, palabras clave negativas y cuándo conviene pagar en lugar de esperar al SEO.',
                'h1' => 'SEM y Google Ads',
                'meta_title' => 'Guías de Google Ads para México | Blog RankPro',
                'meta_description' => 'Presupuesto mínimo por industria, nivel de calidad, palabras clave negativas y Performance Max: lo que hay que saber antes de invertir en Google Ads.',
                'servicio' => 'sem-google-ads',
                'gradient' => 'gradient-1',
            ],

            'seo-organico' => [
                'slug' => 'seo-organico',
                'nombre' => 'SEO orgánico',
                'nombre_corto' => 'SEO',
                'descripcion' => 'Posicionamiento sin humo: qué se puede prometer, cuánto tarda de verdad, cómo auditarlo y cómo detectar a quien te está vendiendo aire.',
                'h1' => 'SEO orgánico',
                'meta_title' => 'Guías de SEO orgánico en español | Blog RankPro',
                'meta_description' => 'Checklists de SEO on-page, indexación, migraciones sin perder posiciones y cómo auditar a tu agencia actual con Search Console y GA4.',
                'servicio' => 'seo-organico',
                'gradient' => 'gradient-2',
            ],

            'desarrollo-web' => [
                'slug' => 'desarrollo-web',
                'nombre' => 'Desarrollo web',
                'nombre_corto' => 'Desarrollo',
                'descripcion' => 'Qué cuesta una página web en México, qué plataforma conviene, y cómo migrar o mantener un sitio sin romper el posicionamiento.',
                'h1' => 'Desarrollo web y tiendas en línea',
                'meta_title' => 'Guías de desarrollo web en México | Blog RankPro',
                'meta_description' => 'Costos reales de una página web y su mantenimiento, WordPress frente a Shopify, migraciones sin perder SEO y requisitos legales de un sitio mexicano.',
                'servicio' => 'desarrollo-web',
                'gradient' => 'gradient-3',
            ],

            'social-media' => [
                'slug' => 'social-media',
                'nombre' => 'Redes sociales',
                'nombre_corto' => 'Redes',
                'descripcion' => 'Qué red conviene a cada negocio, cuánto cuesta gestionarlas y cómo vender por WhatsApp Business midiendo el resultado.',
                'h1' => 'Redes sociales y pauta digital',
                'meta_title' => 'Guías de redes sociales para negocios | Blog RankPro',
                'meta_description' => 'Qué red social conviene a tu negocio en México, cuánto cuesta gestionarlas de verdad y cómo vender por WhatsApp Business con catálogo y medición.',
                'servicio' => 'social-media',
                'gradient' => 'gradient-6',
            ],
        ];
    }

    public static function existe(string $slug): bool
    {
        return array_key_exists($slug, self::todos());
    }

    /** @return array{slug: string, nombre: string, ...}|null */
    public static function encontrar(string $slug): ?array
    {
        return self::todos()[$slug] ?? null;
    }

    /**
     * Version reducida para navegacion (navbar, footer, indice del blog).
     *
     * @return list<array{slug: string, nombre: string, nombre_corto: string, descripcion: string, url: string, gradient: string}>
     */
    public static function navegacion(): array
    {
        return array_values(array_map(static fn (array $c) => [
            'slug' => $c['slug'],
            'nombre' => $c['nombre'],
            'nombre_corto' => $c['nombre_corto'],
            'descripcion' => $c['descripcion'],
            'url' => route('blog.cluster', $c['slug']),
            'gradient' => $c['gradient'],
        ], self::todos()));
    }

    /**
     * Slug del servicio (hub) al que apoya cada cluster.
     *
     * Devuelve null si el cluster no existe, para que quien llame decida
     * si es un 404 o simplemente no pintar el bloque de enlace.
     */
    public static function servicioDe(string $slugCluster): ?string
    {
        return self::todos()[$slugCluster]['servicio'] ?? null;
    }

    /**
     * Inverso del anterior: clusters que apoyan a un servicio dado.
     *
     * Hoy la relacion es 1:1, pero se devuelve una lista para no tener que
     * cambiar las llamadas el dia que un servicio tenga dos clusters.
     *
     * @return list<string>
     */
    public static function deServicio(string $slugServicio): array
    {
        return array_keys(array_filter(
            self::todos(),
            static fn (array $c) => $c['servicio'] === $slugServicio
        ));
    }

    /** @return list<string> Valores validos para la columna `cluster`. */
    public static function slugs(): array
    {
        return array_keys(self::todos());
    }
}
