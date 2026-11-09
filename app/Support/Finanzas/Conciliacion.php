<?php

namespace App\Support\Finanzas;

use App\Models\Cliente;
use App\Models\Finanza;
use App\Models\Servicio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Conciliación SOLO LECTURA: esperado (servicios de cobro mensual) contra facturado y cobrado (tabla finanzas).
 * No genera cobros, no corrige ni borra filas: la agencia captura a mano y aquí solo se hace visible la brecha
 * y lo que huele a error de captura (decisión del usuario; el MRR sigue saliendo de FinanzasMetrics::mrr()).
 */
class Conciliacion
{
    private const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    /** Diferencias menores a medio centavo son ruido de redondeo, no brecha. */
    private const TOLERANCIA = 0.005;

    public function __construct(private readonly Carbon $ahora, private readonly ?int $clienteId = null)
    {
    }

    /**
     * Seis meses calendario (el último es el mes en curso), del más viejo al más nuevo, como revenueData().
     *
     * @return list<array<string, mixed>>
     */
    public function meses(): array
    {
        // startOfMonth() antes de restar evita el desbordamiento de fin de mes (ver FinanzasMetrics::revenueData()).
        $periodos = collect(range(5, 0))->map(fn (int $i) => $this->ahora->copy()->startOfMonth()->subMonths($i));

        // Solo se conoce el estado ACTUAL de cada servicio (no hay historial): un servicio hoy pausado o
        // cancelado no aparece como esperado en meses pasados. Es la misma regla del MRR, no una nueva.
        $servicios = Servicio::mensual()
            ->when($this->clienteId, fn ($q) => $q->where('cliente_id', $this->clienteId))
            ->get();

        $ingresos = Finanza::where('tipo', 'ingreso')
            ->when($this->clienteId, fn ($q) => $q->where('cliente_id', $this->clienteId))
            ->whereIn('anio', $periodos->pluck('year')->unique()->all())
            ->get()
            ->groupBy(fn (Finanza $f) => $f->anio . '-' . $f->mes);

        $nombres = $this->nombresDeClientes(
            $servicios->pluck('cliente_id')->merge($ingresos->flatten()->pluck('cliente_id'))->unique()->all()
        );

        return $periodos->map(fn (Carbon $p) => $this->mes($p, $servicios, $ingresos->get($p->year . '-' . $p->month, collect()), $nombres))
            ->values()
            ->all();
    }

    /**
     * Servicio que cuenta como esperado en el mes. Mes en curso: regla del MRR (activo aunque la fecha_fin
     * ya haya pasado). Meses cerrados: se respeta fecha_inicio y fecha_fin. $respetarFechaFin = false
     * reproduce la regla del MRR en cualquier mes.
     */
    private function esperadoEnMes(Servicio $s, Carbon $inicioMes, bool $respetarFechaFin): bool
    {
        $finMes = $inicioMes->copy()->endOfMonth();

        if ($s->fecha_inicio !== null && $s->fecha_inicio->gt($finMes)) {
            return false;
        }

        return ! $respetarFechaFin || $s->fecha_fin === null || $s->fecha_fin->gte($inicioMes);
    }

    private function mes(Carbon $periodo, Collection $servicios, Collection $ingresos, Collection $nombres): array
    {
        $esActual = $periodo->isSameMonth($this->ahora);
        $inicioMes = $periodo->copy()->startOfMonth();

        $esperados = $servicios->filter(fn (Servicio $s) => $this->esperadoEnMes($s, $inicioMes, ! $esActual))->values();
        $porFechaFin = $servicios->filter(fn (Servicio $s) => $this->esperadoEnMes($s, $inicioMes, true));

        $esperado = round((float) $esperados->sum('precio_mensual'), 2);
        $facturado = round((float) $ingresos->sum('monto'), 2);
        $cobrado = round((float) $ingresos->filter(fn (Finanza $f) => $f->estado->value === 'pagado')->sum('monto'), 2);
        $porCobrar = round($facturado - $cobrado, 2);
        $faltaFacturar = max(0.0, round($esperado - $facturado, 2));

        $clienteIds = $esperados->pluck('cliente_id')->merge($ingresos->pluck('cliente_id'))->unique();
        $clientes = $clienteIds->map(fn ($id) => $this->cliente(
            (int) $id,
            $nombres->get($id, '—'),
            $esperados->where('cliente_id', $id),
            $ingresos->where('cliente_id', $id),
            $inicioMes,
        ))->sortByDesc('esperado')->values()->all();

        return [
            'periodo' => sprintf('%04d-%02d', $periodo->year, $periodo->month),
            'etiqueta' => self::MESES[$periodo->month - 1] . ' ' . $periodo->year,
            'es_actual' => $esActual,
            'esperado' => $esperado,
            'facturado' => $facturado,
            'cobrado' => $cobrado,
            'por_cobrar' => $porCobrar,
            'falta_facturar' => $faltaFacturar,
            'falta_cobrar' => max(0.0, $porCobrar),
            // Facturado por encima de lo esperado: proyectos de pago único u otros cargos, no es error.
            'sobre_esperado' => max(0.0, round($facturado - $esperado, 2)),
            // Solo distinto de 0 en el mes en curso: servicios con fecha_fin ya vencida que el MRR sigue contando.
            'esperado_por_fecha_fin' => round((float) $porFechaFin->sum('precio_mensual'), 2),
            'incluye_vencidos' => max(0.0, round($esperado - (float) $porFechaFin->sum('precio_mensual'), 2)),
            'estado' => $this->estado($esperado, $facturado, $faltaFacturar, $porCobrar),
            'clientes' => $clientes,
        ];
    }

