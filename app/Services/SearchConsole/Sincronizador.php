<?php

namespace App\Services\SearchConsole;

use App\Enums\EstadoKeyword;
use App\Models\GscMetricaMensual;
use App\Models\GscPropiedad;
use App\Models\Keyword;
use App\Models\KeywordMedicion;
use App\Support\SearchConsole\NormalizadorKeyword;
use App\Support\SearchConsole\Saneador;
use App\Support\Webhooks\RondaKeywords;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Trae de Search Console los totales mensuales de una propiedad y, con las keywords del banco del
 * cliente, crea mediciones mensuales de posición.
 *
 * Decisiones de fechas y de no pisar nada:
 *  - La medición de un mes se fecha el ÚLTIMO DÍA de ese mes calendario ya cerrado. Search Console
 *    da un promedio del mes, no una foto de un día; el último día es el ancla que deja el mes
 *    ordenado detrás de cualquier captura manual hecha durante él y alinea la ronda con el tablero
 *    de Pendientes de captura (que cuenta por mes).
 *  - El mes en curso NO genera medición (el promedio cambiaría cada día y ensuciaría la caché
 *    posicion_actual); solo guarda el total mensual, marcado como parcial.
 *  - Si la keyword ya tiene CUALQUIER medición con fecha dentro del mes que no sea una importada
 *    por esta clase (fecha = fin de mes y nota de Search Console), se respeta y no se toca.
 *    Las importadas por nosotros sí se actualizan, porque Google tarda 2-3 días en cerrar el mes.
 */
class Sincronizador
{
    public const NOTA = 'Search Console · promedio del mes';

    public const MESES_COMPLETO = 16;

    public const MESES_RECIENTES = 3;

    public const MAX_PAGINAS_QUERY = 10;

