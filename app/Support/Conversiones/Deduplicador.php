<?php

namespace App\Support\Conversiones;

use App\Models\AdsConversion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Detecta y marca conversiones repetidas (el doble clic en un botón de WhatsApp genera dos
 * eventos con evento_id distinto, y el índice único solo protege reintentos de red).
 *
 * Regla: mismo cliente + tipo + visitor_id (o gclid si el visitor_id viene vacío) dentro de
 * una ventana de N minutos contada desde la PRIMERA del grupo, que se conserva. Las demás se
 * marcan (duplicada_de_id), nunca se borran. Anclar la ventana a la original evita que una
 * cadena de clics espaciados se encadene sin fin y se coma conversiones legítimas.
 */
class Deduplicador
{
    public const VENTANA_DEFECTO = 5;

    public function __construct(private readonly int $ventanaMinutos = self::VENTANA_DEFECTO)
    {
        if ($ventanaMinutos < 1) {
            throw new \InvalidArgumentException('La ventana debe ser de al menos 1 minuto.');
        }
    }

    /** Clave de agrupación: visitor_id y, sin él, gclid. Null = sin identidad, jamás se marca. */
    public static function claveGrupo(?string $visitorId, ?string $gclid): ?string
    {
        if (filled($visitorId)) {
            return 'v:'.$visitorId;
        }

        return filled($gclid) ? 'g:'.$gclid : null;
    }

    /**
     * Simula: devuelve lo que se marcaría, sin escribir nada. Las ya marcadas no cuentan
     * (por eso aplicar dos veces no cambia nada).
     *
     * @return Collection<int, array{id: int, original_id: int, cliente_id: int, tipo: string, clave: string, segundos: int, creada: string}>
     */
    public function detectar(?int $clienteId = null): Collection
    {
        $consulta = AdsConversion::query()
            ->whereNull('duplicada_de_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->select(['id', 'cliente_id', 'tipo', 'visitor_id', 'gclid', 'created_at']);

        if ($clienteId !== null) {
            $consulta->where('cliente_id', $clienteId);
        }

        $ventana = $this->ventanaMinutos * 60;
        $originales = []; // clave de grupo => [id, timestamp] de la original vigente
        $detectadas = collect();

        foreach ($consulta->cursor() as $fila) {
            $clave = self::claveGrupo($fila->visitor_id, $fila->gclid);
            if ($clave === null) {
                continue;
            }

            $tipo = $fila->tipo->value;
            $grupo = $fila->cliente_id.'|'.$tipo.'|'.$clave;
            $instante = $fila->created_at->getTimestamp();

            if (isset($originales[$grupo]) && $instante - $originales[$grupo][1] <= $ventana) {
                $detectadas->push([
                    'id' => $fila->id,
                    'original_id' => $originales[$grupo][0],
                    'cliente_id' => $fila->cliente_id,
                    'tipo' => $tipo,
                    'clave' => $clave,
                    'segundos' => $instante - $originales[$grupo][1],
                    'creada' => $fila->created_at->format('Y-m-d H:i:s'),
                ]);

                continue;
            }

            $originales[$grupo] = [$fila->id, $instante];
        }

        return $detectadas;
    }

    /**
     * Marca las detectadas en una transacción. El UPDATE exige duplicada_de_id NULL, así una
     * segunda corrida (o una carrera con otra) no pisa nada. Devuelve cuántas marcó de verdad.
     *
     * @param  Collection<int, array{id: int, original_id: int}>|null  $detectadas  null = detecta ahora
     */
    public function aplicar(?Collection $detectadas = null, ?int $clienteId = null): int
    {
        $detectadas ??= $this->detectar($clienteId);
        $ahora = now();

        return DB::transaction(function () use ($detectadas, $ahora) {
            $marcadas = 0;

            foreach ($detectadas as $d) {
                $marcadas += AdsConversion::query()
                    ->whereKey($d['id'])
                    ->whereNull('duplicada_de_id')
                    ->update(['duplicada_de_id' => $d['original_id'], 'duplicada_at' => $ahora]);
            }

            return $marcadas;
        });
    }

    /**
     * Para una conversión NUEVA (aún sin guardar): la original vigente del mismo grupo cuya
     * ventana todavía cubre este instante, o null si es una conversión legítima distinta.
     */
    public function buscarOriginal(int $clienteId, string $tipo, ?string $visitorId, ?string $gclid, ?\DateTimeInterface $instante = null): ?AdsConversion
    {
        $clave = self::claveGrupo($visitorId, $gclid);
        if ($clave === null) {
            return null;
        }

        $instante = \Illuminate\Support\Carbon::instance($instante ?? now());

        return AdsConversion::query()
            ->where('cliente_id', $clienteId)
            ->where('tipo', $tipo)
            ->whereNull('duplicada_de_id')
            ->when(
                filled($visitorId),
                fn ($q) => $q->where('visitor_id', $visitorId),
                fn ($q) => $q->where('gclid', $gclid)->where(fn ($v) => $v->whereNull('visitor_id')->orWhere('visitor_id', '')),
            )
            ->where('created_at', '>=', $instante->copy()->subMinutes($this->ventanaMinutos))
            ->where('created_at', '<=', $instante)
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
    }
}
