<?php

namespace App\Support\Tablero\Bloques;

use App\Models\AdsClic;
use App\Models\AdsConversion;
use App\Support\Tablero\Contexto;
use Illuminate\Support\Carbon;

/**
 * Seguimiento de clics y conversiones del snippet del cliente. Aquí las fechas son las de los eventos reales
 * (created_at), no las de una captura: «al día» significa que el snippet sigue enviando eventos.
 */
class Conversiones extends Bloque
{
    /** Ventana de «recientes»; la misma de la regla de dato sin actualizar. */
    private const DIAS_RECIENTES = 30;

    public function clave(): string
    {
        return 'conversiones';
    }

    public function titulo(): string
    {
        return 'Conversiones';
    }

    public function icono(): string
    {
        return 'fa-bullseye';
    }

    public function permisos(): array
    {
        return ['conversiones'];
    }

    public function calcular(Contexto $ctx): array
    {
        $clienteId = $ctx->cliente->id;
        $desde = $ctx->hoy->copy()->startOfDay()->subDays(self::DIAS_RECIENTES);
        $enlace = ['url' => route('admin.clientes.conversiones', $clienteId), 'etiqueta' => 'Ver conversiones del cliente'];

        $clics = AdsClic::query()
            ->where('cliente_id', $clienteId)
            ->selectRaw('count(*) as total, max(created_at) as ultima, coalesce(sum(created_at >= ?), 0) as recientes', [$desde])
            ->first();

        $porTipo = AdsConversion::query()
            ->where('cliente_id', $clienteId)
            ->groupBy('tipo', 'estado')
            ->selectRaw('tipo, estado, count(*) as total, coalesce(sum(valor), 0) as valor, max(created_at) as ultima, coalesce(sum(created_at >= ?), 0) as recientes', [$desde])
            ->get();

        $totalClics = (int) ($clics->total ?? 0);
        $totalConv = (int) $porTipo->sum('total');

        if ($totalClics === 0 && $totalConv === 0) {
            return $this->bloque(self::NEUTRO, 'Todavía no llega ningún clic ni conversión del snippet de seguimiento.', [], $enlace);
        }

        $ultimoClic = ! empty($clics->ultima) ? Carbon::parse($clics->ultima) : null;
        $ultimaConv = ! $porTipo->isEmpty() ? self::masReciente($porTipo->map(fn ($f) => Carbon::parse($f->ultima))) : null;
        $ultimoEvento = self::masReciente([$ultimoClic, $ultimaConv]);

        $fuente = 'Seguimiento del cliente (snippet de RankPro)';
        $tipos = $porTipo->groupBy(fn ($f) => $f->tipo instanceof \BackedEnum ? $f->tipo->value : (string) $f->tipo)
            ->map(fn ($grupo, $tipo) => $tipo.' '.$grupo->sum('total'))
            ->values()->implode(' · ');
        $pendientes = (int) $porTipo->filter(fn ($f) => ($f->estado instanceof \BackedEnum ? $f->estado->value : (string) $f->estado) === 'pendiente')->sum('total');

        $cifras = [
            self::cifra('Clics registrados', self::entero($totalClics), self::fecha($ultimoClic), $fuente, ['nota' => 'La fecha es la del último clic. En los últimos '.self::DIAS_RECIENTES.' días: '.(int) ($clics->recientes ?? 0).'.']),
            self::cifra('Conversiones registradas', self::entero($totalConv), self::fecha($ultimaConv), $fuente, ['nota' => $totalConv > 0 ? 'La fecha es la de la última conversión. En los últimos '.self::DIAS_RECIENTES.' días: '.(int) $porTipo->sum('recientes').'.' : null]),
            self::cifra('Conversiones por tipo', $tipos === '' ? null : $tipos, self::fecha($ultimaConv), $fuente),
            self::cifra('Valor de las conversiones', $totalConv > 0 ? self::dinero((float) $porTipo->sum('valor')) : null, self::fecha($ultimaConv), $fuente, ['nota' => 'Suma del valor que reportó el snippet; puede venir vacío.']),
            self::cifra('Pendientes de exportar', self::entero($pendientes), self::fecha($ultimaConv), $fuente),
        ];

        if (self::desactualizado($ultimoEvento, $ctx->hoy)) {
            $estado = self::AMARILLO;
            $motivo = 'El último evento llegó el '.self::fecha($ultimoEvento).' (más de '.self::DIAS_SIN_ACTUALIZAR.' días): revisa que el snippet siga instalado.';
        } else {
            $estado = self::VERDE;
            $motivo = 'El seguimiento está enviando eventos (último: '.self::fecha($ultimoEvento).').';
        }

        return $this->bloque($estado, $motivo, $cifras, $enlace);
    }
}
