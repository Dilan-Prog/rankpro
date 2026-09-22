<?php

namespace App\Support\Reglas;

use App\Enums\FaseSeo;

/**
 * Reglas de validación del autosave de fase SEO (el `match($fase)` de
 * SeoFaseController::guardar()), compartidas con el endpoint equivalente de
 * la API (App\Http\Controllers\Api\V1\Seo\SeoFaseApiController). El resto de
 * guardar() (leer el registro, fusionar el checklist, aplicar los booleanos)
 * sigue en cada controlador porque no es validación, es la máquina de fase
 * (App\Services\Fases\MaquinaSeo), ya compartida por ambos.
 */
class SeoFases
{
    /** @return array<string, array<int, mixed>> */
    public static function guardar(FaseSeo $fase): array
    {
        return match ($fase) {
            FaseSeo::Auditoria => [
                'seo_score' => ['nullable', 'integer', 'min:0', 'max:100'],
                'velocidad_mobile' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'velocidad_desktop' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'lcp_mobile' => ['nullable', 'numeric', 'min:0'],
                'fid_mobile' => ['nullable', 'numeric', 'min:0'],
                'cls_mobile' => ['nullable', 'numeric', 'min:0'],
                'lcp_desktop' => ['nullable', 'numeric', 'min:0'],
                'fid_desktop' => ['nullable', 'numeric', 'min:0'],
                'cls_desktop' => ['nullable', 'numeric', 'min:0'],
                'errores_tecnicos' => ['nullable', 'integer', 'min:0'],
                'indexacion_ok' => ['nullable', 'boolean'],
                'sitemap_ok' => ['nullable', 'boolean'],
                'robots_ok' => ['nullable', 'boolean'],
                'errores_404' => ['nullable', 'integer', 'min:0'],
                'redirecciones_incorrectas' => ['nullable', 'integer', 'min:0'],
                'duplicidad_contenido' => ['nullable', 'boolean'],
                'canonical_ok' => ['nullable', 'boolean'],
                'schema_ok' => ['nullable', 'boolean'],
                'herramienta' => ['nullable', 'in:semrush,ahrefs,screaming_frog,google_search_console,otro'],
                'notas' => ['nullable', 'string', 'max:2000'],
                'checklist' => ['nullable', 'array'],
                'checklist.*' => ['boolean'],
            ],
            FaseSeo::Estrategia => [
                'keywords_ids' => ['nullable', 'array'],
                'keywords_ids.*' => ['integer'],
                'analisis_competencia' => ['nullable', 'string', 'max:5000'],
                'plan_contenido' => ['nullable', 'string', 'max:5000'],
                'estrategia_link_building' => ['nullable', 'string', 'max:5000'],
                'meta_trafico_mensual' => ['nullable', 'integer', 'min:0'],
                'meta_top3' => ['nullable', 'integer', 'min:0'],
                'meta_top10' => ['nullable', 'integer', 'min:0'],
                'meta_leads_mensual' => ['nullable', 'integer', 'min:0'],
                'herramientas' => ['nullable', 'string', 'max:255'],
                'cronograma' => ['nullable', 'string', 'max:5000'],
                'notas' => ['nullable', 'string', 'max:2000'],
                'checklist' => ['nullable', 'array'],
                'checklist.*' => ['boolean'],
            ],
            FaseSeo::Ejecucion => [
                'porcentaje_avance' => ['nullable', 'integer', 'min:0', 'max:100'],
                'paginas_optimizadas' => ['nullable', 'integer', 'min:0'],
                'titles_meta_ok' => ['nullable', 'boolean'],
                'headings_ok' => ['nullable', 'boolean'],
                'imagenes_ok' => ['nullable', 'boolean'],
                'links_internos_ok' => ['nullable', 'boolean'],
                'backlinks_mes' => ['nullable', 'integer', 'min:0'],
                'errores_404_ok' => ['nullable', 'boolean'],
                'redirecciones_ok' => ['nullable', 'boolean'],
                'schema_ok' => ['nullable', 'boolean'],
                'velocidad_ok' => ['nullable', 'boolean'],
                'articulos_publicados' => ['nullable', 'integer', 'min:0'],
                'checklist' => ['nullable', 'array'],
                'checklist.*' => ['boolean'],
            ],
            FaseSeo::Reporte => [
                'trafico_inicio' => ['nullable', 'integer', 'min:0'],
                'trafico_actual' => ['nullable', 'integer', 'min:0'],
                'keywords_top3' => ['nullable', 'integer', 'min:0'],
                'keywords_top10' => ['nullable', 'integer', 'min:0'],
                'keywords_top100' => ['nullable', 'integer', 'min:0'],
                'backlinks_total' => ['nullable', 'integer', 'min:0'],
                'articulos_total' => ['nullable', 'integer', 'min:0'],
                'errores_resueltos' => ['nullable', 'integer', 'min:0'],
                'errores_pendientes' => ['nullable', 'integer', 'min:0'],
                'conclusiones' => ['nullable', 'string', 'max:5000'],
                'recomendaciones' => ['nullable', 'string', 'max:5000'],
                'satisfaccion_cliente' => ['nullable', 'integer', 'min:1', 'max:5'],
                'continua_campana' => ['nullable', 'boolean'],
                'notas_cierre' => ['nullable', 'string', 'max:2000'],
                'checklist' => ['nullable', 'array'],
                'checklist.*' => ['boolean'],
            ],
            FaseSeo::Cerrada => [],
        };
    }

    /**
     * Campos booleanos que llegan como checkbox HTML (ausentes si no están
     * marcados): necesitan un fallback explícito a false vía $request->boolean().
     *
     * @return array<int, string>
     */
    public static function booleanos(FaseSeo $fase): array
    {
        return match ($fase) {
            FaseSeo::Auditoria => ['indexacion_ok', 'sitemap_ok', 'robots_ok', 'duplicidad_contenido', 'canonical_ok', 'schema_ok'],
            FaseSeo::Ejecucion => ['titles_meta_ok', 'headings_ok', 'imagenes_ok', 'links_internos_ok', 'errores_404_ok', 'redirecciones_ok', 'schema_ok', 'velocidad_ok'],
            FaseSeo::Reporte => ['continua_campana'],
            default => [],
        };
    }

    /** @return array<string, array<int, mixed>> */
    public static function tecnico(): array
    {
        return [
            'tecnico_checklist' => ['nullable', 'array'],
            'tecnico_checklist.*' => ['boolean'],
        ];
    }
}
