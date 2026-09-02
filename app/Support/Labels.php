<?php

namespace App\Support;

use App\Enums\TipoServicio;

/**
 * Human-readable Spanish labels for the various categorical DB columns
 * (tipo, plataforma, objetivo, ...) that aren't full status enums with
 * their own badge color — shared across every admin module's Blade views.
 */
class Labels
{
    /** Up to 2 uppercase initials from a display name — shared by the sidebar and header avatar. */
    public static function initials(?string $name): string
    {
        return collect(explode(' ', trim($name ?? '') ?: 'Usuario'))
            ->filter()
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->take(2)
            ->join('');
    }

    /** Accepts either the raw column value or the TipoServicio enum the Servicio model casts it to. */
    public static function servicioTipo(string|TipoServicio $tipo): string
    {
        $tipo = $tipo instanceof TipoServicio ? $tipo->value : $tipo;

        return [
            'seo' => 'SEO',
            'google_ads' => 'Google Ads',
            'meta_ads' => 'Meta Ads',
            'tiktok_ads' => 'TikTok Ads',
            'rediseno' => 'Rediseño',
            'software' => 'Software',
            'automatizacion' => 'Automatización',
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
            'datos' => 'Datos',
            'entregable' => 'Entregable',
            'otro' => 'Otro',
        ][$tipo] ?? ucfirst($tipo);
    }

    public static function estadoCliente(string $estado): string
    {
        return [
            'activo' => 'Activo',
            'pausado' => 'Pausado',
            'cancelado' => 'Cancelado',
        ][$estado] ?? ucfirst($estado);
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
            'cerrada' => 'Cerrada',
        ][$fase] ?? ucfirst($fase);
    }

    public static function faseAds(string $fase): string
    {
        return [
            'briefing' => 'Briefing y Estrategia',
            'configuracion' => 'Configuración',
            'lanzamiento' => 'Lanzamiento y Optimización',
            'reporte' => 'Reporte y Análisis',
            'cerrada' => 'Cerrada',
        ][$fase] ?? ucfirst($fase);
    }

    public static function faseAutomatizacion(string $fase): string
    {
        return [
            'diagnostico' => 'Diagnóstico',
            'diseno_flujo' => 'Diseño de Flujo',
            'implementacion' => 'Implementación',
            'reporte' => 'Reporte y Análisis',
            'cerrada' => 'Cerrada',
        ][$fase] ?? ucfirst($fase);
    }

    public static function tipoFlujoAutomatizacion(string $tipo): string
    {
        return [
            'whatsapp' => 'WhatsApp',
            'crm' => 'CRM',
            'email' => 'Email',
            'notificaciones' => 'Notificaciones',
            'otro' => 'Otro',
        ][$tipo] ?? ucfirst($tipo);
    }

    public static function complejidadFlujoAutomatizacion(string $complejidad): string
    {
        return [
            'basico' => 'Básico',
            'intermedio' => 'Intermedio',
            'avanzado' => 'Avanzado',
        ][$complejidad] ?? ucfirst($complejidad);
    }

    public static function tipoOptimizacion(string $tipo): string
    {
        return [
            'puja' => 'Puja',
            'audiencia' => 'Audiencia',
            'creativo' => 'Creativo',
            'presupuesto' => 'Presupuesto',
            'keyword' => 'Keyword',
        ][$tipo] ?? ucfirst($tipo);
    }

    public static function tipoConversion(string $tipo): string
    {
        return [
            'formulario' => 'Formulario',
            'whatsapp' => 'WhatsApp',
            'llamada' => 'Llamada',
            'compra' => 'Compra',
        ][$tipo] ?? ucfirst($tipo);
    }

    public static function areaUsuario(?string $area): string
    {
        return [
            'direccion' => 'Dirección',
            'seo' => 'SEO',
            'ads' => 'Ads',
            'social' => 'Social',
            'desarrollo' => 'Desarrollo',
            'administracion' => 'Administración',
            'externo' => 'Externo',
        ][$area] ?? ($area ? ucfirst($area) : '—');
    }

    public static function estadoReporte(?string $estado): string
    {
        return [
            'borrador' => 'Borrador',
            'listo' => 'Listo',
            'entregado' => 'Entregado',
        ][$estado] ?? ($estado ? ucfirst($estado) : '—');
    }

    public static function severidadHallazgo(?string $severidad): string
    {
        return [
            'critico' => 'Crítico',
            'alto' => 'Alto',
            'medio' => 'Medio',
            'informativo' => 'Informativo',
        ][$severidad] ?? ($severidad ? ucfirst($severidad) : '—');
    }

    /** P0 bloqueante, P1 alto retorno, P2 mejora acumulativa, P3 exploratorio. */
    public static function prioridadAccion(?string $prioridad): string
    {
        return [
            'p0' => 'P0 — Bloqueante',
            'p1' => 'P1 — Alta',
            'p2' => 'P2 — Media',
            'p3' => 'P3 — Baja',
        ][$prioridad] ?? ($prioridad ? mb_strtoupper($prioridad) : '—');
    }

    /** Ícono + color por tipo de conversión — usado en la tarjeta del tablero de embudo. */
    public static function tipoConversionIcono(string $tipo): array
    {
        return [
            'formulario' => ['fa-file-lines', '#6366f1'],
            'whatsapp' => ['fa-whatsapp', '#22c55e'],
            'llamada' => ['fa-phone', '#3b82f6'],
            'compra' => ['fa-cart-shopping', '#f59e0b'],
        ][$tipo] ?? ['fa-circle-question', '#6b7280'];
    }
}
