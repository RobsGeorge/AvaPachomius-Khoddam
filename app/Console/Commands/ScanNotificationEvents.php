<?php

namespace App\Console\Commands;

use App\Services\NotificationScannerService;
use Illuminate\Console\Command;

class ScanNotificationEvents extends Command
{
    protected $signature = 'notifications:scan-events';

    protected $description = 'Scan nearby events and birthdays for notifications';

    public function handle(NotificationScannerService $scanner): int
    {
        $count = $scanner->scanEventsAndBirthdays();
        $this->info("Generated {$count} event/birthday notifications.");

        return self::SUCCESS;
    }
}
