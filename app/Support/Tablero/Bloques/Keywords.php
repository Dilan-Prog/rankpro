<?php

namespace App\Support\Tablero\Bloques;

use App\Models\KeywordMedicion;
use App\Models\User;
use App\Support\Tablero\Contexto;
use Illuminate\Support\Carbon;

/**
 * Posicionamiento de keywords. El cálculo de top 3/10/20, posición media y variación es de
 * TableroCliente::analizar() (una sola fórmula para este bloque y para el Tablero de Reportes).
 */
class Keywords extends Bloque
{
    public function clave(): string
    {
        return 'keywords';
    }

    public function titulo(): string
    {
        return 'Keywords';
    }

    public function icono(): string
    {
        return 'fa-key';
    }

    public function permisos(): array
    {
        return ['keywords'];
    }

    public function calcular(Contexto $ctx): array
    {
        $datos = $ctx->tablero();
        $kw = $datos['keywords'];
        $enlace = ['url' => route('admin.keywords.cliente', $ctx->cliente), 'etiqueta' => 'Ver keywords del cliente'];

        if ($kw['seguimiento'] === 0) {
            return $this->bloque(self::NEUTRO, 'Este cliente no tiene keywords en seguimiento.', [], $enlace);
        }

        $media = $datos['posicion_media'];
        if ($media['fecha_actual'] === null) {
            return $this->bloque(self::NEUTRO, $kw['seguimiento'].' keywords en seguimiento, ninguna con medición de posición todavía.', [
                self::cifra('Keywords en seguimiento', (string) $kw['seguimiento'], self::fecha($ctx->hoy), 'Keywords'),
            ], $enlace);
        }

        $ronda = Carbon::parse($media['fecha_actual']);
        $fecha = self::fecha($ronda);
        $quien = $this->quienMidio($ctx, $media['fecha_actual']);
        $fuente = 'Keywords · ronda de mediciones del '.$fecha;
        $extra = ['registrado_por' => $quien];

        $top = fn (string $clave, string $etiqueta) => self::cifra(
            $etiqueta,
            $datos['top'][$clave]['cantidad'].' de '.$kw['medidas'].($datos['top'][$clave]['porcentaje'] !== null ? ' ('.$datos['top'][$clave]['porcentaje'].' %)' : ''),
            $fecha,
            $fuente,
            $extra,
        );

        $variacion = $media['mejora'];
        $cifras = [
            self::cifra('Keywords en seguimiento', $kw['seguimiento'].($kw['sin_medir'] > 0 ? ' ('.$kw['sin_medir'].' sin medir)' : ''), $fecha, $fuente, $extra),
            $top('top3', 'En el top 3'),
            $top('top10', 'En el top 10'),
            $top('top20', 'En el top 20'),
            self::cifra('Posición media', $media['actual'] === null ? null : '#'.$media['actual'], $fecha, $fuente, $extra),
            self::cifra(
                'Variación de la posición media',
                match (true) {
                    $variacion === null => null,
                    $variacion > 0 => 'Mejoró '.$variacion.' posiciones',
                    $variacion < 0 => 'Empeoró '.abs($variacion).' posiciones',
                    default => 'Sin cambio',
                },
                $fecha,
                $fuente,
                $extra + ['nota' => $variacion === null ? 'Hace falta una ronda anterior con posición en las mismas keywords.' : 'Contra la ronda del '.self::fecha(Carbon::parse($media['fecha_anterior'])).'.'],
            ),
        ];

        if (self::desactualizado($ronda, $ctx->hoy)) {
            $estado = self::AMARILLO;
            $motivo = 'La última ronda de mediciones es del '.$fecha.' (más de '.self::DIAS_SIN_ACTUALIZAR.' días).';
        } else {
            $estado = self::VERDE;
            $motivo = 'Última ronda de mediciones: '.$fecha.'.';
        }

        return $this->bloque($estado, $motivo, $cifras, $enlace);
    }

    /** Persona que registró la ronda más reciente; null si fue una importación (Search Console) o varias personas. */
    private function quienMidio(Contexto $ctx, string $fecha): ?string
    {
        $ids = KeywordMedicion::query()
            ->join('keywords', 'keywords.id', '=', 'keyword_mediciones.keyword_id')
            ->where('keywords.cliente_id', $ctx->cliente->id)
            ->whereDate('keyword_mediciones.fecha', $fecha)
            ->whereNotNull('keyword_mediciones.registrado_por')
            ->distinct()
            ->pluck('keyword_mediciones.registrado_por');

        if ($ids->count() !== 1) {
            return $ids->count() > 1 ? 'Varias personas' : null;
        }

        return User::query()->whereKey($ids->first())->value('name');
    }
}
