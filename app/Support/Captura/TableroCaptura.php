<?php

namespace App\Support\Captura;

use App\Enums\EstadoCampana;
use App\Enums\EstadoClienteServicio;
use App\Enums\EstadoKeyword;
use App\Models\AdsCampana;
use App\Models\AdsMetrica;
use App\Models\Cliente;
use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Models\KeywordMedicion;
use App\Models\MetricaCliente;
use App\Models\MetricaClienteValor;
use App\Models\SeoCampana;
use App\Models\SeoMetricaMensual;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Tablero "pendientes de captura del mes": toda la captura del proyecto es
 * manual y lo que se pierde es la constancia, así que aquí se cruza, por
 * cliente activo y fuente, qué ya se midió en el mes y qué no.
 *
 * Una consulta agregada por fuente (nunca una por cliente) para que el tablero
 * no se degrade al crecer la cartera. Cada fuente devuelve, por cliente, la
 * lista de "unidades de captura" y si cada una ya tiene dato en el mes.
 */
class TableroCaptura
{
    public const FUENTES = [
        'keywords' => 'Posiciones de keywords',
        'seo' => 'Métricas SEO',
        'ads' => 'Métricas Ads',
        'metricas' => 'Métricas personalizadas',
    ];

    public const COMPLETO = 'completo';
    public const PARCIAL = 'parcial';
    public const FALTA = 'falta';
    public const NO_APLICA = 'no_aplica';

    /** Acepta "2026-10"; cualquier otra cosa (incluido un arreglo `?mes[]=`) cae al mes actual. */
    public static function resolverMes(mixed $valor): Carbon
    {
        if (is_string($valor) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $valor)) {
            return Carbon::createFromFormat('!Y-m', $valor);
        }

