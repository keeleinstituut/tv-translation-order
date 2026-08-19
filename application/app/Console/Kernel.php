<?php

namespace App\Console;

use App\Console\Commands\AutoAcceptPendingProjects;
use App\Console\Commands\ExpireOverdueProposals;
use App\Console\Commands\NotifyProjectsPendingAutoAcceptance;
use App\Console\Commands\NotifyThatProjectDeadlineIsReached;
use App\Console\Commands\NotifyThatProjectTimeslotPassedWithNoAssignee;
use App\Console\Commands\SweepExpiredOutsourceOffers;
use App\Helpers\DateUtil;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $timezone = DateUtil::TIMEZONE;
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

        $schedule
            ->command(NotifyThatProjectTimeslotPassedWithNoAssignee::class)
            ->timezone($timezone)
            ->onOneServer()
            ->hourly();

        $schedule
            ->command(ExpireOverdueProposals::class)
            ->onOneServer()
            ->everyFiveMinutes();

        $schedule
            ->command(SweepExpiredOutsourceOffers::class)
            ->onOneServer()
            ->everyFiveMinutes();

        $schedule
            ->command(NotifyProjectsPendingAutoAcceptance::class)
            ->timezone($timezone)
            ->onOneServer()
            ->weekdays()
            ->hourly()
            ->between('09:00', '17:00');

        $schedule
            ->command(AutoAcceptPendingProjects::class)
            ->timezone($timezone)
            ->onOneServer()
            ->weekdays()
            ->hourly()
            ->between('09:00', '17:00');
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
