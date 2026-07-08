<?php

namespace App\Services;

use App\Mail\MonthlyBirthdayAnnouncementMail;
use App\Models\Course;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class BirthdayNotificationService
{
    public function __construct(
        private StudentRosterService $rosterService
    ) {}

    public function sendForCourse(Course $course, int $month, int $year): int
    {
        $students = $this->rosterService->enrolledStudents($course);
        $birthdayStudents = $this->rosterService->studentsWithBirthdayInMonth($students, $month);

        if ($birthdayStudents->isEmpty()) {
            return 0;
        }

        $staff = $this->rosterService->courseStaff($course->course_id);
        $sent = 0;

        foreach ($staff as $recipient) {
            if (! $recipient->email) {
                continue;
            }

            Mail::to($recipient->email)->send(
                new MonthlyBirthdayAnnouncementMail($course, $birthdayStudents, $month, $year, $recipient)
            );
            $sent++;
        }

        return $sent;
    }

    public function sendForAllCourses(int $month, int $year): array
    {
        $courses = Course::query()->orderBy('title')->get();
        $summary = ['courses' => 0, 'emails' => 0];

        foreach ($courses as $course) {
            $sent = $this->sendForCourse($course, $month, $year);
            if ($sent > 0) {
                $summary['courses']++;
                $summary['emails'] += $sent;
            }
        }

        return $summary;
    }
}
