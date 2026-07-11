<?php

namespace App\Console\Commands;

use App\Services\NotificationScannerService;
use Illuminate\Console\Command;

class ScanGradesRiskNotifications extends Command
{
    protected $signature = 'notifications:scan-grades-risk';

    protected $description = 'Scan students below passing grade for staff notifications';

    public function handle(NotificationScannerService $scanner): int
    {
        $count = $scanner->scanBelowPassingGrades();
        $this->info("Generated {$count} below-passing notifications.");

        return self::SUCCESS;
    }
}
