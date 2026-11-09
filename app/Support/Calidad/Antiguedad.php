<?php

namespace App\Support\Calidad;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Throwable;

/**
 * Decide si una cifra del panel está al día, según la fecha del DATO.
 *
 * Pura y sin base de datos para poder probarla en las fronteras (30/31 y 90/91 días).
 * El umbral cuenta desde la fecha del dato (periodo, ronda, auditoría), nunca desde la
 * última edición del registro: un `updated_at` cambia con cualquier retoque y daría un
 * dato viejo por "fresco".
 */
class Antiguedad
{
    public const RECIENTE = 'reciente';

    public const ANTIGUO = 'antiguo';

    public const SIN_DATO = 'sin_dato';

    /** Tipos de dato: cada uno con su propio ritmo de actualización. */
    public const TIPO_GENERAL = 'general';

    public const TIPO_AUDITORIA = 'auditoria';

    public const TIPO_CALCULADO = 'calculado';

    /** Métricas, Ads, keywords, Search Console y tráfico se capturan al menos cada mes. */
    public const UMBRAL_GENERAL = 30;

    /** La auditoría SEO es trimestral: antes de 90 días no hay nada nuevo que capturar. */
    public const UMBRAL_AUDITORIA = 90;

    /** null = sin umbral: la cifra sale de `now()` (MRR, pagos...) y no envejece. */
    public const UMBRALES = [
        self::TIPO_GENERAL => self::UMBRAL_GENERAL,
        self::TIPO_AUDITORIA => self::UMBRAL_AUDITORIA,
        self::TIPO_CALCULADO => null,
    ];

    /**
     * @param  int|null  $dias  Umbral explícito; si es null manda el del $tipo.
     * @return array{estado: string, dias: int|null, umbral: int|null}
     *                                                                    "Antiguo" es MÁS de $umbral días (30 exactos todavía es reciente).
     */
    public static function evaluar(?CarbonInterface $fecha, ?int $dias = null, ?string $tipo = null): array
    {
        $tipo = $tipo !== null && array_key_exists($tipo, self::UMBRALES) ? $tipo : self::TIPO_GENERAL;
        $umbral = $dias ?? self::UMBRALES[$tipo];

        // Calculado al momento: la fecha es "hoy" por definición, aunque no haya una guardada.
        if ($tipo === self::TIPO_CALCULADO && $dias === null) {
            return ['estado' => self::RECIENTE, 'dias' => 0, 'umbral' => null];
        }

        if ($fecha === null) {
            return ['estado' => self::SIN_DATO, 'dias' => null, 'umbral' => $umbral];
        }

        $transcurridos = self::diasDesde($fecha);

        return [
            'estado' => $umbral !== null && $transcurridos > $umbral ? self::ANTIGUO : self::RECIENTE,
            'dias' => $transcurridos,
            'umbral' => $umbral,
        ];
    }

    /** Días enteros desde la fecha (por día calendario); una fecha futura cuenta como 0. */
    public static function diasDesde(CarbonInterface $fecha): int
    {
        // Ambas en UTC y a medianoche: así el cambio de horario no mueve el resultado.
        $a = Carbon::parse($fecha->format('Y-m-d'), 'UTC')->getTimestamp();
        $b = Carbon::parse(Carbon::now()->format('Y-m-d'), 'UTC')->getTimestamp();

        return max(0, intdiv($b - $a, 86400));
    }

    /** Convierte lo que traiga una vista (Carbon, DateTime, texto ISO) a Carbon; lo ilegible es null. */
    public static function aCarbon(mixed $valor): ?CarbonInterface
    {
        try {
            if ($valor instanceof CarbonInterface) {
                return $valor;
            }
            if ($valor instanceof DateTimeInterface) {
                return Carbon::instance($valor);
            }
            if (is_string($valor) && trim($valor) !== '') {
                return Carbon::parse($valor);
            }
        } catch (Throwable) {
            // Una fecha mala nunca debe romper la pantalla: se trata como "sin fecha".
        }

        return null;
    }

    /** Último día del periodo mensual (mes/año): la edad de un dato mensual se cuenta desde que el mes cerró. */
    public static function finDeMes(?int $mes, ?int $anio): ?CarbonInterface
    {
        if (! $mes || ! $anio || $mes < 1 || $mes > 12) {
            return null;
        }

        return Carbon::create($anio, $mes, 1)->endOfMonth();
    }
}
