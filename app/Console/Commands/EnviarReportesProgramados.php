<?php

namespace App\Console\Commands;

use App\Models\ReporteProgramacion;
use App\Services\Reportes\EnviadorReporteProgramado;
use Illuminate\Console\Command;

/**
 * Manda los reportes mensuales cuya programación ya venció. Lo dispara el
 * scheduler (ver app/Console/Kernel.php); `withoutOverlapping` evita que dos
 * corridas tomen la misma programación si un SMTP lento estira una de ellas.
 */
class EnviarReportesProgramados extends Command
{
    protected $signature = 'reportes:enviar-programados';

    protected $description = 'Envía por correo los reportes mensuales programados cuya fecha ya venció';

    public function handle(EnviadorReporteProgramado $enviador): int
    {
        $procesadas = 0;

        // ejecutar() nunca lanza y guarda el resultado en la propia fila:
        // un cliente que falla no frena a los siguientes.
        ReporteProgramacion::vencidas()->with(['cliente', 'plantilla'])->orderBy('proximo_envio_en')->get()
            ->each(function (ReporteProgramacion $prog) use ($enviador, &$procesadas) {
                // false = otro proceso la tiene o ya la reclamó: no es un fallo ni cuenta como procesada.
                if (! $enviador->ejecutar($prog)) {
                    $this->line("Programación #{$prog->id}: omitida (ya en curso)");

                    return;
                }

                $estado = $prog->ultimo_ok ? 'ok' : 'ERROR: '.$prog->ultimo_resultado;
                $linea = "Programación #{$prog->id} ({$prog->cliente?->nombre}): {$estado}";
                $prog->ultimo_ok ? $this->line($linea) : $this->error($linea);
                $procesadas++;
            });

        $this->info("Procesadas: {$procesadas}");

        return self::SUCCESS;
    }
}