    /** @return array{clave: string, etiquetas: list<array{clave: string, texto: string, tono: string}>} */
    private function estado(float $esperado, float $facturado, float $faltaFacturar, float $porCobrar): array
    {
        $etiquetas = [];
        if ($faltaFacturar > self::TOLERANCIA) {
            $etiquetas[] = ['clave' => 'falta_facturar', 'texto' => 'Falta facturar', 'tono' => 'danger'];
        }
        if ($porCobrar > self::TOLERANCIA) {
            $etiquetas[] = ['clave' => 'falta_cobrar', 'texto' => 'Falta cobrar', 'tono' => 'warning'];
        }
        if ($etiquetas === []) {
            $etiquetas[] = $esperado <= self::TOLERANCIA && $facturado <= self::TOLERANCIA
                ? ['clave' => 'sin_movimiento', 'texto' => 'Sin movimiento', 'tono' => 'neutral']
                : ['clave' => 'cuadra', 'texto' => 'Cuadra', 'tono' => 'success'];
        }

        return ['clave' => $etiquetas[0]['clave'], 'etiquetas' => $etiquetas];
    }

    private function cliente(int $id, string $nombre, Collection $esperados, Collection $ingresos, Carbon $inicioMes): array
    {
        $porServicio = $ingresos->whereNotNull('servicio_id')->groupBy('servicio_id');

        $servicios = $esperados->map(function (Servicio $s) use ($porServicio, $inicioMes) {
            $filas = $porServicio->get($s->id, collect());
            $estado = $filas->isEmpty() ? 'sin_fila'
                : ($filas->every(fn (Finanza $f) => $f->estado->value === 'pagado') ? 'pagado' : 'pendiente');

            return [
                'servicio_id' => $s->id,
                'nombre' => $s->nombre,
                'precio' => round((float) $s->precio_mensual, 2),
                'facturado' => round((float) $filas->sum('monto'), 2),
                'estado' => $estado,
                'fecha_fin_vencida' => $s->fecha_fin !== null && $s->fecha_fin->lt($inicioMes),
            ];
        })->values()->all();

        $esperadoIds = $esperados->pluck('id');
        $sinServicio = $ingresos->whereNull('servicio_id');
        $otros = $ingresos->whereNotNull('servicio_id')->reject(fn (Finanza $f) => $esperadoIds->contains($f->servicio_id));

        return [
            'cliente_id' => $id,
            'cliente' => $nombre,
            'esperado' => round((float) $esperados->sum('precio_mensual'), 2),
            'facturado' => round((float) $ingresos->sum('monto'), 2),
            'cobrado' => round((float) $ingresos->filter(fn (Finanza $f) => $f->estado->value === 'pagado')->sum('monto'), 2),
            'por_cobrar' => round((float) $ingresos->reject(fn (Finanza $f) => $f->estado->value === 'pagado')->sum('monto'), 2),
            'servicios' => $servicios,
            // Filas que no se pueden cruzar con un servicio mensual: se muestran aparte para no inflar ni esconder la brecha.
            'sin_servicio' => ['filas' => $sinServicio->count(), 'monto' => round((float) $sinServicio->sum('monto'), 2)],
            'otros_servicios' => ['filas' => $otros->count(), 'monto' => round((float) $otros->sum('monto'), 2)],
        ];
    }

    /** @return Collection<int, string> cliente_id => nombre comercial */
    private function nombresDeClientes(array $ids): Collection
    {
        return Cliente::whereIn('id', $ids)->get()->mapWithKeys(fn (Cliente $c) => [$c->id => $c->nombre_comercial]);
    }

