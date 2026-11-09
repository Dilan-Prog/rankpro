<?php

namespace App\Support\Reportes\Precarga;

use App\Enums\AreaReporte;
use App\Models\Reporte;
use App\Models\ReporteSeccion;
use App\Support\Reportes\Plantillas;
use Carbon\Carbon;

/**
 * Siembra un reporte "del mes" con datos ya capturados y los refresca después.
 *
 * "Actualizar datos" NUNCA pisa lo que alguien tocó: al sembrar se guarda la huella de
 * cada valor precargado (columna `reporte_secciones.precarga`); al refrescar solo se
 * reemplaza lo que sigue igual a su huella. Un KPI corregido a mano, un logro reescrito
 * o una serie con filas añadidas ya no coinciden y se conservan tal cual.
 */
class AplicadorPrecarga
{
    /** Campos del KPI que cuentan para saber si lo editaron (no `destacado`: es presentación). */
    private const CAMPOS_KPI = ['label', 'valor', 'formato', 'comparativo', 'direccion', 'detalle', 'desactualizado', 'fuente', 'fecha_dato'];

    public static function para(Reporte $reporte): Precarga
    {
        $reporte->loadMissing('cliente');
        $mes = Carbon::parse($reporte->periodo_inicio)->startOfMonth();

        return match ($reporte->area) {
            AreaReporte::Seo => new Seo($reporte->cliente, $mes, $reporte->seo_campana_id),
            AreaReporte::Ads => new Ads($reporte->cliente, $mes, $reporte->ads_campana_id),
            AreaReporte::Web => new Web($reporte->cliente, $mes),
        };
    }

    /**
     * @param  array<int, string>  $avanzadas  claves de secciones avanzadas elegidas
     */
    public function sembrar(Reporte $reporte, array $avanzadas = []): void
    {
        $datos = self::para($reporte)->secciones();

        foreach (Plantillas::paraMes($reporte->area, $avanzadas) as $i => $def) {
            $contenido = $def['contenido'];
            $meta = null;

            if ($def['precarga'] !== null && isset($datos[$def['precarga']])) {
                [$contenido, $meta] = $this->llenar($def['precarga'], $contenido, $datos[$def['precarga']]);
            }

            $reporte->secciones()->create([
                'tipo' => $def['tipo']->value,
                'titulo' => $def['titulo'],
                'orden' => $i + 1,
                'contenido' => $contenido,
                'precarga' => $meta,
                'visible' => true,
            ]);
        }
    }

    /**
     * Contenido de un resumen para el cliente de un reporte del mes, para cuando alguien
     * añade esa sección a mano a un reporte que no la traía.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}|null
     */
    public function resumenPara(Reporte $reporte): ?array
    {
        if (! $reporte->esDelMes()) {
            return null;
        }

        $datos = self::para($reporte)->secciones();

        return isset($datos['resumen']) ? $this->llenar('resumen', [], $datos['resumen']) : null;
    }

