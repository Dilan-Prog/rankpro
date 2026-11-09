<?php

namespace App\Support\Tablero\Bloques;

use App\Models\BuyerPersona;
use App\Models\IdentidadMarca;
use App\Support\Tablero\Contexto;
use Illuminate\Support\Carbon;

/**
 * Audiencia e identidad de marca. Son documentos de trabajo, no métricas: no «caducan» a los 30 días, así que
 * aquí el semáforo mide presencia (qué piezas existen), nunca antigüedad.
 */
class Audiencias extends Bloque
{
    public function clave(): string
    {
        return 'audiencias';
    }

    public function titulo(): string
    {
        return 'Audiencia e identidad';
    }

    public function icono(): string
    {
        return 'fa-users-viewfinder';
    }

    public function permisos(): array
    {
        return ['audiencia', 'identidad'];
    }

    public function calcular(Contexto $ctx): array
    {
        $cifras = [];
        $presentes = 0;
        $consideradas = 0;
        $faltan = [];
        $enlace = null;

        if ($ctx->puede('audiencia')) {
            $consideradas++;
            $fila = BuyerPersona::query()->where('cliente_id', $ctx->cliente->id)->selectRaw('count(*) as total, max(updated_at) as ultima')->first();
            $total = (int) ($fila->total ?? 0);
            $ultima = $total > 0 && $fila->ultima ? Carbon::parse($fila->ultima) : null;
            $total > 0 ? $presentes++ : $faltan[] = 'buyer personas';

            $cifras[] = self::cifra('Buyer personas', $total > 0 ? (string) $total : 'Ninguna', self::fecha($ultima), 'Audiencia · captura manual', ['nota' => $ultima ? 'La fecha es la de la última edición.' : null]);
            $enlace = ['url' => route('admin.audiencia.index'), 'etiqueta' => 'Abrir Audiencia'];
        }

        if ($ctx->puede('identidad')) {
            $consideradas++;
            $identidad = IdentidadMarca::query()->where('cliente_id', $ctx->cliente->id)->first();
            $definida = $identidad !== null && $this->tieneContenido($identidad);
            $definida ? $presentes++ : $faltan[] = 'identidad de marca';

            $cifras[] = self::cifra('Identidad de marca', $definida ? 'Definida' : 'No definida', $definida ? self::fecha($identidad->updated_at) : null, 'Identidad de marca · captura manual', ['nota' => $definida ? 'La fecha es la de la última edición.' : null]);
            $enlace ??= ['url' => route('admin.identidad.index'), 'etiqueta' => 'Abrir Identidad'];
        }

        if ($presentes === 0) {
            $estado = self::NEUTRO;
            $motivo = 'Sin '.implode(' ni ', $faltan).' capturadas para este cliente.';
        } elseif ($presentes < $consideradas) {
            $estado = self::AMARILLO;
            $motivo = 'Falta capturar: '.implode(', ', $faltan).'.';
        } else {
            $estado = self::VERDE;
            $motivo = 'Audiencia e identidad capturadas.';
        }

        // Estos módulos trabajan con el cliente ACTIVO del menú, no con el de la URL: la vista lo avisa.
        return $this->bloque($estado, $motivo, $cifras, $enlace, ['requiere_cliente_activo' => true]);
    }

    /** Una fila vacía (creada al abrir la pantalla sin guardar nada) no cuenta como identidad definida. */
    private function tieneContenido(IdentidadMarca $i): bool
    {
        foreach (['logo_ruta', 'slogan', 'voz', 'mision', 'vision', 'valores'] as $campo) {
            if (filled($i->{$campo})) {
                return true;
            }
        }

        return filled($i->paleta) || filled($i->tonos);
    }
}
