<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // withoutOverlapping(30): sin argumento el mutex dura 24 h y un cron muerto frenaría todo el día.
        $schedule->command('correo:procesar-programados')->everyMinute()->withoutOverlapping(30);
        // Cada 15 min: la hora de envío es HH:MM y no debe llegar con casi una hora de retraso.
        $schedule->command('reportes:enviar-programados')->everyFifteenMinutes()->withoutOverlapping(30);
        $schedule->command('webhooks:reintentar')->everyMinute()->withoutOverlapping(30);
        // De madrugada: Google ya cerró el día anterior y no compite con el uso del panel.
        $schedule->command('gsc:sincronizar')->dailyAt('04:30')->withoutOverlapping(30);
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
