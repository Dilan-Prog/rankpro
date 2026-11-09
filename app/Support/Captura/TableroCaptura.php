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
        // No es mensual: ignora el mes; una unidad por cliente activo que es «hecha» cuando su perfil está completo.
        'perfil' => 'Perfil del cliente',
    ];

    /** Fuentes que son una medición mensual; `perfil` no cuenta para decidir si un cliente «no tiene fuentes». */
    private const MEDICIONES = ['keywords', 'seo', 'ads', 'metricas'];

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
     * @return array{mes: Carbon, filas: array<int, array>, resumen: array<string, int>}
     */
    public function calcular(Carbon $mes, bool $soloPendientes = false, ?int $clienteId = null): array
    {
        $mes = $mes->copy()->startOfMonth();

        // `$clienteId` lo usa solo el panel (cliente activo); la API lo deja en null y ve toda la cartera.
        $clientes = Cliente::query()
            ->where('estado', EstadoClienteServicio::Activo)
            ->when($clienteId, fn ($q) => $q->where('id', $clienteId))
            ->ordenadoPorNombreComercial()
            ->get(['id', 'nombre', 'empresa', 'contacto_nombre', 'email', 'telefono', 'fecha_renovacion_contrato']);
        $ids = $clientes->pluck('id')->all();

        $fuentes = [
            'keywords' => $this->keywords($ids, $mes),
            'seo' => $this->seo($ids, $mes),
            'ads' => $this->ads($ids, $mes),
            'metricas' => $this->metricas($ids, $mes),
            'perfil' => $this->perfil($clientes),
        ];

        $filas = [];
        $pendientes = 0;
        $total = 0;
        $conPendientes = 0;
        $sinFuentes = 0;

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
                if (in_array($clave, self::MEDICIONES, true)) {
                    $aplicables++;
                }
                if ($celda['estado'] !== self::COMPLETO) {
                    $pendientesFila++;
                }
            }

            // Un cliente sin fuentes no se oculta: que no salga era justo lo que hacía invisible que le falta configurar su medición.
            $sinFuente = $aplicables === 0;
            if ($sinFuente) {
                $sinFuentes++;
            }

            // El resumen cuenta todo el mes, aunque el filtro oculte filas completas.
            $pendientes += $pendientesFila;
            // El perfil es una celda más del tablero (cuenta en el total), pero no una fuente de medición.
            $total += $aplicables + ($celdas['perfil']['estado'] === self::NO_APLICA ? 0 : 1);
            if ($pendientesFila > 0) {
                $conPendientes++;
            }

            // El filtro oculta a los completos, pero deja a los que no tienen fuentes: tienen algo por configurar.
            if ($soloPendientes && $pendientesFila === 0 && ! $sinFuente) {
                continue;
            }

            $filas[] = [
                'cliente' => $cliente,
                'celdas' => $celdas,
                'pendientes' => $pendientesFila,
                'sin_fuentes' => $sinFuente,
                'configurar' => $sinFuente ? self::enlacesConfigurar($cliente) : [],
            ];
        }

        return [
            'mes' => $mes,
            'filas' => $filas,
            // `pendientes`/`total` cuentan CELDAS (se conservan por compatibilidad con la API); los demás cuentan clientes.
            'resumen' => [
                'pendientes' => $pendientes,
                'total' => $total,
                'celdas_pendientes' => $pendientes,
                'celdas_total' => $total,
                'clientes_con_pendientes' => $conPendientes,
                'clientes_activos' => $clientes->count(),
                'clientes_sin_fuentes' => $sinFuentes,
            ],
        ];
    }

    /** Dónde empezar a dar de alta cada fuente de un cliente que aún no tiene ninguna. */
    public static function enlacesConfigurar(Cliente $cliente): array
    {
        return [
            ['fuente' => 'keywords', 'etiqueta' => 'Crear lista de keywords', 'url' => route('admin.keywords.index')],
            ['fuente' => 'seo', 'etiqueta' => 'Crear campaña SEO', 'url' => route('admin.seo.index')],
            ['fuente' => 'ads', 'etiqueta' => 'Crear campaña de Ads', 'url' => route('admin.ads.index')],
            ['fuente' => 'metricas', 'etiqueta' => 'Definir una métrica', 'url' => route('admin.metricas.index', ['cliente' => $cliente->id])],
        ];
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

    /** Unidad = el cliente mismo; hecha = perfil completo (contacto, correo, WhatsApp y renovación). Ignora el mes. El enlace abre su edición. */
    private function perfil(Collection $clientes): array
    {
        $salida = [];
        foreach ($clientes as $cliente) {
            $salida[$cliente->id][] = ['hecho' => $cliente->perfilCompleto(), 'url' => route('admin.clientes.index', ['editar' => $cliente->id])];
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
