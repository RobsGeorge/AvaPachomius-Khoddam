<?php

namespace App\Console\Commands;

use App\Services\ProfilePhotoReuploadReminderService;
use Illuminate\Console\Command;

class ScanProfilePhotoReuploadReminders extends Command
{
    protected $signature = 'profile-photos:scan-reupload-reminders';

    protected $description = 'Notify students who have not replaced a rejected profile photo';

    public function handle(ProfilePhotoReuploadReminderService $reminders): int
    {
        $count = $reminders->scan();
        $this->info("Generated {$count} profile photo re-upload reminders.");

        return self::SUCCESS;
    }
}
