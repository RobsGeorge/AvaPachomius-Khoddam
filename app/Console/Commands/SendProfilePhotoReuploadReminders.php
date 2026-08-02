<?php

namespace App\Console\Commands;

use App\Services\ProfilePhotoReuploadReminderService;
use Illuminate\Console\Command;

class SendProfilePhotoReuploadReminders extends Command
{
    protected $signature = 'photos:send-reupload-reminders';

    protected $description = 'Remind rejected students to re-upload a profile photo after the configured delay';

    public function handle(ProfilePhotoReuploadReminderService $reminders): int
    {
        $count = $reminders->sendReminders();
        $this->info("Sent {$count} profile photo re-upload reminder(s).");

        return self::SUCCESS;
    }
}
