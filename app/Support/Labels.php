<?php

namespace App\Support;

/**
 * Human-readable Spanish labels for the various categorical DB columns
 * (tipo, plataforma, objetivo, ...) that aren't full status enums with
 * their own badge color — shared across every admin module's Blade views.
 */
class Labels
{
    public static function servicioTipo(string $tipo): string
    {
        return [
            'seo' => 'SEO',
            'google_ads' => 'Google Ads',
            'meta_ads' => 'Meta Ads',
            'tiktok_ads' => 'TikTok Ads',
            'rediseno' => 'Rediseño',
            'software' => 'Software',
        ][$tipo] ?? ucfirst($tipo);
    }

    public static function plataforma(string $plataforma): string
    {
        return [
            'google_ads' => 'Google Ads',
            'meta_ads' => 'Meta Ads',
            'tiktok_ads' => 'TikTok Ads',
        ][$plataforma] ?? ucfirst($plataforma);
    }

    public static function objetivo(string $objetivo): string
    {
        return [
            'leads' => 'Leads',
            'ventas' => 'Ventas',
            'trafico' => 'Tráfico',
            'branding' => 'Branding',
        ][$objetivo] ?? ucfirst($objetivo);
    }

    public static function intencion(?string $intencion): string
    {
        return [
            'informacional' => 'Informacional',
            'transaccional' => 'Transaccional',
            'navegacional' => 'Navegacional',
        ][$intencion] ?? ($intencion ? ucfirst($intencion) : '—');
    }

    public static function herramientaOrigen(?string $herramienta): string
    {
        return [
            'semrush' => 'Semrush',
            'ahrefs' => 'Ahrefs',
            'google_kp' => 'Google Keyword Planner',
            'otro' => 'Otro',
        ][$herramienta] ?? ($herramienta ? ucfirst($herramienta) : '—');
    }

    public static function tipoKeyword(string $tipo): string
    {
        return [
            'principal' => 'Principal',
            'secundaria' => 'Secundaria',
            'long_tail' => 'Long Tail',
            'lsi' => 'LSI',
        ][$tipo] ?? ucfirst($tipo);
    }

    public static function tipoProyecto(string $tipo): string
    {
        return [
            'rediseno' => 'Rediseño',
            'web_nueva' => 'Web Nueva',
            'software' => 'Software',
            'landing' => 'Landing Page',
        ][$tipo] ?? ucfirst($tipo);
    }

    public static function tipoArchivo(string $tipo): string
    {
        return [
            'contrato' => 'Contrato',
            'propuesta' => 'Propuesta',
            'diseno' => 'Diseño',
            'reporte' => 'Reporte',
            'otro' => 'Otro',
        ][$tipo] ?? ucfirst($tipo);
    }

    public static function formaPago(?string $forma): string
    {
        return [
            'mensual' => 'Mensual',
            'trimestral' => 'Trimestral',
            'anual' => 'Anual',
        ][$forma] ?? ($forma ? ucfirst($forma) : '—');
    }

    public static function metodoPago(?string $metodo): string
    {
        return [
            'transferencia' => 'Transferencia',
            'tarjeta' => 'Tarjeta',
            'efectivo' => 'Efectivo',
            'paypal' => 'PayPal',
        ][$metodo] ?? ($metodo ? ucfirst($metodo) : '—');
    }

    public static function formaPagoProyecto(?string $forma): string
    {
        return [
            'mensual' => 'Mensual',
            'etapas' => 'Por Etapas',
            'unico' => 'Pago Único',
        ][$forma] ?? ($forma ? ucfirst($forma) : '—');
    }

    public static function faseProyecto(string $fase): string
    {
        return [
            'planeacion' => 'Planeación',
            'organizacion' => 'Organización',
            'direccion' => 'Dirección',
            'control' => 'Control',
            'cerrado' => 'Cerrado',
        ][$fase] ?? ucfirst($fase);
    }

    public static function tipoPruebaQa(string $tipo): string
    {
        return [
            'funcional' => 'Funcional',
            'visual' => 'Visual',
            'rendimiento' => 'Rendimiento',
            'seguridad' => 'Seguridad',
        ][$tipo] ?? ucfirst($tipo);
    }

    public static function faseSeo(string $fase): string
    {
        return [
            'auditoria' => 'Auditoría',
            'estrategia' => 'Estrategia',
            'ejecucion' => 'Ejecución',
            'reporte' => 'Reporte y Análisis',
        ][$fase] ?? ucfirst($fase);
    }
}
