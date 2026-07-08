<?php

namespace App\Console\Commands;

use App\Services\BirthdayNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class NotifyMonthlyBirthdays extends Command
{
    protected $signature = 'birthdays:notify-monthly
                            {--month= : Month number (1-12), defaults to current month}
                            {--year= : Year, defaults to current year}
                            {--course= : Optional course_id to limit to one course}';

    protected $description = 'Email course instructors and admins about student birthdays in the given month';

    public function handle(BirthdayNotificationService $notificationService): int
    {
        $timezone = config('attendance.timezone', config('app.timezone'));
        $now = now($timezone);

        $month = (int) ($this->option('month') ?: $now->month);
        $year = (int) ($this->option('year') ?: $now->year);
        $courseId = $this->option('course');

        if ($month < 1 || $month > 12) {
            $this->error('Month must be between 1 and 12.');

            return self::FAILURE;
        }

        $monthLabel = Carbon::create($year, $month, 1)->format('F Y');
        $this->info("Sending birthday announcements for {$monthLabel}...");

        if ($courseId) {
            $course = \App\Models\Course::findOrFail($courseId);
            $sent = $notificationService->sendForCourse($course, $month, $year);
            $this->info("Course \"{$course->title}\": {$sent} email(s) sent.");

            return self::SUCCESS;
        }

        $summary = $notificationService->sendForAllCourses($month, $year);
        $this->info("Done. {$summary['courses']} course(s), {$summary['emails']} email(s) sent.");

        return self::SUCCESS;
    }
}
