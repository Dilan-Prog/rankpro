<?php

namespace App\Support\Reportes\Precarga;

use App\Enums\AreaReporte;
use App\Models\Cliente;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Datos que ya están capturados en el sistema para un cliente y un MES calendario,
 * convertidos en contenido de secciones de reporte (KPIs, serie, tabla y resumen).
 *
 * Regla de oro: un dato que no existe NO se rellena con cero. Cada KPI sin dato del
 * mes sale con valor vacío y la marca `desactualizado` ("Sin dato de agosto 2026"),
 * porque un 0 inventado en un entregable al cliente es peor que un hueco visible.
 * Hoy casi todas las fuentes están vacías, así que "sin dato" es el caso normal.
 *
 * Cada subclase cubre un área y devuelve `secciones()`: clave de precarga => contenido.
 */
abstract class Precarga
{
    /** Meses (incluido el del reporte) que dibuja la gráfica de evolución. */
    public const MESES_SERIE = 6;

    protected Carbon $mes;

    /**
     * Cifras reales con las que SugerenciasResumen redacta logros y retos.
     *
     * @var array<string, mixed>
     */
    protected array $hechos = [];

    public function __construct(protected Cliente $cliente, Carbon $mes, protected ?int $campanaId = null)
    {
        $this->mes = $mes->copy()->startOfMonth();
    }

    abstract public function area(): AreaReporte;

    /**
     * @return array<string, array<string, mixed>> clave de precarga => contenido de la sección
     */
    abstract public function secciones(): array;

    /** Título de la gráfica clave del resumen para el cliente. */
    abstract protected function tituloGrafica(): string;

    public function mes(): Carbon
    {
        return $this->mes->copy();
    }

    protected function mesEtiqueta(): string
    {
        return Periodo::etiqueta($this->mes);
    }

    /**
     * Del más antiguo al mes del reporte.
     *
     * @return Collection<int, Carbon>
     */
    protected function mesesDeSerie(): Collection
    {
        return collect(range(self::MESES_SERIE - 1, 0))->map(fn (int $atras) => $this->mes->copy()->subMonthsNoOverflow($atras));
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function kpi(string $clave, string $label, ?float $valor, string $formato, string $fuente, ?string $fechaDato, array $extra = []): array
    {
        $item = [
            'clave' => $clave,
            'label' => $label,
            'valor' => $valor === null ? '' : self::numeroTexto($valor),
            'formato' => $formato,
            'comparativo' => '',
            'direccion' => 'neutral',
            'detalle' => '',
            'destacado' => false,
            'desactualizado' => '',
            'fuente' => $fuente,
            'fecha_dato' => $fechaDato,
            'precargada' => true,
        ];

        if ($valor === null) {
            $item['desactualizado'] = 'Sin dato de '.$this->mesEtiqueta();
            $item['fecha_dato'] = null;
        }

        return array_replace($item, $extra);
    }

    /**
     * Comparativo contra el mes anterior. Sin mes previo (o previo en 0, que no da
     * un porcentaje honesto) no se compara: se deja vacío, nunca "+0 %" inventado.
     *
     * @return array{comparativo: string, direccion: string, detalle: string}
     */
    protected function comparar(?float $actual, ?float $previo, bool $subirEsBueno): array
    {
        if ($actual === null || $previo === null || $previo == 0.0) {
            return ['comparativo' => '', 'direccion' => 'neutral', 'detalle' => ''];
        }

        $detalle = 'vs. '.Periodo::etiqueta($this->mes->copy()->subMonthNoOverflow());
        $pct = ($actual - $previo) / abs($previo) * 100;

        if (abs($pct) < 0.05) {
            return ['comparativo' => 'Sin cambio', 'direccion' => 'neutral', 'detalle' => $detalle];
        }

        return [
            'comparativo' => str_replace('.', ',', sprintf('%+.1f%%', $pct)),
            'direccion' => (($pct > 0) === $subirEsBueno) ? 'positiva' : 'negativa',
            'detalle' => $detalle,
        ];
    }

    /** Suma de una columna; null si no hay ninguna fila (no es lo mismo que 0). */
    protected static function suma(Collection $filas, string $campo): ?float
    {
        return $filas->isEmpty() ? null : (float) $filas->sum($campo);
    }

    /** Fecha (Y-m-d) de la última actualización de las filas fuente; null si no hay. */
    protected static function fechaDe(Collection $filas): ?string
    {
        $max = $filas->max('updated_at');

        return $max ? Carbon::parse($max)->toDateString() : null;
    }

    public static function numeroTexto(float $valor): string
    {
        return floor($valor) == $valor && abs($valor) < 1e15
            ? (string) (int) $valor
            : rtrim(rtrim(number_format($valor, 4, '.', ''), '0'), '.');
    }

    /**
     * Serie mensual lista para la sección: solo entran los meses con algún dato y
     * dentro de cada fila solo las series que lo tienen.
     *
     * @param  array<int, array{clave: string, titulo: string, eje: string}>  $series
     * @param  callable(Carbon): array<string, float|null>  $valoresDelMes
     * @return array<string, mixed>
     */
    protected function serieMensual(array $series, callable $valoresDelMes): array
    {
        $filas = [];
        foreach ($this->mesesDeSerie() as $mes) {
            $valores = array_filter($valoresDelMes($mes), fn ($v) => $v !== null);
            if ($valores === []) {
                continue;
            }
            $filas[] = ['x' => Periodo::corta($mes), 'valores' => array_map(fn ($v) => (float) $v, $valores)];
        }

        return ['etiqueta_x' => 'Mes', 'series' => $series, 'filas' => $filas, 'lectura' => ''];
    }

    /**
     * Resumen para el cliente: logros y retos sugeridos con cifras reales (editables)
     * y la serie mensual como gráfica clave. Los próximos pasos los redacta el equipo.
     *
     * @param  array<string, mixed>  $serie
     * @return array<string, mixed>
     */
    protected function resumen(array $serie): array
    {
        $sugerido = SugerenciasResumen::generar($this->hechos + [
            'mes_previo_label' => Periodo::etiqueta($this->mes->copy()->subMonthNoOverflow()),
        ]);
        $series = array_slice($serie['series'] ?? [], 0, 2);
        $claves = array_flip(array_column($series, 'clave'));

        return [
            'logros' => $sugerido['logros'],
            'retos' => $sugerido['retos'],
            'proximos_pasos' => [],
            'grafica' => [
                'titulo' => $this->tituloGrafica(),
                'etiqueta_x' => 'Mes',
                'series' => array_map(fn ($s) => ['clave' => $s['clave'], 'titulo' => $s['titulo']], $series),
                'filas' => array_map(fn ($f) => ['x' => $f['x'], 'valores' => array_intersect_key($f['valores'], $claves)], $serie['filas'] ?? []),
            ],
        ];
    }
}
