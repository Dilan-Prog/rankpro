<?php

namespace App\Console\Commands;

use App\Models\CorreoEnvio;
use App\Services\Correo\EnviadorCorreo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Manda los envíos programados cuya hora ya venció. Lo dispara el scheduler
 * cada minuto (ver app/Console/Kernel.php); `withoutOverlapping` evita que dos
 * ejecuciones tomen el mismo envío si un SMTP lento estira una de ellas.
 */
class ProcesarCorreosProgramados extends Command
{
    protected $signature = 'correo:procesar-programados';

    protected $description = 'Envía los correos programados cuya fecha ya venció';

    public function handle(EnviadorCorreo $enviador): int
    {
        $procesados = 0;

        CorreoEnvio::vencidos()->orderBy('programado_para')->get()->each(function (CorreoEnvio $envio) use ($enviador, &$procesados) {
            try {
                $resultado = $enviador->enviar($envio);
                $this->line("Envío #{$envio->id} ({$envio->asunto}): {$resultado->estado->label()}");
                $procesados++;
            } catch (Throwable $e) {
                // Un envío roto no debe frenar a los demás de la misma tanda.
                Log::error('Correo: fallo al procesar envío programado', ['envio_id' => $envio->id, 'error' => $e->getMessage()]);
                $this->error("Envío #{$envio->id}: {$e->getMessage()}");
            }
        });

        $this->info("Procesados: {$procesados}");

        return self::SUCCESS;
    }
}
