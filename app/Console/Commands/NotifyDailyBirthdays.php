<?php

namespace App\Console\Commands;

use App\Services\BirthdayNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class NotifyDailyBirthdays extends Command
{
    protected $signature = 'birthdays:notify-daily
                            {--date= : Birthday date (Y-m-d) in portal timezone, defaults to today}
                            {--course= : Optional course_id to limit to one course}';

    protected $description = 'Email course instructors and admins about student birthdays today, and create portal notifications';

    public function handle(BirthdayNotificationService $notificationService): int
    {
        $timezone = config('attendance.timezone', config('app.timezone'));
        $on = $this->option('date')
            ? Carbon::parse($this->option('date'), $timezone)->startOfDay()
            : now($timezone)->startOfDay();

        $courseId = $this->option('course');
        $dateLabel = $on->toDateString();

        $this->info("Sending birthday announcements for {$dateLabel}...");

        if ($courseId) {
            $course = \App\Models\Course::findOrFail($courseId);
            $result = $notificationService->notifyStaffForCourseToday($course, $on);
            $this->info(
                "Course \"{$course->title}\": {$result['emails']} email(s), {$result['portal']} portal notification(s)."
            );

            return self::SUCCESS;
        }

        $summary = $notificationService->notifyStaffForAllCoursesToday($on);
        $this->info(
            "Done. {$summary['courses']} course(s), {$summary['emails']} email(s), {$summary['portal']} portal notification(s)."
        );

        Log::info('birthdays:notify-daily completed', [
            'date' => $dateLabel,
            'courses' => $summary['courses'],
            'emails' => $summary['emails'],
            'portal' => $summary['portal'],
        ]);

        return self::SUCCESS;
    }
}
