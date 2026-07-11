<?php

namespace App\Console\Commands;

use App\Services\NotificationScannerService;
use Illuminate\Console\Command;

class ScanNotificationDeadlines extends Command
{
    protected $signature = 'notifications:scan-deadlines';

    protected $description = 'Scan assignment and exam deadlines for notifications';

    public function handle(NotificationScannerService $scanner): int
    {
        $count = $scanner->scanDeadlines();
        $this->info("Generated {$count} deadline notifications.");

        return self::SUCCESS;
    }
}