    /**
     * Posibles duplicados y datos a revisar. Nada se corrige: solo el usuario sabe cuál fila sobra.
     *
     * @return array{duplicados: list<array<string, mixed>>, inconsistencias: list<array<string, mixed>>, total: int}
     */
    public function revision(): array
    {
        $filas = Finanza::with('cliente')
            ->when($this->clienteId, fn ($q) => $q->where('cliente_id', $this->clienteId))
            ->orderBy('id')
            ->get();

        $ingresos = $filas->where('tipo', 'ingreso');
        $duplicados = $this->duplicados($ingresos);

        $inconsistencias = [
            $this->grupo(
                'pendiente_con_fecha_pago',
                'Pendientes o vencidos con fecha de pago',
                'Dicen que no se han cobrado o pagado, pero tienen fecha de pago. Revisa cuál de los dos datos es el correcto.',
                $filas->filter(fn (Finanza $f) => $f->estado->value !== 'pagado' && $f->fecha_pago !== null),
            ),
            $this->grupo(
                'ingreso_sin_servicio',
                'Ingresos sin servicio vinculado',
                'No se pueden cruzar con un servicio mensual, así que la conciliación por servicio no los reconoce.',
                $ingresos->filter(fn (Finanza $f) => $f->servicio_id === null),
            ),
            $this->grupo(
                'ingreso_en_cero',
                'Ingresos en $0',
                'Una fila de ingreso sin monto cuenta como facturada pero no suma nada.',
                $ingresos->filter(fn (Finanza $f) => (float) $f->monto <= 0.0),
            ),
        ];
        $inconsistencias = array_values(array_filter($inconsistencias, fn (array $g) => $g['filas'] !== []));

        return [
            'duplicados' => $duplicados,
            'inconsistencias' => $inconsistencias,
            'total' => count($duplicados) + array_sum(array_map(fn (array $g) => count($g['filas']), $inconsistencias)),
        ];
    }

    /**
     * Grupos con mismo cliente + concepto + monto en el mismo mes, o creados en el mismo minuto (doble clic).
     * Un grupo "mismo minuto" ya cubierto por uno de "mismo mes" no se repite.
     *
     * @return list<array<string, mixed>>
     */
    private function duplicados(Collection $ingresos): array
    {
        $base = fn (Finanza $f) => $f->cliente_id . '|' . mb_strtolower(trim((string) $f->concepto)) . '|' . number_format((float) $f->monto, 2, '.', '');

        $porMes = $ingresos->groupBy(fn (Finanza $f) => $base($f) . '|' . $f->anio . '-' . $f->mes)->filter(fn ($g) => $g->count() > 1);
        $porMinuto = $ingresos->filter(fn (Finanza $f) => $f->created_at !== null)
            ->groupBy(fn (Finanza $f) => $base($f) . '|' . $f->created_at->format('Y-m-d H:i'))
            ->filter(fn ($g) => $g->count() > 1);

        $grupos = [];
        foreach ($porMes as $g) {
            $grupos[] = ['motivo' => 'Mismo cliente, concepto, monto y mes', 'items' => $g];
        }
        foreach ($porMinuto as $g) {
            $cubierto = $porMes->contains(fn ($m) => $g->pluck('id')->diff($m->pluck('id'))->isEmpty());
            if (! $cubierto) {
                $grupos[] = ['motivo' => 'Creadas en el mismo minuto con mismo cliente, concepto y monto', 'items' => $g];
            }
        }

        return array_map(fn (array $g) => [
            'motivo' => $g['motivo'],
            'filas' => $g['items']->map(fn (Finanza $f) => $this->fila($f))->values()->all(),
            // Si realmente fuera un doble registro, esto es lo que sobraría (una fila se conserva).
            'monto_sobrante' => round((float) $g['items']->skip(1)->sum('monto'), 2),
        ], $grupos);
    }

    private function grupo(string $clave, string $titulo, string $explicacion, Collection $filas): array
    {
        return [
            'clave' => $clave,
            'titulo' => $titulo,
            'explicacion' => $explicacion,
            'filas' => $filas->map(fn (Finanza $f) => $this->fila($f))->values()->all(),
        ];
    }

    private function fila(Finanza $f): array
    {
        return [
            'id' => $f->id,
            'folio' => 'F-' . str_pad((string) $f->id, 5, '0', STR_PAD_LEFT),
            'cliente' => $f->cliente?->nombre_comercial ?? '—',
            'concepto' => $f->concepto,
            'tipo' => $f->tipo,
            'monto' => round((float) $f->monto, 2),
            'estado' => $f->estado->value,
            'periodo' => self::MESES[$f->mes - 1] . ' ' . $f->anio,
            'fecha_pago' => $f->fecha_pago?->format('d/m/Y'),
            'creada' => $f->created_at?->format('d/m/Y H:i:s'),
            // El alta y la edición viven en un modal de Facturación; esta URL lo abre sobre la fila.
            'editar_url' => route('admin.finanzas.index', ['editar' => $f->id]),
        ];
    }
}
