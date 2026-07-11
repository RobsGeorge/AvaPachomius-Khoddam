<?php

namespace App\Console\Commands;

use App\Services\NotificationScannerService;
use Illuminate\Console\Command;

class ScanInstructorNotifications extends Command
{
    protected $signature = 'notifications:scan-instructor';

    protected $description = 'Scan instructor/admin alert notifications';

    public function handle(NotificationScannerService $scanner): int
    {
        $count = $scanner->scanInstructorAlerts();
        $this->info("Generated {$count} instructor notifications.");

        return self::SUCCESS;
    }
}
