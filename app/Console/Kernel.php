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
        // Generar matrículas mensuales todos los días a la 1:00 AM
        $schedule->command('tuition:generate')->dailyAt('01:00');

        // Verificar pagos vencidos todos los días a las 2:00 AM
        $schedule->command('tuition:check-overdue')->dailyAt('02:00');
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