        return now()->startOfMonth();
    }

    /**
     * @return array{mes: Carbon, filas: array<int, array>, resumen: array{pendientes: int, total: int}}
     */
    public function calcular(Carbon $mes, bool $soloPendientes = false): array
    {
        $mes = $mes->copy()->startOfMonth();

        $clientes = Cliente::query()
            ->where('estado', EstadoClienteServicio::Activo)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
        $ids = $clientes->pluck('id')->all();

        $fuentes = [
            'keywords' => $this->keywords($ids, $mes),
            'seo' => $this->seo($ids, $mes),
            'ads' => $this->ads($ids, $mes),
            'metricas' => $this->metricas($ids, $mes),
        ];

        $filas = [];
        $pendientes = 0;
        $total = 0;

        foreach ($clientes as $cliente) {
            $celdas = [];
            $pendientesFila = 0;
            $aplicables = 0;

            foreach ($fuentes as $clave => $porCliente) {
                $celda = self::celda($porCliente[$cliente->id] ?? []);
                $celdas[$clave] = $celda;

                if ($celda['estado'] === self::NO_APLICA) {
                    continue;
                }
                $aplicables++;
                if ($celda['estado'] !== self::COMPLETO) {
                    $pendientesFila++;
                }
            }

            // Sin ninguna fuente que genere captura, el cliente no tiene nada que medir.
            if ($aplicables === 0) {
                continue;
            }

            // El resumen cuenta todo el mes, aunque el filtro oculte filas completas.
            $pendientes += $pendientesFila;
            $total += $aplicables;

            if ($soloPendientes && $pendientesFila === 0) {
                continue;
            }

            $filas[] = ['cliente' => $cliente, 'celdas' => $celdas, 'pendientes' => $pendientesFila];
        }

        return ['mes' => $mes, 'filas' => $filas, 'resumen' => ['pendientes' => $pendientes, 'total' => $total]];
    }

    /**
     * @param  array<int, array{hecho: bool, parcial?: bool, url: string}>  $unidades
     * @return array{estado: string, hechas: int, total: int, url: ?string}
     */
    public static function celda(array $unidades): array
    {
        $total = count($unidades);
        if ($total === 0) {
            return ['estado' => self::NO_APLICA, 'hechas' => 0, 'total' => 0, 'url' => null];
        }

        $hechas = count(array_filter($unidades, fn ($u) => $u['hecho']));
        // Una unidad a medias (lista con parte de sus keywords medidas) cuenta como pendiente pero hace `parcial` a la celda.
        $enCurso = $hechas > 0 || count(array_filter($unidades, fn ($u) => $u['parcial'] ?? false)) > 0;
        $estado = $hechas === $total ? self::COMPLETO : ($enCurso ? self::PARCIAL : self::FALTA);

        // El enlace lleva a la primera unidad pendiente; si está completo, a la primera.
        $destino = collect($unidades)->firstWhere('hecho', false) ?? reset($unidades);

        return ['estado' => $estado, 'hechas' => $hechas, 'total' => $total, 'url' => $destino['url']];
    }

    /**
     * Unidad = lista con keywords vivas; hecha = TODAS sus keywords vivas tienen una medición en el mes
     * (posición null cuenta: "medida, no rankea"). Una ronda a medias deja la unidad en `parcial` y no
     * suma a "hechas", para no dar por completa una lista de 50 con una sola medición.
     */
    private function keywords(array $clientes, Carbon $mes): array
    {
        $listas = KeywordLista::query()
            ->whereIn('cliente_id', $clientes)
            ->where('estado', '!=', EstadoKeyword::Descartada)
            // Una lista creada después del mes consultado no podía medirse entonces.
            ->where('created_at', '<=', $mes->copy()->endOfMonth())
            ->orderBy('id')
            ->get(['id', 'cliente_id']);

        $vivas = Keyword::query()
            ->whereIn('lista_id', $listas->pluck('id'))
            ->where('estado', '!=', EstadoKeyword::Descartada->value)
            ->groupBy('lista_id')
            ->selectRaw('lista_id, count(*) as total')
            ->pluck('total', 'lista_id');

        // Se cuentan keywords distintas y vivas: varias mediciones de una misma keyword no inflan el avance.
        $medidas = KeywordMedicion::query()
            ->join('keywords', 'keywords.id', '=', 'keyword_mediciones.keyword_id')
            ->whereIn('keyword_mediciones.lista_id', $vivas->keys())
            ->where('keywords.estado', '!=', EstadoKeyword::Descartada->value)
            ->whereBetween('keyword_mediciones.fecha', [$mes->toDateString(), $mes->copy()->endOfMonth()->toDateString()])
            ->groupBy('keyword_mediciones.lista_id')
            ->selectRaw('keyword_mediciones.lista_id as lista_id, count(distinct keyword_mediciones.keyword_id) as medidas')
            ->pluck('medidas', 'lista_id');

        // La pantalla de keywords no acepta filtros por URL: el enlace va al banco.
        $url = route('admin.keywords.index');
        $salida = [];
        foreach ($listas as $lista) {
            $total = (int) ($vivas[$lista->id] ?? 0);
            // Lista vacía o con todo descartado: no se puede medir, no genera unidad.
            if ($total === 0) {
                continue;
            }
            $hechas = (int) ($medidas[$lista->id] ?? 0);
            $salida[$lista->cliente_id][] = ['hecho' => $hechas >= $total, 'parcial' => $hechas > 0 && $hechas < $total, 'url' => $url];
        }

        return $salida;
    }

    /** Unidad = campaña SEO activa ya iniciada en el mes; hecha = fila de SeoMetricaMensual de ese mes+año. */
    private function seo(array $clientes, Carbon $mes): array
    {
        $campanas = SeoCampana::query()
            ->whereIn('cliente_id', $clientes)
            ->where('estado', EstadoCampana::Activa)
            ->where(fn ($q) => $q->whereNull('fecha_inicio')->orWhere('fecha_inicio', '<=', $mes->copy()->endOfMonth()->toDateString()))
            ->orderBy('id')
            ->get(['id', 'cliente_id']);

        $hechas = SeoMetricaMensual::query()
            ->whereIn('seo_campana_id', $campanas->pluck('id'))
            ->where('mes', $mes->month)->where('anio', $mes->year)
            ->distinct()->pluck('seo_campana_id')->flip();

        return $this->porCampana($campanas, $hechas, fn ($c) => route('admin.seo.show', $c->id).'#metricasCard');
    }

    /** Igual que SEO con AdsMetrica y, además, sin campañas cuya fecha_fin ya pasó (SeoCampana no tiene fecha_fin); se ignora el ciclo porque la captura es del mes calendario. */
    private function ads(array $clientes, Carbon $mes): array
    {
        $campanas = AdsCampana::query()
            ->whereIn('cliente_id', $clientes)
            ->where('estado', EstadoCampana::Activa)
            ->where(fn ($q) => $q->whereNull('fecha_inicio')->orWhere('fecha_inicio', '<=', $mes->copy()->endOfMonth()->toDateString()))
            // Una campaña que terminó antes del mes consultado ya no exige captura aunque siga marcada activa.
            ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $mes->toDateString()))
            ->orderBy('id')
            ->get(['id', 'cliente_id']);

        $hechas = AdsMetrica::query()
            ->whereIn('ads_campana_id', $campanas->pluck('id'))
            ->where('mes', $mes->month)->where('anio', $mes->year)
            ->distinct()->pluck('ads_campana_id')->flip();

        return $this->porCampana($campanas, $hechas, fn ($c) => route('admin.ads.show', $c->id).'#metricasCard');
    }

    /** Unidad = métrica activa del cliente; hecha = valor con periodo = día 1 del mes. */
    private function metricas(array $clientes, Carbon $mes): array
    {
        $definiciones = MetricaCliente::query()
            ->whereIn('cliente_id', $clientes)
            ->where('activa', true)
            ->orderBy('id')
            ->get(['id', 'cliente_id']);

        $hechas = MetricaClienteValor::query()
            ->whereIn('metrica_id', $definiciones->pluck('id'))
            ->whereDate('periodo', $mes->toDateString())
            ->distinct()->pluck('metrica_id')->flip();

        $salida = [];
        foreach ($definiciones as $m) {
            $salida[$m->cliente_id][] = [
                'hecho' => $hechas->has($m->id),
                'url' => route('admin.metricas.index', ['cliente' => $m->cliente_id, 'mes' => $mes->format('Y-m')]),
            ];
        }

        return $salida;
    }

    private function porCampana(Collection $campanas, Collection $hechas, callable $url): array
    {
        $salida = [];
        foreach ($campanas as $c) {
            $salida[$c->cliente_id][] = ['hecho' => $hechas->has($c->id), 'url' => $url($c)];
        }

        return $salida;
    }
}