    /**
     * Sincroniza una propiedad. Los errores de Google no se lanzan: quedan guardados en la propiedad
     * (ultimo_ok / ultimo_resultado) para que un cliente con problemas no frene a los demás.
     * Solo lanza SincronizacionEnCurso si otro proceso tiene el candado de esa propiedad.
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function sincronizar(GscPropiedad $propiedad, ?int $meses = null): array
    {
        $candado = Cache::lock("gsc:sincronizar:{$propiedad->id}", 900);
        if (! $candado->get()) {
            throw new SincronizacionEnCurso();
        }

        try {
            $meses ??= $propiedad->metricas()->exists() ? self::MESES_RECIENTES : self::MESES_COMPLETO;
            $meses = max(1, min(self::MESES_COMPLETO, $meses));

            try {
                $mensaje = $this->ejecutar($propiedad, ClienteSearchConsole::desdeConexion(), $meses);
                $ok = true;
            } catch (ErrorSearchConsole $e) {
                $ok = false;
                $mensaje = $e->getMessage();
            } catch (\Throwable $e) {
                // Un fallo inesperado de un cliente tampoco debe frenar al resto; se guarda sin rutas ni trazas.
                report($e);
                $ok = false;
                $mensaje = 'Error inesperado al sincronizar: '.$e->getMessage();
            }

            $mensaje = Saneador::mensaje($mensaje);
            $propiedad->forceFill(['ultima_sync_en' => now(), 'ultimo_ok' => $ok, 'ultimo_resultado' => $mensaje])->save();

            return ['ok' => $ok, 'mensaje' => $mensaje];
        } finally {
            $candado->release();
        }
    }

    private function ejecutar(GscPropiedad $propiedad, ClienteSearchConsole $api, int $meses): string
    {
        $hoy = Carbon::today();
        $mesActual = $hoy->copy()->startOfMonth();
        $primerMes = $mesActual->copy()->subMonths($meses - 1);

        $totales = $this->importarTotales($propiedad, $api, $primerMes, $hoy, $mesActual);

        $cerrados = [];
        for ($m = $primerMes->copy(); $m->lt($mesActual); $m->addMonth()) {
            $cerrados[] = $m->copy();
        }

        $keywords = Keyword::query()
            ->with('lista:id,estado')
            ->where('cliente_id', $propiedad->cliente_id)
            ->where('estado', '!=', EstadoKeyword::Descartada->value)
            ->get()
            ->filter(fn (Keyword $k) => $k->lista === null || $k->lista->estado !== EstadoKeyword::Descartada)
            ->values();

        if ($keywords->isEmpty() || $cerrados === []) {
            return "{$totales} meses de totales importados (el mes en curso queda parcial)."
                .($keywords->isEmpty() ? ' El cliente no tiene keywords en el banco: sin mediciones de posición.' : '');
        }

        $r = $this->importarMediciones($propiedad, $api, $keywords, $cerrados);

        return "{$totales} meses de totales importados (el mes en curso queda parcial). Mediciones de posición: "
            ."{$r['creadas']} nuevas, {$r['actualizadas']} actualizadas, {$r['respetadas']} respetadas (ya había una manual ese mes)"
            .($r['sin_dato'] > 0 ? ", {$r['sin_dato']} keyword-mes sin datos en Google" : '').'.';
    }

    private function importarTotales(GscPropiedad $propiedad, ClienteSearchConsole $api, Carbon $desde, Carbon $hasta, Carbon $mesActual): int
    {
        // 'all' incluye los últimos días aún provisionales; el mes en curso es parcial de todos modos
        // y los meses recientes se vuelven a traer en cada corrida.
        $filas = $api->consultar($propiedad->site_url, $desde->toDateString(), $hasta->toDateString(), ['date'], 'all');

        $porMes = [];
        foreach ($filas as $f) {
            $mes = substr($f['keys'][0] ?? '', 0, 7);
            if (! preg_match('/^\d{4}-\d{2}$/', $mes)) {
                continue;
            }
            $porMes[$mes] ??= ['clics' => 0, 'impresiones' => 0, 'pos' => 0.0];
            $porMes[$mes]['clics'] += $f['clics'];
            $porMes[$mes]['impresiones'] += $f['impresiones'];
            // Posición media del mes ponderada por impresiones, igual que la pondera Google.
            $porMes[$mes]['pos'] += $f['posicion'] * $f['impresiones'];
        }

        foreach ($porMes as $mes => $t) {
            $periodo = Carbon::parse("{$mes}-01");
            GscMetricaMensual::updateOrCreate(
                ['propiedad_id' => $propiedad->id, 'periodo' => $periodo->toDateString()],
                [
                    'clics' => $t['clics'],
                    'impresiones' => $t['impresiones'],
                    'ctr' => $t['impresiones'] > 0 ? round($t['clics'] / $t['impresiones'], 6) : 0,
                    'posicion_media' => $t['impresiones'] > 0 ? round($t['pos'] / $t['impresiones'], 2) : null,
                    'parcial' => $periodo->equalTo($mesActual),
                ]
            );
        }

        return count($porMes);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Keyword>  $keywords
     * @param  array<int, Carbon>  $cerrados
     * @return array{creadas: int, actualizadas: int, respetadas: int, sin_dato: int}
     */
    private function importarMediciones(GscPropiedad $propiedad, ClienteSearchConsole $api, $keywords, array $cerrados): array
    {
        $porNormalizada = $keywords->groupBy(fn (Keyword $k) => NormalizadorKeyword::normalizar($k->keyword));

        $existentes = KeywordMedicion::query()
            ->whereIn('keyword_id', $keywords->pluck('id'))
            ->whereBetween('fecha', [$cerrados[0]->toDateString(), end($cerrados)->copy()->endOfMonth()->toDateString()])
            ->get()
            ->groupBy('keyword_id');

        $r = ['creadas' => 0, 'actualizadas' => 0, 'respetadas' => 0, 'sin_dato' => 0];
        $tocadas = [];       // keyword_id => true, para recalcular posicion_actual/anterior
        $rondas = [];        // "lista_id|fecha" => [lista_id, fecha], una señal por ronda

        foreach ($cerrados as $inicio) {
            $fin = $inicio->copy()->endOfMonth()->startOfDay();
            $filas = $api->consultar($propiedad->site_url, $inicio->toDateString(), $fin->toDateString(), ['query'], 'final', self::MAX_PAGINAS_QUERY);

            // Dos consultas que normalizan igual (con/sin acento) se funden con posición ponderada por impresiones.
            $posiciones = [];
            foreach ($filas as $f) {
                $n = NormalizadorKeyword::normalizar($f['keys'][0] ?? '');
                if ($n === '' || ! $porNormalizada->has($n)) {
                    continue;
                }
                $posiciones[$n] ??= ['imp' => 0, 'pos' => 0.0];
                $peso = max($f['impresiones'], 1);
                $posiciones[$n]['imp'] += $peso;
                $posiciones[$n]['pos'] += $f['posicion'] * $peso;
            }

            foreach ($porNormalizada as $n => $grupo) {
                if (! isset($posiciones[$n])) {
                    $r['sin_dato'] += $grupo->count();

                    continue;
                }
                $posicion = max(1, (int) round($posiciones[$n]['pos'] / $posiciones[$n]['imp']));

                foreach ($grupo as $keyword) {
                    $delMes = ($existentes[$keyword->id] ?? collect())->filter(
                        fn (KeywordMedicion $m) => $m->fecha->between($inicio, $fin->copy()->endOfDay())
                    );
                    $propia = $delMes->first(fn (KeywordMedicion $m) => $this->esImportada($m, $fin));

                    // Cualquier medición del mes que no sea nuestra es manual: no se toca.
                    if ($delMes->count() > ($propia ? 1 : 0)) {
                        $r['respetadas']++;

                        continue;
                    }

                    if ($propia) {
                        if ($propia->posicion === $posicion) {
                            continue;
                        }
                        KeywordMedicion::withoutEvents(fn () => $propia->forceFill(['posicion' => $posicion])->save());
                        $r['actualizadas']++;
                    } else {
                        KeywordMedicion::withoutEvents(fn () => KeywordMedicion::create([
                            'keyword_id' => $keyword->id,
                            'lista_id' => $keyword->lista_id,
                            'fecha' => $fin->toDateString(),
                            'posicion' => $posicion,
                            'url' => null,
                            'nota' => self::NOTA,
                            'registrado_por' => null,
                        ]));
                        $r['creadas']++;
                    }

                    $tocadas[$keyword->id] = true;
                    if ($keyword->lista_id) {
                        $rondas["{$keyword->lista_id}|{$fin->toDateString()}"] = [$keyword->lista_id, $fin->toDateString()];
                    }
                }
            }
        }

        DB::transaction(function () use ($tocadas) {
            Keyword::whereIn('id', array_keys($tocadas))->get()->each(fn (Keyword $k) => $k->sincronizarPosiciones());
        });

        // Se silencia el evento por medición (16 meses x N keywords serían cientos de webhooks) y se
        // avisa una sola vez por ronda (lista + mes), como hace la captura manual.
        foreach ($rondas as [$listaId, $fecha]) {
            RondaKeywords::emitir($listaId, $fecha);
        }

        return $r;
    }

    private function esImportada(KeywordMedicion $m, Carbon $finDeMes): bool
    {
        return $m->registrado_por === null
            && $m->nota === self::NOTA
            && $m->fecha->toDateString() === $finDeMes->toDateString();
    }
}
