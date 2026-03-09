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
        // Sync online payments every minute. Output goes to a dedicated log (not laravel.log).
        $syncLog = storage_path('logs/online-payments-sync.log');
        $schedule->command('online-payments:sync --limit=500')
            ->everyMinute()
            ->withoutOverlapping(2)
            ->appendOutputTo($syncLog)
            ->runInBackground();
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
