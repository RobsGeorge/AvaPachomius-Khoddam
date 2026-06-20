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
        $schedule->command('attendance:mark-absent')
            ->dailyAt('00:00')
            ->timezone(config('attendance.timezone'))
            ->when(fn () => config('attendance.auto_close_enabled'));
        $schedule->call(fn () => \App\Services\PendingRegistrationService::purgeStale())->daily();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    protected $commands = [
        Commands\MarkAbsentUsers::class,
        Commands\SetSuperAdmin::class,
        Commands\FlushAllSessions::class,
    ];
}
