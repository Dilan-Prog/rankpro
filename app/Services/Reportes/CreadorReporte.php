<?php

namespace App\Services\Reportes;

use App\Enums\AreaReporte;
use App\Enums\EstadoReporte;
use App\Models\Cliente;
use App\Models\Reporte;
use App\Support\Reportes\Plantillas;
use App\Support\Reportes\Precarga\AplicadorPrecarga;
use App\Support\Reportes\Precarga\Periodo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Único sitio donde nace un reporte (panel y API): lo crea en borrador, le asigna
 * el folio y siembra sus secciones, todo en una transacción. El folio se asigna AQUÍ
 * y no al generar el primer PDF: así el borrador ya lo muestra y un render fallido
 * no consume nada.
 *
 * Un folio nunca se reutiliza: si el borrador se elimina, ese número queda vacío en
 * la serie (un hueco es aceptable; un folio repetido en dos documentos no).
 */
class CreadorReporte
{
    public function __construct(private PublicadorReporte $publicador)
    {
    }

    /**
     * Reporte personalizado: periodo libre y plantilla clásica con TODAS las secciones vacías.
     *
     * @param  array<string, mixed>  $datos  columnas de `reportes` ya validadas
     */
    public function crearPersonalizado(array $datos, ?int $usuarioId): Reporte
    {
        return $this->conFolio(function () use ($datos, $usuarioId) {
            $reporte = Reporte::create($datos + [
                'origen' => 'personalizado',
                'estado' => EstadoReporte::Borrador->value,
                'creado_por' => $usuarioId,
            ]);

            // Un reporte nace con la estructura de su área ya puesta (ver Plantillas):
            // el equipo abre el detalle y solo pega y redacta.
            foreach (Plantillas::para($reporte->area) as $i => $seccion) {
                $reporte->secciones()->create([
                    'tipo' => $seccion['tipo']->value,
                    'titulo' => $seccion['titulo'],
                    'orden' => $i + 1,
                    'contenido' => $seccion['contenido'],
                    'visible' => true,
                ]);
            }

            return $reporte;
        });
    }

    /**
     * "Generar reporte del mes": un mes calendario completo, título autogenerado y
     * secciones precargadas desde lo ya capturado. Compara contra el mes anterior.
     *
     * @param  array<int, string>  $avanzadas
     */
    public function crearDelMes(Cliente $cliente, AreaReporte $area, Carbon $mes, array $avanzadas, ?int $usuarioId, ?int $seoCampanaId = null, ?int $adsCampanaId = null): Reporte
    {
        $mes = $mes->copy()->startOfMonth();
        $previo = $mes->copy()->subMonthNoOverflow();

        return $this->conFolio(function () use ($cliente, $area, $mes, $previo, $avanzadas, $usuarioId, $seoCampanaId, $adsCampanaId) {
            $reporte = Reporte::create([
                'cliente_id' => $cliente->id,
                'area' => $area->value,
                'origen' => 'mes',
                'titulo' => 'Reporte '.$area->label().' — '.$cliente->nombre_comercial.' — '.Periodo::titulo($mes),
                'periodo_inicio' => $mes->toDateString(),
                'periodo_fin' => $mes->copy()->endOfMonth()->toDateString(),
                'comparativa_inicio' => $previo->toDateString(),
                'comparativa_fin' => $previo->copy()->endOfMonth()->toDateString(),
                'seo_campana_id' => $seoCampanaId,
                'ads_campana_id' => $adsCampanaId,
                'estado' => EstadoReporte::Borrador->value,
                'creado_por' => $usuarioId,
            ]);

            (new AplicadorPrecarga)->sembrar($reporte->load('cliente'), $avanzadas);

            return $reporte;
        });
    }

    /**
     * Crea, asigna folio y devuelve el reporte fresco. Si dos altas simultáneas calculan el
     * mismo folio gana una (índice único de `numero`) y la otra reintenta con el siguiente.
     *
     * @param  callable(): Reporte  $crear
     */
    private function conFolio(callable $crear): Reporte
    {
        $intentos = 0;

        while (true) {
            try {
                return DB::transaction(function () use ($crear) {
                    $reporte = $crear();
                    $this->publicador->asignarFolio($reporte);

                    return $reporte;
                });
            } catch (\Illuminate\Database\QueryException $e) {
                // 23000 = violación de unicidad; cualquier otro error no es una carrera de folio.
                if ($e->getCode() !== '23000' || ++$intentos >= 5) {
                    throw $e;
                }
            }
        }
    }
}
