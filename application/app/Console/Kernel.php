<?php

namespace App\Console;

use App\Console\Commands\NotifyThatProjectDeadlineIsReached;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $timezone = 'Europe/Tallinn';
        $schedule
            ->command('sync:all')
            ->timezone($timezone)
            ->onOneServer()
            ->dailyAt('04:00');

        $schedule
            ->command(NotifyThatProjectDeadlineIsReached::class)
            ->timezone($timezone)
            ->onOneServer()
            ->hourly();
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
