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
        $schedule->command('birthdays:notify-monthly')
            ->monthlyOn(1, '08:00')
            ->timezone(config('attendance.timezone', config('app.timezone')));
        $schedule->command('notifications:scan-deadlines')->hourly();
        $schedule->command('notifications:scan-events')->dailyAt('07:00');
        $schedule->command('notifications:scan-instructor')->dailyAt('08:00');
        $schedule->command('notifications:scan-grades-risk')->weeklyOn(1, '09:00');
        $schedule->command('notifications:fire-reminders')->everyFiveMinutes();
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
        Commands\NotifyMonthlyBirthdays::class,
        Commands\ScanGradesRiskNotifications::class,
        Commands\ScanInstructorNotifications::class,
        Commands\ScanNotificationDeadlines::class,
        Commands\ScanNotificationEvents::class,
        Commands\FireNotificationReminders::class,
    ];
}
