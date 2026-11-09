<?php

namespace App\Support\Calidad;

use App\Models\AdsCampana;
use App\Models\Keyword;
use App\Models\KeywordMedicion;
use App\Models\MetricaCliente;
use App\Models\MetricaClienteValor;
use App\Models\SeoCampana;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Cuenta las cifras con más días sin actualizarse que su umbral, por módulo, para el aviso de Inicio.
 *
 * Solo cuenta datos que EXISTEN y son viejos: sin dato (sin auditoría, sin métricas, sin mediciones)
 * no es "antiguo", es "sin dato", y avisarlo aquí sería ruido en un sistema recién poblado.
 * Respeta permisos: un módulo al que el usuario no entra no aparece.
 */
class DatosDesactualizados
{
    /**
     * @return list<array{modulo: string, etiqueta: string, cantidad: int, href: string}>
     */
    public static function detectar(?User $usuario = null): array
    {
        $permitido = fn (string $modulo): bool => $usuario === null || $usuario->tienePermiso($modulo);
        $avisos = [];

        // Si aún no se corrió la migración de fecha_auditoria no hay forma de saber la edad del score.
        if ($permitido('seo') && Schema::hasColumn('seo_fase_auditoria', 'fecha_auditoria')) {
            $n = SeoCampana::where('estado', 'activa')->with('faseAuditoria')->get()
                ->filter(fn (SeoCampana $c) => $c->faseAuditoria?->seo_score !== null
                    && Antiguedad::evaluar($c->faseAuditoria->fecha_auditoria, null, Antiguedad::TIPO_AUDITORIA)['estado'] === Antiguedad::ANTIGUO)
                ->count();
            $avisos[] = ['modulo' => 'seo', 'etiqueta' => 'SEO Score con auditoría de hace más de '.Antiguedad::UMBRAL_AUDITORIA.' días', 'cantidad' => $n, 'href' => route('admin.seo.index')];
        }

        if ($permitido('ads')) {
            $n = AdsCampana::where('estado', 'activa')->with('metricas:id,ads_campana_id,mes,anio')->get()
                ->filter(function (AdsCampana $c) {
                    $ultima = $c->metricas->sortByDesc(fn ($m) => $m->anio * 12 + $m->mes)->first();

                    return $ultima !== null
                        && Antiguedad::evaluar(Antiguedad::finDeMes((int) $ultima->mes, (int) $ultima->anio))['estado'] === Antiguedad::ANTIGUO;
                })->count();
            $avisos[] = ['modulo' => 'ads', 'etiqueta' => 'campañas de Ads con la última métrica de hace más de '.Antiguedad::UMBRAL_GENERAL.' días', 'cantidad' => $n, 'href' => route('admin.ads.index')];
        }

        if ($permitido('metricas')) {
            $activas = MetricaCliente::where('activa', true)->pluck('id');
            $n = MetricaClienteValor::whereIn('metrica_id', $activas)
                ->selectRaw('metrica_id, MAX(periodo) as ultimo')->groupBy('metrica_id')->get()
                ->filter(fn ($v) => Antiguedad::evaluar(Antiguedad::aCarbon($v->ultimo)?->endOfMonth())['estado'] === Antiguedad::ANTIGUO)
                ->count();
            $avisos[] = ['modulo' => 'metricas', 'etiqueta' => 'métricas de cliente con la última captura de hace más de '.Antiguedad::UMBRAL_GENERAL.' días', 'cantidad' => $n, 'href' => route('admin.metricas.index')];
        }

        if ($permitido('keywords')) {
            $n = KeywordMedicion::whereNotNull('posicion')
                ->selectRaw('keyword_id, MAX(fecha) as ultima')->groupBy('keyword_id')->get()
                ->filter(fn ($m) => Antiguedad::evaluar(Antiguedad::aCarbon($m->ultima))['estado'] === Antiguedad::ANTIGUO)
                ->count();
            $avisos[] = ['modulo' => 'keywords', 'etiqueta' => 'keywords con la última medición de hace más de '.Antiguedad::UMBRAL_GENERAL.' días', 'cantidad' => $n, 'href' => route('admin.keywords.index')];
        }

        return array_values(array_filter($avisos, fn (array $a) => $a['cantidad'] > 0));
    }
}
