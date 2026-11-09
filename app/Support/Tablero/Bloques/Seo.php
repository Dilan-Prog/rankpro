<?php

namespace App\Support\Tablero\Bloques;

use App\Enums\EstadoCampana;
use App\Models\GscMetricaMensual;
use App\Models\GscPropiedad;
use App\Models\SeoCampana;
use App\Models\SeoMetricaMensual;
use App\Support\Tablero\Contexto;
use Carbon\CarbonInterface;

/**
 * SEO orgánico: lo capturado a mano por campaña (SeoMetricaMensual) y, si el cliente tiene propiedad,
 * lo sincronizado de Search Console. Se muestra el mes MÁS RECIENTE con dato; nunca se rellena un mes vacío.
 */
class Seo extends Bloque
{
    public function clave(): string
    {
        return 'seo';
    }

    public function titulo(): string
    {
        return 'SEO orgánico';
    }

    public function icono(): string
    {
        return 'fa-magnifying-glass-chart';
    }

    public function permisos(): array
    {
        return ['seo'];
    }

    public function calcular(Contexto $ctx): array
    {
        $cliente = $ctx->cliente;

        $campanas = SeoCampana::query()->where('cliente_id', $cliente->id)->orderBy('id')->get(['id', 'nombre', 'estado']);
        $activa = $campanas->first(fn ($c) => $c->estado === EstadoCampana::Activa) ?? $campanas->first();
        $enlace = $activa
            ? ['url' => route('admin.seo.show', $activa->id), 'etiqueta' => 'Abrir campaña SEO']
            : ['url' => route('admin.seo.index'), 'etiqueta' => 'Ir a SEO'];

        $manual = $campanas->isEmpty() ? [] : $this->filasDelUltimoMes($campanas->pluck('id')->all());
        $propiedad = GscPropiedad::query()->where('cliente_id', $cliente->id)->first();
        $gsc = $propiedad ? GscMetricaMensual::query()->where('propiedad_id', $propiedad->id)->orderByDesc('periodo')->first() : null;

        $cifras = [];
        $capturas = [];

        if ($manual !== []) {
            $mes = (int) $manual[0]->mes;
            $anio = (int) $manual[0]->anio;
            $periodo = self::periodo($mes, $anio);
            $capturado = self::masReciente(array_map(fn ($f) => $f->updated_at, $manual));
            $capturas[] = $capturado;
            $fuente = 'Captura manual · Módulo SEO ('.($campanas->count() === 1 ? 'campaña «'.$campanas->first()->nombre.'»' : $campanas->count().' campañas').')';
            $extra = ['capturado' => self::fecha($capturado)];

            $suma = fn (string $campo) => collect($manual)->sum(fn ($f) => (int) $f->{$campo});
            $hay = fn (string $campo) => collect($manual)->contains(fn ($f) => $f->{$campo} !== null);
            $valor = fn (string $campo) => $hay($campo) ? self::entero($suma($campo)) : null;

            $cifras[] = self::cifra('Tráfico orgánico', $valor('trafico_organico'), $periodo, $fuente, $extra);
            $cifras[] = self::cifra('Keywords en top 3', $valor('keywords_top3'), $periodo, $fuente, $extra);
            $cifras[] = self::cifra('Keywords en top 10', $valor('keywords_top10'), $periodo, $fuente, $extra);
            $cifras[] = self::cifra('Keywords en top 100', $valor('keywords_top100'), $periodo, $fuente, $extra);
            $cifras[] = self::cifra('Backlinks', $valor('backlinks_total'), $periodo, $fuente, $extra);
            $cifras[] = self::cifra('Errores técnicos pendientes', $valor('errores_pendientes'), $periodo, $fuente, $extra);
        }

        $gscFallo = false;
        if ($propiedad && $gsc) {
            $sync = $propiedad->ultima_sync_en;
            $capturas[] = $sync;
            $gscFallo = $propiedad->ultimo_ok === false;
            $periodoGsc = self::periodo((int) $gsc->periodo->month, (int) $gsc->periodo->year);
            $fuenteGsc = 'Search Console · '.$propiedad->site_url;
            $extra = ['capturado' => self::fecha($sync), 'parcial' => (bool) $gsc->parcial];
            // Un mes en curso de Search Console es parcial: se avisa para que nadie lo compare con un mes cerrado.
            $nota = $gsc->parcial ? 'Mes parcial: aún no termina.' : null;

            $cifras[] = self::cifra('Clics (Search Console)', self::entero($gsc->clics), $periodoGsc, $fuenteGsc, $extra + ['nota' => $nota]);
            $cifras[] = self::cifra('Impresiones (Search Console)', self::entero($gsc->impresiones), $periodoGsc, $fuenteGsc, $extra + ['nota' => $nota]);
            $cifras[] = self::cifra('Posición media (Search Console)', $gsc->posicion_media === null ? null : number_format($gsc->posicion_media, 1), $periodoGsc, $fuenteGsc, $extra + ['nota' => $nota]);
        }

        if ($cifras === []) {
            $motivo = match (true) {
                $campanas->isNotEmpty() => 'Hay '.$campanas->count().' '.($campanas->count() === 1 ? 'campaña SEO' : 'campañas SEO').', pero ninguna tiene métricas mensuales capturadas.',
                $propiedad !== null => 'Hay una propiedad de Search Console, pero todavía no se sincroniza ningún mes.',
                default => 'Sin campaña SEO ni propiedad de Search Console para este cliente.',
            };

            return $this->bloque(self::NEUTRO, $motivo, [], $enlace);
        }

        $ultima = self::masReciente($capturas);
        if ($gscFallo) {
            $estado = self::AMARILLO;
            $motivo = 'La última sincronización con Search Console falló.';
        } elseif (self::desactualizado($ultima, $ctx->hoy)) {
            $estado = self::AMARILLO;
            $motivo = 'Sin actualizar desde el '.self::fecha($ultima).' (más de '.self::DIAS_SIN_ACTUALIZAR.' días).';
        } else {
            $estado = self::VERDE;
            $motivo = 'Datos al día (última captura: '.self::fecha($ultima).').';
        }

        return $this->bloque($estado, $motivo, $cifras, $enlace);
    }

    /**
     * Filas del mes más reciente con dato, una por campaña (la de mayor ciclo si hubiera varias).
     *
     * @param  list<int>  $campanaIds
     * @return list<SeoMetricaMensual>
     */
    private function filasDelUltimoMes(array $campanaIds): array
    {
        $filas = SeoMetricaMensual::query()
            ->whereIn('seo_campana_id', $campanaIds)
            ->orderByDesc('anio')->orderByDesc('mes')->orderByDesc('ciclo')
            ->limit(50)
            ->get();

        if ($filas->isEmpty()) {
            return [];
        }

        $primera = $filas->first();

        return $filas
            ->filter(fn ($f) => $f->mes === $primera->mes && $f->anio === $primera->anio)
            ->unique('seo_campana_id')
            ->values()
            ->all();
    }
}
