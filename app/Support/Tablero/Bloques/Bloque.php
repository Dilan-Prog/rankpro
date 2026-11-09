<?php

namespace App\Support\Tablero\Bloques;

use App\Support\Tablero\Contexto;
use Carbon\CarbonInterface;

/**
 * Contrato común de los bloques del Resumen de cliente: cada uno devuelve
 * `{estado_semaforo, cifras:[{etiqueta, valor, fecha, fuente}], enlace}` más un motivo legible.
 *
 * Reglas del semáforo SIN meta (no se inventan umbrales): verde = hay dato y está al día; amarillo = dato sin
 * actualizar hace más de 30 días o captura/seguimiento incompleto; rojo = problema duro (contrato vencido,
 * pagos vencidos); NEUTRO (gris) = sin dato o sin integración, NUNCA verde por no tener datos. Solo las
 * métricas con meta (MetricaCliente) se califican con `Semaforo::evaluar`.
 */
abstract class Bloque
{
    public const VERDE = 'verde';
    public const AMARILLO = 'amarillo';
    public const ROJO = 'rojo';
    public const NEUTRO = 'neutro';

    /** Un dato capturado a mano que lleva más días que esto sin tocarse se marca para actualizar. */
    public const DIAS_SIN_ACTUALIZAR = 30;

    private const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    abstract public function clave(): string;

    abstract public function titulo(): string;

    abstract public function icono(): string;

    /** @return list<string> Permisos de módulo; con uno basta para ver el bloque. */
    abstract public function permisos(): array;

    /** @return array<string, mixed> */
    abstract public function calcular(Contexto $ctx): array;

    /**
     * @param  list<array<string, mixed>>  $cifras
     * @param  array{url: string, etiqueta: string}|null  $enlace
     * @param  array<string, mixed>  $extra  Claves opcionales (`leyenda`, `requiere_cliente_activo`).
     * @return array<string, mixed>
     */
    protected function bloque(string $estado, string $motivo, array $cifras, ?array $enlace, array $extra = []): array
    {
        return array_merge([
            'clave' => $this->clave(),
            'titulo' => $this->titulo(),
            'icono' => $this->icono(),
            'estado_semaforo' => $estado,
            'leyenda' => self::leyenda($estado),
            'motivo' => $motivo,
            'sin_dato' => $estado === self::NEUTRO,
            'cifras' => $cifras,
            'enlace' => $enlace,
            'requiere_cliente_activo' => false,
        ], $extra);
    }

    /**
     * Una cifra siempre lleva su fecha y su fuente. `valor` null = sin dato (la vista pinta un guion).
     *
     * @param  array<string, mixed>  $extra  `capturado` (d/m/Y en que se capturó), `registrado_por`, `parcial`, `semaforo`, `nota`.
     * @return array<string, mixed>
     */
    public static function cifra(string $etiqueta, ?string $valor, ?string $fecha, string $fuente, array $extra = []): array
    {
        return array_merge([
            'etiqueta' => $etiqueta,
            'valor' => $valor,
            'fecha' => $fecha,
            'fuente' => $fuente,
            'capturado' => null,
            'registrado_por' => null,
            'parcial' => false,
            'semaforo' => null,
            'nota' => null,
        ], $extra);
    }

    public static function leyenda(string $estado): string
    {
        return match ($estado) {
            self::VERDE => 'Al día',
            self::AMARILLO => 'Por revisar',
            self::ROJO => 'Requiere atención',
            default => 'Sin dato',
        };
    }

    /** El peor de varios estados; NEUTRO solo si no hay ninguno con color. */
    public static function peor(array $estados): string
    {
        foreach ([self::ROJO, self::AMARILLO, self::VERDE] as $e) {
            if (in_array($e, $estados, true)) {
                return $e;
            }
        }

        return self::NEUTRO;
    }

    public static function fecha(?CarbonInterface $fecha): ?string
    {
        return $fecha?->format('d/m/Y');
    }

    /** «octubre 2026». */
    public static function periodo(int $mes, int $anio): string
    {
        return (self::MESES[$mes - 1] ?? (string) $mes).' '.$anio;
    }

    /** Más de 30 días sin actualizarse; sin fecha no se puede afirmar nada (false). */
    public static function desactualizado(?CarbonInterface $fecha, CarbonInterface $hoy): bool
    {
        if ($fecha === null) {
            return false;
        }

        return $fecha->copy()->startOfDay()->addDays(self::DIAS_SIN_ACTUALIZAR)->lt($hoy->copy()->startOfDay());
    }

    public static function dinero(float $monto): string
    {
        return '$'.number_format($monto, 2);
    }

    /** Entero con separador de miles; null se queda null (sin dato). */
    public static function entero(int|float|null $n): ?string
    {
        return $n === null ? null : number_format((float) $n, 0);
    }

    /** Fecha más reciente de una lista (ignora nulos). */
    public static function masReciente(iterable $fechas): ?CarbonInterface
    {
        $max = null;
        foreach ($fechas as $f) {
            if ($f !== null && ($max === null || $f->gt($max))) {
                $max = $f;
            }
        }

        return $max;
    }
}
