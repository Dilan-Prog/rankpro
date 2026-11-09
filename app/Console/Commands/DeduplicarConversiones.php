<?php

namespace App\Console\Commands;

use App\Models\AdsConversion;
use App\Support\Conversiones\Deduplicador;
use Illuminate\Console\Command;

/**
 * Marca como repetidas las conversiones del doble clic. Por defecto SOLO simula: en producción
 * hay que ver la tabla antes de tocar nada. Es idempotente y nunca borra filas.
 */
class DeduplicarConversiones extends Command
{
    protected $signature = 'conversiones:deduplicar
        {--aplicar : Escribe las marcas (sin esta opción solo simula)}
        {--ventana=5 : Minutos de la ventana contados desde la primera conversión del grupo}
        {--cliente= : Limita a un cliente (id)}';

    protected $description = 'Detecta (simulación) o marca (--aplicar) conversiones duplicadas por ventana de tiempo';

    private const FILAS_MAXIMAS = 100;

    public function handle(): int
    {
        $ventana = $this->option('ventana');
        if (! ctype_digit((string) $ventana) || (int) $ventana < 1 || (int) $ventana > 1440) {
            $this->error('La ventana debe ser un número entero de minutos entre 1 y 1440.');

            return self::INVALID;
        }

        $cliente = $this->option('cliente');
        if ($cliente !== null && ! ctype_digit((string) $cliente)) {
            $this->error('--cliente debe ser el id numérico del cliente.');

            return self::INVALID;
        }
        $clienteId = $cliente !== null ? (int) $cliente : null;

        $deduplicador = new Deduplicador((int) $ventana);
        $detectadas = $deduplicador->detectar($clienteId);
        $aplicar = (bool) $this->option('aplicar');

        $this->line(($aplicar ? 'APLICAR' : 'SIMULACIÓN (no se escribe nada)').' — ventana de '.$ventana.' min'.($clienteId ? ", cliente {$clienteId}" : ''));

        if ($detectadas->isNotEmpty()) {
            $this->table(
                ['Conversión', 'Cliente', 'Tipo', 'Clave', 'Se marcaría como repetida de', 'Diferencia (s)', 'Creada'],
                $detectadas->take(self::FILAS_MAXIMAS)->map(fn ($d) => [
                    $d['id'], $d['cliente_id'], $d['tipo'], $this->abreviar($d['clave']), $d['original_id'], $d['segundos'], $d['creada'],
                ])->all()
            );

            if ($detectadas->count() > self::FILAS_MAXIMAS) {
                $this->line('… y '.($detectadas->count() - self::FILAS_MAXIMAS).' más.');
            }
        }

        $base = AdsConversion::query()->when($clienteId, fn ($q) => $q->where('cliente_id', $clienteId));
        $yaMarcadas = (clone $base)->whereNotNull('duplicada_de_id')->count();
        $total = (clone $base)->count();

        if (! $aplicar) {
            $this->info("Conversiones: {$total}. Ya marcadas: {$yaMarcadas}. Se marcarían ahora: {$detectadas->count()}.");
            $this->line('Para escribir las marcas: php artisan conversiones:deduplicar --aplicar --ventana='.$ventana);

            return self::SUCCESS;
        }

        $marcadas = $deduplicador->aplicar($detectadas);
        $this->info("Marcadas ahora: {$marcadas}. Total de duplicadas: ".((clone $base)->whereNotNull('duplicada_de_id')->count())." de {$total} conversiones.");

        return self::SUCCESS;
    }

    /** La clave puede ser un gclid larguísimo: la tabla debe seguir siendo legible. */
    private function abreviar(string $clave): string
    {
        return mb_strlen($clave) > 24 ? mb_substr($clave, 0, 21).'...' : $clave;
    }
}
