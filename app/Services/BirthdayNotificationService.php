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

    public function sendForCourse(Course $course, int $month, int $year): array
    {
        $students = $this->rosterService->enrolledStudents($course);
        $birthdayStudents = $this->rosterService->studentsWithBirthdayInMonth($students, $month);

        if ($birthdayStudents->isEmpty()) {
            return ['count' => 0, 'recipients' => collect()];
        }

        $staff = $this->rosterService->courseStaff($course->course_id);
        $recipients = collect();

        foreach ($staff as $recipient) {
            if (! $recipient->email) {
                continue;
            }

            Mail::to($recipient->email)->send(
                new MonthlyBirthdayAnnouncementMail($course, $birthdayStudents, $month, $year, $recipient)
            );
            $recipients->push($recipient);
        }

        return [
            'count' => $recipients->count(),
            'recipients' => $recipients,
        ];
    }

    public function sendForAllCourses(int $month, int $year): array
    {
        $courses = Course::query()->orderBy('title')->get();
        $summary = ['courses' => 0, 'emails' => 0];

        foreach ($courses as $course) {
            $result = $this->sendForCourse($course, $month, $year);
            if ($result['count'] > 0) {
                $summary['courses']++;
                $summary['emails'] += $result['count'];
            }
        }

        return $summary;
    }
}
