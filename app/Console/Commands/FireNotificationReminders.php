<?php

namespace App\Console\Commands;

use App\Services\NotificationScannerService;
use Illuminate\Console\Command;

class FireNotificationReminders extends Command
{
    protected $signature = 'notifications:fire-reminders';

    protected $description = 'Fire due custom notification reminders';

    public function handle(NotificationScannerService $scanner): int
    {
        $count = $scanner->fireDueReminders();
        $this->info("Fired {$count} custom reminders.");

        return self::SUCCESS;
    }
}