    /**
     * @param  array<string, mixed>  $base  contenido vacío de la plantilla
     * @param  array<string, mixed>  $fresco  lo que entrega la Precarga
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function llenar(string $clave, array $base, array $fresco): array
    {
        $ahora = now()->toIso8601String();

        return match ($clave) {
            'kpis' => [
                ['items' => $fresco['items']],
                ['clave' => 'kpis', 'items' => $this->huellasKpi($fresco['items']), 'actualizado_en' => $ahora],
            ],
            'resumen' => [
                $fresco,
                ['clave' => 'resumen', 'partes' => $this->huellasResumen($fresco), 'actualizado_en' => $ahora],
            ],
            'serie' => [
                array_replace($base, $fresco),
                ['clave' => 'serie', 'huella' => $this->huellaSerie($fresco), 'actualizado_en' => $ahora],
            ],
            default => [
                array_replace($base, $fresco),
                ['clave' => $clave, 'huella' => $this->huellaTabla($fresco), 'actualizado_en' => $ahora],
            ],
        };
    }

    /**
     * Refresca lo precargado de un borrador. Devuelve cuántos valores se actualizaron,
     * cuántos se respetaron por estar editados y cuántos KPIs siguen sin dato.
     *
     * @return array{actualizados: int, conservados: int, sin_dato: int}
     */
    public function actualizar(Reporte $reporte): array
    {
        $datos = self::para($reporte)->secciones();
        $resultado = ['actualizados' => 0, 'conservados' => 0, 'sin_dato' => 0];

        foreach ($reporte->secciones()->get() as $seccion) {
            $meta = $seccion->precarga;
            $clave = is_array($meta) ? ($meta['clave'] ?? null) : null;

            if ($clave === null || ! isset($datos[$clave])) {
                continue;
            }

            [$contenido, $nuevaMeta] = match ($clave) {
                'kpis' => $this->refrescarKpis($seccion, $meta, $datos[$clave], $resultado),
                'resumen' => $this->refrescarResumen($seccion, $meta, $datos[$clave], $resultado),
                'serie' => $this->refrescarSerie($seccion, $meta, $datos[$clave], $resultado),
                default => $this->refrescarTabla($seccion, $meta, $datos[$clave], $resultado),
            };

            if ($contenido != $seccion->contenido || $nuevaMeta != $meta) {
                $nuevaMeta['actualizado_en'] = now()->toIso8601String();
                $seccion->update(['contenido' => $contenido, 'precarga' => $nuevaMeta]);
            }
        }

        $resultado['sin_dato'] = $this->contarSinDato($reporte);

        return $resultado;
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function refrescarKpis(ReporteSeccion $seccion, array $meta, array $fresco, array &$res): array
    {
        $contenido = $seccion->contenido ?? ['items' => []];
        $items = array_values(is_array($contenido['items'] ?? null) ? $contenido['items'] : []);
        $huellas = is_array($meta['items'] ?? null) ? $meta['items'] : [];

        $posicion = [];
        foreach ($items as $i => $item) {
            if (is_array($item) && ($item['clave'] ?? '') !== '') {
                $posicion[$item['clave']] = $i;
            }
        }

        foreach ($fresco['items'] as $nuevo) {
            $k = $nuevo['clave'];

            if (isset($posicion[$k])) {
                $actual = $items[$posicion[$k]];
                $intacto = isset($huellas[$k]) && Huella::de($this->soloKpi($actual)) === $huellas[$k];

                if (! $intacto) {
                    $res['conservados']++;

                    continue;
                }
                // Por huella y no por ===: MySQL reordena las claves de un JSON al guardarlo.
                if (Huella::de($this->soloKpi($actual)) === Huella::de($this->soloKpi($nuevo))) {
                    continue;
                }

                $items[$posicion[$k]] = $nuevo;
                $items[$posicion[$k]]['destacado'] = (bool) ($actual['destacado'] ?? $nuevo['destacado']);
                $huellas[$k] = Huella::de($this->soloKpi($items[$posicion[$k]]));
                $res['actualizados']++;

                continue;
            }

            // Un KPI que sembró la precarga y el equipo borró no vuelve a aparecer solo.
            if (isset($huellas[$k])) {
                continue;
            }

            $items[] = $nuevo;
            $huellas[$k] = Huella::de($this->soloKpi($nuevo));
            $res['actualizados']++;
        }

        return [['items' => $items] + $contenido, ['clave' => 'kpis', 'items' => $huellas] + $meta];
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function refrescarResumen(ReporteSeccion $seccion, array $meta, array $fresco, array &$res): array
    {
        $contenido = $seccion->contenido ?? [];
        $partes = is_array($meta['partes'] ?? null) ? $meta['partes'] : [];
        $nuevas = $this->huellasResumen($fresco);

        foreach (['logros', 'retos'] as $lista) {
            $actual = $this->huellaTextos($contenido[$lista] ?? []);

            if (($partes[$lista] ?? null) !== $actual) {
                $res['conservados']++;

                continue;
            }
            if ($actual !== $nuevas[$lista]) {
                $contenido[$lista] = $fresco[$lista];
                $res['actualizados']++;
            }
            $partes[$lista] = $nuevas[$lista];
        }

        $actual = $this->huellaGrafica($contenido['grafica'] ?? []);
        if (($partes['grafica'] ?? null) !== $actual) {
            $res['conservados']++;
        } else {
            if ($actual !== $nuevas['grafica']) {
                $contenido['grafica'] = $fresco['grafica'];
                $res['actualizados']++;
            }
            $partes['grafica'] = $nuevas['grafica'];
        }

        return [$contenido, ['partes' => $partes] + $meta];
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function refrescarSerie(ReporteSeccion $seccion, array $meta, array $fresco, array &$res): array
    {
        $contenido = $seccion->contenido ?? [];

        if (($meta['huella'] ?? null) !== $this->huellaSerie($contenido)) {
            $res['conservados']++;

            return [$contenido, $meta];
        }

        $nueva = $this->huellaSerie($fresco);
        if ($nueva !== $meta['huella']) {
            $contenido = array_replace($contenido, ['series' => $fresco['series'], 'filas' => $fresco['filas']]);
            $res['actualizados']++;
        }

        return [$contenido, ['huella' => $nueva] + $meta];
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function refrescarTabla(ReporteSeccion $seccion, array $meta, array $fresco, array &$res): array
    {
        $contenido = $seccion->contenido ?? [];

        if (($meta['huella'] ?? null) !== $this->huellaTabla($contenido)) {
            $res['conservados']++;

            return [$contenido, $meta];
        }

        $nueva = $this->huellaTabla($fresco);
        if ($nueva !== $meta['huella']) {
            $contenido = array_replace($contenido, array_intersect_key($fresco, array_flip(['columnas', 'filas'])));
            $res['actualizados']++;
        }

        return [$contenido, ['huella' => $nueva] + $meta];
    }

    private function contarSinDato(Reporte $reporte): int
    {
        $n = 0;
        foreach ($reporte->secciones()->get() as $seccion) {
            if (($seccion->precarga['clave'] ?? null) !== 'kpis') {
                continue;
            }
            foreach ($seccion->contenido['items'] ?? [] as $item) {
                if (! empty($item['precargada']) && ($item['valor'] ?? '') === '' && str_starts_with((string) ($item['desactualizado'] ?? ''), 'Sin dato')) {
                    $n++;
                }
            }
        }

        return $n;
    }

    /** @return array<string, string> */
    private function huellasKpi(array $items): array
    {
        $huellas = [];
        foreach ($items as $item) {
            $huellas[$item['clave']] = Huella::de($this->soloKpi($item));
        }

        return $huellas;
    }

    /** @return array<string, mixed> */
    private function soloKpi(array $item): array
    {
        return array_intersect_key($item + array_fill_keys(self::CAMPOS_KPI, ''), array_flip(self::CAMPOS_KPI));
    }

    /** @return array<string, string> */
    private function huellasResumen(array $resumen): array
    {
        return [
            'logros' => $this->huellaTextos($resumen['logros'] ?? []),
            'retos' => $this->huellaTextos($resumen['retos'] ?? []),
            'grafica' => $this->huellaGrafica($resumen['grafica'] ?? []),
        ];
    }

    private function huellaTextos(array $lista): string
    {
        return Huella::de(array_map(fn ($f) => is_array($f) ? ($f['texto'] ?? '') : '', array_values($lista)));
    }

    private function huellaGrafica(array $g): string
    {
        return Huella::de([$g['series'] ?? [], $g['filas'] ?? []]);
    }

    private function huellaSerie(array $c): string
    {
        return Huella::de([$c['series'] ?? [], $c['filas'] ?? []]);
    }

    private function huellaTabla(array $c): string
    {
        return Huella::de($c['filas'] ?? []);
    }
}
