<?php

namespace App\Console;

use App\Services\ScheduledTaskRegistrar;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        app(ScheduledTaskRegistrar::class)->register($schedule);
    }

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
        Commands\NotifyDailyBirthdays::class,
        Commands\ScanGradesRiskNotifications::class,
        Commands\ScanInstructorNotifications::class,
        Commands\ScanNotificationDeadlines::class,
        Commands\ScanNotificationEvents::class,
        Commands\FireNotificationReminders::class,
    ];
}
